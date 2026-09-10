<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Tabella incontri (slice C1). A ogni warp, con probabilità encounter.chance e
 * rispettando un cooldown, si estrae un incontro compatibile col contesto e lo
 * si mette "in sospeso": la plancia mostra un pannello con 2-3 scelte, ognuna
 * con esiti pesati ed eventuale skill-check d'equipaggio. I template stanno in
 * `encounters` (data-driven), lo stato per giocatore in `player_encounters`.
 */
final class Encounters
{
    public static function chance(): int
    {
        return max(0, GameConfig::int('encounter.chance', 15));
    }

    public static function cooldownMin(): int
    {
        return max(0, GameConfig::int('encounter.cooldown_min', 10));
    }

    public static function repeatMin(): int
    {
        return max(0, GameConfig::int('encounter.per_encounter_repeat_min', 720));
    }

    /**
     * Chiamato da Navigation::move dopo l'arrivo. Estrae un incontro se: nessuno
     * in sospeso, cooldown globale rispettato, tiro riuscito, e c'è almeno un
     * template compatibile.
     *
     * @param array<string,mixed> $player
     * @return array{title:string}|null  (solo per il teaser negli entry-events)
     */
    public static function maybeSpawn(array $player): ?array
    {
        $pid = (int) $player['id'];

        if (Database::first("SELECT 1 AS x FROM player_encounters WHERE player_id = ? AND status = 'pending' LIMIT 1", [$pid]) !== null) {
            return null;
        }
        $cd = self::cooldownMin();
        if ($cd > 0 && Database::first(
            "SELECT 1 AS x FROM player_encounters WHERE player_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1",
            [$pid, $cd]
        ) !== null) {
            return null;
        }
        if (mt_rand(1, 100) > self::chance()) {
            return null;
        }

        $sectorId = (int) $player['sector_id'];
        $sec = Universe::sector($sectorId);
        if ($sec === null) {
            return null;
        }
        $ctx = [
            'is_fedspace' => (bool) $sec['is_fedspace'],
            'region_kind' => (string) ($sec['region_kind'] ?? 'core'),
            'alignment'   => (int) ($player['alignment'] ?? 0),
            'experience'  => (int) ($player['experience'] ?? 0),
            'free_holds'  => self::freeHolds($pid),
        ];

        $repeat = self::repeatMin();
        $pool = [];
        foreach (Database::all("SELECT * FROM encounters WHERE enabled = 1") as $enc) {
            if (!self::eligible(self::decode($enc['conditions']), $ctx)) {
                continue;
            }
            if ((int) $enc['once'] === 1 && Database::first(
                "SELECT 1 AS x FROM player_encounters WHERE player_id = ? AND encounter_id = ? LIMIT 1",
                [$pid, (int) $enc['id']]
            ) !== null) {
                continue;
            }
            $encCd = $enc['cooldown_min'] !== null ? (int) $enc['cooldown_min'] : $repeat;
            if ($encCd > 0 && Database::first(
                "SELECT 1 AS x FROM player_encounters WHERE player_id = ? AND encounter_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1",
                [$pid, (int) $enc['id'], $encCd]
            ) !== null) {
                continue;
            }
            $pool[] = ['v' => $enc, 'w' => max(1, (int) $enc['weight'])];
        }
        if ($pool === []) {
            return null;
        }

        $enc = self::weightedPick($pool);
        Database::run(
            "INSERT INTO player_encounters (player_id, encounter_id, sector_id, status) VALUES (?, ?, ?, 'pending')",
            [$pid, (int) $enc['id'], $sectorId]
        );
        return ['title' => (string) $enc['title']];
    }

    /**
     * L'incontro in sospeso del giocatore, pronto per il pannello di plancia.
     * @return array{pe_id:int,ckey:string,title:string,body:string,choices:list<array{key:string,label:string,skill_label:?string}>}|null
     */
    public static function pending(int $playerId): ?array
    {
        $row = self::pendingRow($playerId);
        if ($row === null) {
            return null;
        }
        $choices = [];
        foreach (self::decode($row['choices']) as $c) {
            $role = $c['skill']['role'] ?? null;
            $choices[] = [
                'key'         => (string) ($c['key'] ?? ''),
                'label'       => (string) ($c['label'] ?? '—'),
                'skill_label' => $role ? (Crew::ROLE_LABEL[$role] ?? ucfirst((string) $role)) : null,
            ];
        }
        return [
            'pe_id'   => (int) $row['pe_id'],
            'ckey'    => (string) $row['ckey'],
            'title'   => (string) $row['title'],
            'body'    => (string) $row['body'],
            'choices' => $choices,
        ];
    }

    /**
     * Risolve il pending con la scelta indicata.
     * @return array{ok:bool, error?:string, text?:string, summary?:string}
     */
    public static function resolve(int $playerId, string $choiceKey): array
    {
        $row = self::pendingRow($playerId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Nessun incontro in sospeso.'];
        }

        $choice = null;
        foreach (self::decode($row['choices']) as $c) {
            if ((string) ($c['key'] ?? '') === $choiceKey) {
                $choice = $c;
                break;
            }
        }
        if ($choice === null) {
            return ['ok' => false, 'error' => 'Scelta non valida.'];
        }

        $pass = null;
        if (!empty($choice['skill']['role'])) {
            $role  = (string) $choice['skill']['role'];
            $dc    = (int) ($choice['skill']['dc'] ?? 4);
            $pass  = (self::officerSkill($playerId, $role) + mt_rand(0, 6)) >= $dc;
        }

        $outcomes = [];
        foreach (($choice['outcomes'] ?? []) as $o) {
            $need = $o['if'] ?? null;
            if ($pass === null || $need === null
                || ($need === 'pass' && $pass) || ($need === 'fail' && !$pass)) {
                $outcomes[] = $o;
            }
        }
        if ($outcomes === []) {
            $outcomes = $choice['outcomes'] ?? [['weight' => 1, 'text' => 'Non succede nulla di particolare.', 'effects' => ['nothing' => true]]];
        }

        $pick = self::weightedPick(array_map(
            static fn ($o) => ['v' => $o, 'w' => max(1, (int) ($o['weight'] ?? 1))],
            $outcomes
        ));
        $text    = (string) ($pick['text'] ?? '');
        $summary = self::applyEffects($playerId, (array) ($pick['effects'] ?? []));

        Database::run(
            "UPDATE player_encounters SET status = 'resolved', choice_key = ?, outcome_text = ?, resolved_at = NOW() WHERE id = ?",
            [mb_substr($choiceKey, 0, 24), mb_substr($text, 0, 255), (int) $row['pe_id']]
        );

        $eff = (array) ($pick['effects'] ?? []);
        $sev = (isset($eff['shields']) && (int) $eff['shields'] < 0) || (isset($eff['fighters']) && (int) $eff['fighters'] < 0)
            ? 'warning' : 'info';
        ShipLog::write(
            $playerId,
            'system',
            $sev,
            'Incontro: ' . (string) $row['title'],
            $text . ($summary !== '' ? "\n(" . $summary . ')' : ''),
            (int) $row['sector_id'],
            ['encounter' => (string) $row['ckey']]
        );

        return ['ok' => true, 'text' => $text, 'summary' => $summary];
    }

    /** Il giocatore è ripartito senza scegliere: il momento è passato. */
    public static function expireStale(int $playerId): void
    {
        Database::run(
            "UPDATE player_encounters SET status = 'expired', resolved_at = NOW() WHERE player_id = ? AND status = 'pending'",
            [$playerId]
        );
    }

    /** GC dal tick: chiude i pending dimenticati da oltre 6 ore. */
    public static function gc(): int
    {
        return Database::run(
            "UPDATE player_encounters SET status = 'expired', resolved_at = NOW()
             WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)"
        )->rowCount();
    }

    // --- interni ------------------------------------------------------

    /** @return array<string,mixed>|null */
    private static function pendingRow(int $playerId): ?array
    {
        return Database::first(
            "SELECT pe.id AS pe_id, pe.sector_id, e.*
             FROM player_encounters pe JOIN encounters e ON e.id = pe.encounter_id
             WHERE pe.player_id = ? AND pe.status = 'pending'
             ORDER BY pe.id DESC LIMIT 1",
            [$playerId]
        );
    }

    private static function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $d = json_decode((string) ($json ?? ''), true);
        return is_array($d) ? $d : [];
    }

    /**
     * @param array<string,mixed> $cond
     * @param array<string,mixed> $ctx
     */
    private static function eligible(array $cond, array $ctx): bool
    {
        if ($cond === []) {
            return true;
        }
        if (!empty($cond['not_fedspace']) && $ctx['is_fedspace']) {
            return false;
        }
        if (!empty($cond['is_fedspace']) && !$ctx['is_fedspace']) {
            return false;
        }
        if (!empty($cond['region_kind']) && !in_array($ctx['region_kind'], (array) $cond['region_kind'], true)) {
            return false;
        }
        if (isset($cond['min_align']) && $ctx['alignment'] < (int) $cond['min_align']) {
            return false;
        }
        if (isset($cond['max_align']) && $ctx['alignment'] > (int) $cond['max_align']) {
            return false;
        }
        if (isset($cond['min_experience']) && $ctx['experience'] < (int) $cond['min_experience']) {
            return false;
        }
        if (!empty($cond['needs_free_hold']) && (int) $ctx['free_holds'] < 1) {
            return false;
        }
        return true;
    }

    private static function freeHolds(int $playerId): int
    {
        $s = Database::first(
            "SELECT s.holds_total,
                    (s.hold_ore + s.hold_organics + s.hold_equipment + s.hold_colonists) AS used
             FROM ships s JOIN players p ON p.ship_id = s.id WHERE p.id = ?",
            [$playerId]
        );
        return $s === null ? 0 : max(0, (int) $s['holds_total'] - (int) $s['used']);
    }

    private static function officerSkill(int $playerId, string $role): int
    {
        $o = Database::first(
            "SELECT skills, level FROM officers
             WHERE player_id = ? AND role = ? AND assigned = 1 AND status <> 'dead'
             ORDER BY level DESC LIMIT 1",
            [$playerId, $role]
        );
        if ($o === null) {
            return 0;
        }
        $sk = json_decode((string) $o['skills'], true) ?: [];
        $primary = Crew::PRIMARY[$role] ?? null;
        return (int) ($primary !== null && isset($sk[$primary]) ? $sk[$primary] : ($o['level'] ?? 0));
    }

    /**
     * @param list<array{v:mixed,w:int}> $pool
     * @return mixed  l'elemento `v` estratto
     */
    private static function weightedPick(array $pool): mixed
    {
        $total = 0;
        foreach ($pool as $p) {
            $total += $p['w'];
        }
        $r = mt_rand(1, max(1, $total));
        foreach ($pool as $p) {
            $r -= $p['w'];
            if ($r <= 0) {
                return $p['v'];
            }
        }
        return $pool[array_key_last($pool)]['v'];
    }

    /**
     * Applica gli effetti di un esito. Whitelist rigida, tutto clampato.
     * @param array<string,mixed> $eff
     * @return string riassunto leggibile
     */
    private static function applyEffects(int $playerId, array $eff): string
    {
        $pl = Database::first("SELECT ship_id FROM players WHERE id = ?", [$playerId]);
        if ($pl === null) {
            return '';
        }
        $shipId = (int) $pl['ship_id'];
        $parts = [];

        foreach (['credits' => 'cr', 'experience' => 'exp', 'salvage' => 'leghe', 'crystals' => 'cristalli', 'components' => 'componenti'] as $col => $lbl) {
            if (!isset($eff[$col]) || (int) $eff[$col] === 0) {
                continue;
            }
            $d = (int) $eff[$col];
            Database::run("UPDATE players SET {$col} = GREATEST(0, {$col} + ?) WHERE id = ?", [$d, $playerId]);
            $parts[] = ($d > 0 ? '+' : '') . number_format($d, 0, ',', '.') . ' ' . $lbl;
        }
        if (isset($eff['alignment']) && (int) $eff['alignment'] !== 0) {
            $d = (int) $eff['alignment'];
            Database::run("UPDATE players SET alignment = alignment + ? WHERE id = ?", [$d, $playerId]);
            $parts[] = 'allineamento ' . ($d > 0 ? '+' : '') . $d;
        }
        if (isset($eff['turns']) && (int) $eff['turns'] !== 0) {
            $d = (int) $eff['turns'];
            Database::run("UPDATE players SET turns = GREATEST(0, turns + ?) WHERE id = ?", [$d, $playerId]);
            $parts[] = ($d > 0 ? '+' : '') . $d . ' turni';
        }
        if (!empty($eff['faction']) && is_array($eff['faction'])) {
            foreach ($eff['faction'] as $fk => $fd) {
                $fd = (int) $fd;
                if ($fd === 0 || !in_array((string) $fk, Faction::KEYS, true)) {
                    continue;
                }
                Faction::adjust($playerId, (string) $fk, $fd, 'incontro');
                $parts[] = "rep {$fk} " . ($fd > 0 ? '+' : '') . $fd;
            }
        }
        if (isset($eff['shields']) && (int) $eff['shields'] !== 0) {
            $d = (int) $eff['shields'];
            Database::run("UPDATE ships SET shields = GREATEST(0, shields + ?) WHERE id = ?", [$d, $shipId]);
            $parts[] = 'scudi ' . ($d > 0 ? '+' : '') . number_format($d, 0, ',', '.');
        }
        if (isset($eff['fighters']) && (int) $eff['fighters'] !== 0) {
            $d = (int) $eff['fighters'];
            Database::run("UPDATE ships SET fighters = GREATEST(0, fighters + ?) WHERE id = ?", [$d, $shipId]);
            $parts[] = 'caccia ' . ($d > 0 ? '+' : '') . $d;
        }
        if (!empty($eff['cargo']) && is_array($eff['cargo'])) {
            $map = ['ore' => 'hold_ore', 'organics' => 'hold_organics', 'equipment' => 'hold_equipment', 'colonists' => 'hold_colonists'];
            $s = Database::first("SELECT holds_total, hold_ore, hold_organics, hold_equipment, hold_colonists FROM ships WHERE id = ?", [$shipId]);
            $room = max(0, (int) $s['holds_total'] - ((int) $s['hold_ore'] + (int) $s['hold_organics'] + (int) $s['hold_equipment'] + (int) $s['hold_colonists']));
            foreach ($eff['cargo'] as $k => $q) {
                if (!isset($map[$k])) {
                    continue;
                }
                $q = (int) $q;
                if ($q > 0) {
                    $q = min($q, $room);
                }
                if ($q === 0) {
                    continue;
                }
                $col = $map[$k];
                Database::run("UPDATE ships SET {$col} = GREATEST(0, {$col} + ?) WHERE id = ?", [$q, $shipId]);
                $room = max(0, $room - max(0, $q));
                $parts[] = ($q > 0 ? '+' : '') . $q . ' ' . $k;
            }
        }
        if (!empty($eff['module'])) {
            $mod = Loot::grant($playerId, 'encounter', false, is_string($eff['module']) ? $eff['module'] : null);
            if ($mod !== null) {
                $parts[] = 'modulo: ' . ($mod['name'] ?? 'nuovo');
            }
        }

        return implode(', ', $parts);
    }
}
