<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 8 — equipaggio: ufficiali con ruolo, livello, skill e abilità.
 * I bonus passivi degli imbarcati sono fusi in ShipStats::effective().
 */
final class Crew
{
    public const ROLES = ['tactical', 'navigator', 'engineer', 'scientist', 'medic', 'diplomat'];
    public const SKILLS = ['combat', 'piloting', 'engineering', 'science', 'medicine', 'diplomacy'];

    public const ROLE_LABEL = [
        'tactical' => 'Tattico', 'navigator' => 'Navigatore', 'engineer' => 'Ingegnere',
        'scientist' => 'Scienziato', 'medic' => 'Medico', 'diplomat' => 'Diplomatico',
    ];
    public const SKILL_LABEL = [
        'combat' => 'Combattimento', 'piloting' => 'Pilotaggio', 'engineering' => 'Ingegneria',
        'science' => 'Scienza', 'medicine' => 'Medicina', 'diplomacy' => 'Diplomazia',
    ];
    public const PRIMARY = [
        'tactical' => 'combat', 'navigator' => 'piloting', 'engineer' => 'engineering',
        'scientist' => 'science', 'medic' => 'medicine', 'diplomat' => 'diplomacy',
    ];

    private const GIVEN = ['Alira', 'Dex', 'Sova', 'Renn', 'Mira', 'Kael', 'Tarin', 'Vosk', 'Ysolde', 'Bram',
        'Nadia', 'Orin', 'Selene', 'Cato', 'Lira', 'Marek', 'Juno', 'Pell', 'Rhea', 'Talos', 'Ines', 'Garro'];
    private const SURNAME = ['Vantar', 'Okoro', 'Rell', 'Sunhu', 'Barange', 'Wick', 'Draeven', 'Colombo', 'Ferro',
        'Kestrel', 'Amari', 'Nakamura', 'Solari', 'Voss', 'Renard', 'Achebe', 'Bianchi', 'Corso', 'Halden', 'Ito'];

    // --- ruolo e abilità ---------------------------------------------------

    public static function roleLabel(string $r): string
    {
        return self::ROLE_LABEL[$r] ?? $r;
    }

    /** @return array{name:string,desc:string} */
    public static function abilityInfo(string $role, int $tier = 1): array
    {
        $t2 = $tier >= 2;
        return match ($role) {
            'tactical'  => ['name' => 'Mira', 'desc' => 'Il prossimo attacco infligge molto più danno' . ($t2 ? ' (potenziata)' : '') . '.'],
            'navigator' => ['name' => 'Rotta rapida', 'desc' => 'Il prossimo salto di warp è gratuito' . ($t2 ? ' e non fa scattare intercettazioni' : '') . '.'],
            'engineer'  => ['name' => 'Squadra di riparazione', 'desc' => 'Ripristina subito una parte di caccia e scudi' . ($t2 ? ' (di più)' : '') . '.'],
            'scientist' => ['name' => 'Scansione profonda', 'desc' => 'Rivela i settori adiacenti e garantisce il bottino del prossimo kill.'],
            'medic'     => ['name' => 'Triage', 'desc' => 'Rimette in servizio un ufficiale ferito.'],
            'diplomat'  => ['name' => 'Negoziato', 'desc' => 'Al prossimo ingresso in un settore ostile eviti pedaggi e ingaggi.'],
            default     => ['name' => '—', 'desc' => ''],
        };
    }

    // --- generazione -----------------------------------------------------

    private static function randName(): string
    {
        return self::GIVEN[array_rand(self::GIVEN)] . ' ' . self::SURNAME[array_rand(self::SURNAME)];
    }

    /** @return array<string,int> */
    public static function rollSkills(string $role, int $level, ?array $weights = null): array
    {
        if ($weights === null) {
            $a = Database::first('SELECT weights FROM officer_archetypes WHERE role = ? ORDER BY RAND() LIMIT 1', [$role]);
            $weights = ShipStats::decode($a['weights'] ?? null) ?? [];
        }
        $per = GameConfig::float('crew.skill_per_level', 1.8);
        $out = [];
        foreach (self::SKILLS as $s) {
            $w = (float) ($weights[$s] ?? 0.2);
            $base = 3 + $w * (5 + $per * ($level - 1) * 2);
            $out[$s] = max(1, (int) round($base * (0.8 + (mt_rand() / mt_getrandmax()) * 0.5)));
        }
        return $out;
    }

    public static function topUpRecruits(int $playerId): void
    {
        $size = GameConfig::int('crew.recruit_pool_size', 4);
        $rows = Database::all('SELECT id, created_at FROM recruit_candidates WHERE player_id = ? ORDER BY created_at', [$playerId]);
        $stale = $rows !== [] && (time() - strtotime((string) $rows[0]['created_at'])) > GameConfig::int('crew.recruit_refresh_hours', 6) * 3600;
        if ($stale) {
            Database::run('DELETE FROM recruit_candidates WHERE player_id = ?', [$playerId]);
            $rows = [];
        }
        for ($i = count($rows); $i < $size; $i++) {
            $role = self::ROLES[array_rand(self::ROLES)];
            $arch = Database::first('SELECT ckey, weights FROM officer_archetypes WHERE role = ? ORDER BY RAND() LIMIT 1', [$role]);
            $level = mt_rand(1, 2);
            $skills = self::rollSkills($role, $level, ShipStats::decode($arch['weights'] ?? null));
            $cost = GameConfig::int('crew.hire_cost_base', 1500) + $level * GameConfig::int('crew.hire_cost_per_level', 1400);
            Database::run(
                'INSERT INTO recruit_candidates (player_id, name, role, archetype, level, skills, cost) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$playerId, self::randName(), $role, $arch['ckey'] ?? null, $level, json_encode($skills, JSON_UNESCAPED_UNICODE), $cost]
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public static function recruits(int $playerId): array
    {
        self::topUpRecruits($playerId);
        return Database::all('SELECT * FROM recruit_candidates WHERE player_id = ? ORDER BY role, level DESC', [$playerId]);
    }

    // --- roster ---------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public static function roster(int $playerId): array
    {
        return Database::all(
            "SELECT * FROM officers WHERE player_id = ? AND status <> 'dead'
             ORDER BY assigned DESC, FIELD(role,'tactical','navigator','engineer','scientist','medic','diplomat'), level DESC",
            [$playerId]
        );
    }

    public static function counts(int $playerId): array
    {
        $r = Database::first(
            "SELECT
               SUM(assigned = 1 AND status <> 'dead') a,
               SUM(assigned = 0 AND status <> 'dead') b,
               SUM(status = 'injured') inj
             FROM officers WHERE player_id = ?",
            [$playerId]
        );
        return ['assigned' => (int) ($r['a'] ?? 0), 'bench' => (int) ($r['b'] ?? 0), 'injured' => (int) ($r['inj'] ?? 0)];
    }

    public static function slots(string $shipTypeKey): int
    {
        return (int) (Database::first('SELECT crew_slots FROM ship_types WHERE ckey = ?', [$shipTypeKey])['crew_slots'] ?? 0);
    }

    // --- azioni -------------------------------------------------------

    public static function hire(array $player, string $shipTypeKey, int $candidateId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il reclutamento avviene solo allo StarDock.'];
        }
        $c = Database::first('SELECT * FROM recruit_candidates WHERE id = ? AND player_id = ?', [$candidateId, (int) $player['id']]);
        if ($c === null) {
            return ['ok' => false, 'error' => 'Candidato non disponibile.'];
        }
        if ((int) $player['credits'] < (int) $c['cost']) {
            return ['ok' => false, 'error' => "Servono {$c['cost']} cr."];
        }
        $slots = self::slots($shipTypeKey);
        $assigned = self::counts((int) $player['id'])['assigned'];
        $doAssign = $slots > 0 && $assigned < $slots ? 1 : 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [(int) $c['cost'], (int) $player['id']]);
            Database::run(
                'INSERT INTO officers (player_id, name, role, archetype, level, xp, skills, assigned, origin)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
                [(int) $player['id'], $c['name'], $c['role'], $c['archetype'], (int) $c['level'], $c['skills'], $doAssign, 'hire']
            );
            Database::run('DELETE FROM recruit_candidates WHERE id = ?', [$candidateId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $c['name'], 'role' => $c['role'], 'assigned' => (bool) $doAssign, 'cost' => (int) $c['cost']];
    }

    public static function assign(array $player, string $shipTypeKey, int $officerId): array
    {
        $o = self::own($player, $officerId);
        if ($o === null) {
            return ['ok' => false, 'error' => 'Ufficiale non trovato.'];
        }
        if ((int) $o['assigned'] === 1) {
            return ['ok' => false, 'error' => 'Già in servizio.'];
        }
        $slots = self::slots($shipTypeKey);
        if (self::counts((int) $player['id'])['assigned'] >= $slots) {
            return ['ok' => false, 'error' => $slots <= 0 ? 'Questo scafo non ha posti equipaggio.' : 'Stazioni al completo: congeda qualcuno.'];
        }
        Database::run('UPDATE officers SET assigned = 1 WHERE id = ?', [$officerId]);
        return ['ok' => true, 'name' => $o['name']];
    }

    public static function bench(array $player, int $officerId): array
    {
        $o = self::own($player, $officerId);
        if ($o === null) {
            return ['ok' => false, 'error' => 'Ufficiale non trovato.'];
        }
        Database::run('UPDATE officers SET assigned = 0 WHERE id = ?', [$officerId]);
        return ['ok' => true, 'name' => $o['name']];
    }

    public static function dismiss(array $player, int $officerId): array
    {
        $o = self::own($player, $officerId);
        if ($o === null) {
            return ['ok' => false, 'error' => 'Ufficiale non trovato.'];
        }
        Database::run('DELETE FROM officers WHERE id = ?', [$officerId]);
        return ['ok' => true, 'name' => $o['name']];
    }

    public static function heal(array $player, int $officerId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'L\'infermeria completa è allo StarDock (in volo serve il Triage del Medico).'];
        }
        $o = self::own($player, $officerId);
        if ($o === null || $o['status'] !== 'injured') {
            return ['ok' => false, 'error' => 'Nessun ufficiale ferito con quell\'id.'];
        }
        $cost = GameConfig::int('crew.injury_heal_cost', 2500);
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr per le cure."];
        }
        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, (int) $player['id']]);
        Database::run("UPDATE officers SET status = 'active', ready_at = NULL WHERE id = ?", [$officerId]);
        return ['ok' => true, 'name' => $o['name'], 'cost' => $cost];
    }

    // --- abilità -----------------------------------------------------

    public static function useAbility(array $player, array $ship, int $officerId, int $targetId = 0): array
    {
        $o = self::own($player, $officerId);
        if ($o === null) {
            return ['ok' => false, 'error' => 'Ufficiale non trovato.'];
        }
        if ((int) $o['assigned'] !== 1 || $o['status'] !== 'active') {
            return ['ok' => false, 'error' => 'L\'ufficiale deve essere in servizio e in salute.'];
        }
        if ($o['ready_at'] !== null && strtotime((string) $o['ready_at']) > time()) {
            return ['ok' => false, 'error' => 'Abilità in ricarica (pronta ' . substr((string) $o['ready_at'], 11, 5) . ').'];
        }
        $turnCost = GameConfig::int('crew.ability_turn_cost', 15);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $turnCost && $o['role'] !== 'medic') {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$turnCost})."];
        }

        $sk = ShipStats::decode($o['skills']) ?? [];
        $tier = (int) $o['ability_tier'];
        $primary = (int) ($sk[self::PRIMARY[$o['role']]] ?? 5);
        $expires = date('Y-m-d H:i:s', time() + 7200);
        $msg = '';

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            switch ($o['role']) {
                case 'tactical':
                    $mag = 12 + $primary * 0.7 + ($tier - 1) * 12;
                    self::addPending((int) $player['id'], 'attack_bonus_pct', $mag, $expires);
                    $msg = 'Mira agganciata: +' . round($mag) . '% al prossimo attacco.';
                    break;
                case 'navigator':
                    self::addPending((int) $player['id'], 'free_warp', 1, $expires);
                    if ($tier >= 2) {
                        self::addPending((int) $player['id'], 'no_engage', 1, $expires);
                    }
                    $msg = 'Rotta rapida pronta: il prossimo salto è gratis.';
                    break;
                case 'engineer':
                    $fr = 0.10 + $primary * 0.004 + ($tier - 1) * 0.10;
                    $addF = (int) round((int) ($ship['max_fighters'] ?? 0) * $fr);
                    $addS = (int) round((int) ($ship['max_shields'] ?? 0) * $fr);
                    Database::run(
                        'UPDATE ships SET fighters = LEAST(?, fighters + ?), shields = LEAST(?, shields + ?) WHERE id = ?',
                        [(int) ($ship['max_fighters'] ?? 0), $addF, (int) ($ship['max_shields'] ?? 0), $addS, (int) $ship['id']]
                    );
                    $msg = "Riparazione: +{$addF} caccia, +{$addS} scudi.";
                    break;
                case 'scientist':
                    foreach (Universe::warpsFrom((int) $player['sector_id']) as $adj) {
                        Database::run('INSERT IGNORE INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)', [(int) $player['id'], $adj]);
                    }
                    self::addPending((int) $player['id'], 'guaranteed_drop', 1, $expires);
                    $msg = 'Scansione profonda: settori adiacenti rivelati, prossimo bottino garantito.';
                    break;
                case 'medic':
                    $inj = $targetId > 0
                        ? Database::first("SELECT * FROM officers WHERE id = ? AND player_id = ? AND status = 'injured'", [$targetId, (int) $player['id']])
                        : Database::first("SELECT * FROM officers WHERE player_id = ? AND status = 'injured' ORDER BY level DESC LIMIT 1", [(int) $player['id']]);
                    if ($inj === null) {
                        $pdo->rollBack();
                        return ['ok' => false, 'error' => 'Nessun ufficiale ferito da curare.'];
                    }
                    Database::run("UPDATE officers SET status = 'active', ready_at = NULL WHERE id = ?", [(int) $inj['id']]);
                    $msg = "Triage: {$inj['name']} torna in servizio.";
                    break;
                case 'diplomat':
                    self::addPending((int) $player['id'], 'no_engage', 1, $expires);
                    $msg = 'Negoziato preparato: nessun ingaggio al prossimo ingresso ostile.';
                    break;
            }
            if ($o['role'] !== 'medic') {
                Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$turnCost, (int) $player['id']]);
            }
            Database::run(
                'UPDATE officers SET ready_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s', time() + GameConfig::int('crew.ability_cooldown_min', 90) * 60), $officerId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'msg' => $msg, 'name' => $o['name']];
    }

    private static function addPending(int $playerId, string $effect, float $mag, string $expires): void
    {
        // un solo effetto attivo per tipo
        Database::run('DELETE FROM crew_pending WHERE player_id = ? AND effect = ?', [$playerId, $effect]);
        Database::run(
            'INSERT INTO crew_pending (player_id, effect, magnitude, expires_at) VALUES (?, ?, ?, ?)',
            [$playerId, $effect, $mag, $expires]
        );
    }

    /** Consuma (una volta) un effetto pendente; ritorna la magnitudine o null. */
    public static function consumePending(int $playerId, string $effect): ?float
    {
        try {
            $row = Database::first(
                'SELECT id, magnitude FROM crew_pending WHERE player_id = ? AND effect = ? AND expires_at > NOW() ORDER BY id LIMIT 1',
                [$playerId, $effect]
            );
            if ($row === null) {
                return null;
            }
            Database::run('DELETE FROM crew_pending WHERE id = ?', [(int) $row['id']]);
            return (float) $row['magnitude'];
        } catch (\Throwable) {
            return null;
        }
    }

    // --- bonus passivi (usati da ShipStats) ------------------------------

    /** @return array<string,float> */
    public static function passiveBonuses(int $playerId): array
    {
        $out = [
            'combat_pct' => 0.0, 'shield_regen' => 0.0, 'scan_range' => 0.0, 'drop_luck_pct' => 0.0,
            'warp_discount_pct' => 0.0, 'align_shield_pct' => 0.0, 'away_medicine' => 0.0, 'count' => 0,
        ];
        try {
            $offs = Database::all(
                "SELECT role, skills, level FROM officers WHERE player_id = ? AND assigned = 1 AND status = 'active'",
                [$playerId]
            );
        } catch (\Throwable) {
            return $out;
        }
        $dim = GameConfig::float('crew.passive_diminish', 0.55);
        $seen = [];
        // ordina per skill primaria decrescente così il migliore prende il bonus pieno
        usort($offs, static function ($a, $b) {
            $sa = ShipStats::decode($a['skills']) ?? [];
            $sb = ShipStats::decode($b['skills']) ?? [];
            return ($sb[Crew::PRIMARY[$b['role']]] ?? 0) <=> ($sa[Crew::PRIMARY[$a['role']]] ?? 0);
        });
        foreach ($offs as $o) {
            $out['count']++;
            $sk = ShipStats::decode($o['skills']) ?? [];
            $k = $seen[$o['role']] = ($seen[$o['role']] ?? -1) + 1;
            $f = $k === 0 ? 1.0 : $dim ** $k;
            switch ($o['role']) {
                case 'tactical':  $out['combat_pct']        += ($sk['combat'] ?? 0) * 0.5 * $f; break;
                case 'engineer':  $out['shield_regen']      += ($sk['engineering'] ?? 0) * 2.2 * $f; break;
                case 'scientist': $out['scan_range']        += round(($sk['science'] ?? 0) / 8) * $f;
                                  $out['drop_luck_pct']     += ($sk['science'] ?? 0) * 0.9 * $f; break;
                case 'navigator': $out['warp_discount_pct'] += min(($sk['piloting'] ?? 0) * 2, 55) * $f; break;
                case 'diplomat':  $out['align_shield_pct']  += min(($sk['diplomacy'] ?? 0) * 2, 60) * $f; break;
                case 'medic':     $out['away_medicine']     += ($sk['medicine'] ?? 0) * $f; break;
            }
        }
        return $out;
    }

    // --- XP / livello --------------------------------------------------

    public static function xpForNext(int $level): int
    {
        return (int) round(GameConfig::int('crew.xp_curve_base', 100) * $level ** 1.5);
    }

    public static function awardKillXp(int $playerId, int $amount = 0): void
    {
        try {
            $amount = $amount > 0 ? $amount : GameConfig::int('crew.xp_per_kill', 12);
            $max = GameConfig::int('crew.max_level', 8);
            foreach (Database::all(
                "SELECT id, role, level, xp, skills, archetype FROM officers WHERE player_id = ? AND assigned = 1 AND status = 'active'",
                [$playerId]
            ) as $o) {
                self::grantXp((int) $o['id'], $o, $amount, $max);
            }
        } catch (\Throwable) {
            // tabelle non migrate
        }
    }

    /** @param array<string,mixed> $o */
    public static function grantXp(int $officerId, array $o, int $amount, ?int $max = null): void
    {
        $max ??= GameConfig::int('crew.max_level', 8);
        $level = (int) $o['level'];
        $xp = (int) $o['xp'] + $amount;
        $skills = ShipStats::decode($o['skills']) ?? [];
        $arch = $o['archetype'] ? Database::first('SELECT weights FROM officer_archetypes WHERE ckey = ?', [$o['archetype']]) : null;
        $weights = ShipStats::decode($arch['weights'] ?? null) ?? [];
        $per = GameConfig::float('crew.skill_per_level', 1.8);
        while ($level < $max && $xp >= self::xpForNext($level)) {
            $xp -= self::xpForNext($level);
            $level++;
            foreach (self::SKILLS as $s) {
                $w = (float) ($weights[$s] ?? 0.2);
                $skills[$s] = (int) ($skills[$s] ?? 3) + max(0, (int) round($per * $w * (0.6 + (mt_rand() / mt_getrandmax()))));
            }
        }
        Database::run(
            'UPDATE officers SET level = ?, xp = ?, skills = ? WHERE id = ?',
            [$level, $xp, json_encode($skills, JSON_UNESCAPED_UNICODE), $officerId]
        );
    }

    public static function injure(int $officerId): void
    {
        if (GameConfig::int('crew.permadeath', 0) === 1 && mt_rand(1, 100) <= 35) {
            Database::run("UPDATE officers SET status = 'dead', assigned = 0 WHERE id = ?", [$officerId]);
            return;
        }
        Database::run(
            "UPDATE officers SET status = 'injured', ready_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s', time() + GameConfig::int('crew.injury_heal_hours', 6) * 3600), $officerId]
        );
    }

    /** @return array<string,mixed>|null */
    private static function own(array $player, int $officerId): ?array
    {
        return Database::first('SELECT * FROM officers WHERE id = ? AND player_id = ?', [$officerId, (int) $player['id']]);
    }
}
