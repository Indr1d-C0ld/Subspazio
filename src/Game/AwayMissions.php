<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 8 — missioni away: pool per giocatore, risoluzione istantanea a
 * skill-check con esiti ramificati (trionfo / successo / parziale /
 * fallimento / disastro).
 */
final class AwayMissions
{
    private const KINDS = [
        'salvage'  => ['Recupero da relitto', ['engineering', 'combat'], ['salvage' => 1.6, 'module' => 40, 'credits' => 0.8]],
        'anomaly'  => ['Indagine su un\'anomalia', ['science', 'engineering'], ['module' => 55, 'xp' => 1.5, 'credits' => 0.6]],
        'contact'  => ['Contatto con una colonia', ['diplomacy', 'science'], ['credits' => 1.5, 'officer' => 12, 'xp' => 1.2]],
        'rescue'   => ['Missione di soccorso', ['medicine', 'piloting'], ['officer' => 30, 'credits' => 1.0, 'xp' => 1.3]],
        'recon'    => ['Ricognizione avanzata', ['piloting', 'science'], ['credits' => 1.0, 'salvage' => 1.0, 'xp' => 1.4]],
        'patrol'   => ['Pattugliamento della rotta', ['combat', 'piloting'], ['credits' => 1.3, 'salvage' => 0.8, 'xp' => 1.1]],
    ];

    public const OUTCOME_LABEL = [
        'triumph'  => 'Trionfo', 'success' => 'Successo', 'partial' => 'Successo parziale',
        'failure'  => 'Fallimento', 'disaster' => 'Disastro',
    ];

    // --- pool -----------------------------------------------------------

    public static function topUp(int $playerId, int $sectorId): void
    {
        Database::run("UPDATE away_missions SET status = 'done' WHERE player_id = ? AND status = 'open' AND expires_at <= NOW()", [$playerId]);
        $want = GameConfig::int('crew.mission_pool_size', 4);
        $have = (int) (Database::first("SELECT COUNT(*) c FROM away_missions WHERE player_id = ? AND status = 'open'", [$playerId])['c'] ?? 0);

        $regionKind = (string) (Database::first(
            'SELECT r.kind FROM sectors s LEFT JOIN regions r ON r.id = s.region_id WHERE s.id = ?',
            [$sectorId]
        )['kind'] ?? 'core');
        $diffBase = match ($regionKind) { 'deep' => 16, 'frontier' => 11, default => 7 };

        for ($i = $have; $i < $want; $i++) {
            $kind = array_rand(self::KINDS);
            [$title, $skillKeys, $rw] = self::KINDS[$kind];
            $diff = $diffBase + mt_rand(0, 8);
            $skills = [];
            foreach (array_slice($skillKeys, 0, mt_rand(1, 2)) as $sk) {
                $skills[$sk] = $diff + mt_rand(-2, 4);
            }
            $turnCost = mt_rand(GameConfig::int('crew.mission_turn_min', 20), GameConfig::int('crew.mission_turn_max', 55));
            $rewards = [
                'credits' => (int) round(($rw['credits'] ?? 0) * $diff * mt_rand(60, 140)),
                'salvage' => (int) round(($rw['salvage'] ?? 0) * $diff * mt_rand(2, 6)),
                'xp'      => (int) round(($rw['xp'] ?? 1.0) * (20 + $diff * 3)),
                'module_pct' => (int) ($rw['module'] ?? 0),
                'officer_pct' => (int) ($rw['officer'] ?? 0),
                'faction' => ['contact' => 'fed', 'patrol' => 'hegemony', 'salvage' => 'frontier',
                              'recon' => 'frontier', 'rescue' => 'fed'][$kind] ?? null,
            ];
            $onSector = mt_rand(1, 100) <= 55;
            Database::run(
                'INSERT INTO away_missions (player_id, kind, title, descr, difficulty, skills, turn_cost, sector_id, rewards, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $playerId, $kind, $title, self::blurb($kind),
                    $diff, json_encode($skills, JSON_UNESCAPED_UNICODE), $turnCost,
                    $onSector ? $sectorId : null,
                    json_encode($rewards, JSON_UNESCAPED_UNICODE),
                    date('Y-m-d H:i:s', time() + GameConfig::int('crew.mission_expire_hours', 14) * 3600),
                ]
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public static function open(int $playerId, int $sectorId): array
    {
        self::topUp($playerId, $sectorId);
        return Database::all(
            "SELECT * FROM away_missions WHERE player_id = ? AND status = 'open' ORDER BY difficulty, id",
            [$playerId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function log(int $playerId, int $limit = 12): array
    {
        return Database::all('SELECT * FROM away_mission_log WHERE player_id = ? ORDER BY id DESC LIMIT ?', [$playerId, $limit]);
    }

    // --- risoluzione --------------------------------------------------

    /**
     * @param array<string,mixed> $player
     * @param list<int>           $officerIds
     */
    public static function run(array $player, int $missionId, array $officerIds): array
    {
        $m = Database::first("SELECT * FROM away_missions WHERE id = ? AND player_id = ? AND status = 'open'", [$missionId, (int) $player['id']]);
        if ($m === null) {
            return ['ok' => false, 'error' => 'Missione non disponibile.'];
        }
        if (strtotime((string) $m['expires_at']) <= time()) {
            Database::run("UPDATE away_missions SET status = 'done' WHERE id = ?", [$missionId]);
            return ['ok' => false, 'error' => 'Missione scaduta.'];
        }
        $officerIds = array_values(array_unique(array_filter(array_map('intval', $officerIds))));
        if (count($officerIds) < 1 || count($officerIds) > 3) {
            return ['ok' => false, 'error' => 'Scegli da 1 a 3 ufficiali.'];
        }
        $in = implode(',', array_fill(0, count($officerIds), '?'));
        $offs = Database::all(
            "SELECT * FROM officers WHERE id IN ($in) AND player_id = ? AND status = 'active'
             AND (ready_at IS NULL OR ready_at <= NOW())",
            [...$officerIds, (int) $player['id']]
        );
        if (count($offs) !== count($officerIds)) {
            return ['ok' => false, 'error' => 'Uno o più ufficiali non sono disponibili (feriti, in missione o inesistenti).'];
        }

        $player = TurnManager::sync($player);
        $turnCost = (int) $m['turn_cost'];
        if ((int) $player['turns'] < $turnCost) {
            return ['ok' => false, 'error' => "Turni insufficienti: servono {$turnCost}."];
        }

        $need = ShipStats::decode($m['skills']) ?? [];
        $medBonus = 0;
        foreach ($offs as $o) {
            $sk = ShipStats::decode($o['skills']) ?? [];
            if ($o['role'] === 'medic') {
                $medBonus += (int) ($sk['medicine'] ?? 0) * 0.4;
            }
        }
        // margine = anello più debole + piccola quota media
        $margins = [];
        foreach ($need as $skill => $threshold) {
            $team = 0;
            foreach ($offs as $o) {
                $sk = ShipStats::decode($o['skills']) ?? [];
                $team += (int) ($sk[$skill] ?? 0) + (int) $o['level'] * 1.2;
            }
            $team += $medBonus + self::jitter();
            $margins[] = $team - $threshold;
        }
        $margin = (int) round(min($margins) * 0.8 + (array_sum($margins) / max(1, count($margins))) * 0.2);

        $outcome = match (true) {
            $margin >= 10 => 'triumph',
            $margin >= 2  => 'success',
            $margin >= -6 => 'partial',
            $margin >= -16 => 'failure',
            default => 'disaster',
        };

        $rw = ShipStats::decode($m['rewards']) ?? [];
        $scale = ['triumph' => 1.5, 'success' => 1.0, 'partial' => 0.45, 'failure' => 0.0, 'disaster' => 0.0][$outcome];
        $parts = [];
        $credits = (int) round(($rw['credits'] ?? 0) * $scale);
        $salvage = (int) round(($rw['salvage'] ?? 0) * $scale);
        $xp = (int) round(($rw['xp'] ?? 0) * max(0.25, $scale));
        $gotModule = false;
        $gotOfficer = null;
        $injured = null;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$turnCost, (int) $player['id']]);

            if ($credits > 0) {
                Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [$credits, (int) $player['id']]);
                $parts[] = number_format($credits, 0, ',', '.') . ' cr';
            }
            if ($salvage > 0) {
                Database::run('UPDATE players SET salvage = salvage + ? WHERE id = ?', [$salvage, (int) $player['id']]);
                $parts[] = "+{$salvage} Leghe";
            }
            if (in_array($outcome, ['triumph', 'success'], true) && mt_rand(1, 100) <= (int) ($rw['module_pct'] ?? 0)) {
                $item = Database::first('SELECT ckey, name, rarity, effects FROM item_types ORDER BY RAND() LIMIT 1');
                if ($item !== null) {
                    Database::run(
                        "INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, ?, ?, 'mission')",
                        [(int) $player['id'], $item['ckey'], $item['effects']]
                    );
                    $gotModule = true;
                    $parts[] = "modulo: {$item['name']}";
                }
            }
            if (in_array($outcome, ['triumph', 'success'], true) && mt_rand(1, 100) <= (int) ($rw['officer_pct'] ?? 0)) {
                $role = Crew::ROLES[array_rand(Crew::ROLES)];
                $lvl = mt_rand(1, 3);
                $arch = Database::first('SELECT ckey, weights FROM officer_archetypes WHERE role = ? ORDER BY RAND() LIMIT 1', [$role]);
                $name = self::pickName();
                Database::run(
                    "INSERT INTO officers (player_id, name, role, archetype, level, skills, assigned, origin)
                     VALUES (?, ?, ?, ?, ?, ?, 0, 'mission')",
                    [(int) $player['id'], $name, $role, $arch['ckey'] ?? null, $lvl,
                     json_encode(Crew::rollSkills($role, $lvl, ShipStats::decode($arch['weights'] ?? null)), JSON_UNESCAPED_UNICODE)]
                );
                $gotOfficer = $name;
                $parts[] = "ufficiale recuperato: {$name} ({$role})";
            }

            if (in_array($outcome, ['triumph', 'success'], true) && !empty($rw['faction'])) {
                Faction::adjust((int) $player['id'], (string) $rw['faction'],
                    GameConfig::int('faction.mission_gain', 10), 'missione di fazione');
                $parts[] = 'reputazione +';
            }

            // XP ai partecipanti + lealtà
            $loyLvl = GameConfig::int('crew.loyalty_level', 4);
            foreach ($offs as $o) {
                Crew::grantXp((int) $o['id'], $o, max(1, $xp));
                Database::run(
                    'UPDATE officers SET ready_at = ? WHERE id = ?',
                    [date('Y-m-d H:i:s', time() + GameConfig::int('crew.mission_cooldown_min', 120) * 60), (int) $o['id']]
                );
                if (in_array($outcome, ['triumph', 'success'], true)
                    && (int) $o['level'] >= $loyLvl && (int) $o['loyalty_done'] === 0) {
                    $sk = ShipStats::decode($o['skills']) ?? [];
                    $sk[Crew::PRIMARY[$o['role']]] = (int) ($sk[Crew::PRIMARY[$o['role']]] ?? 5) + 3;
                    Database::run(
                        'UPDATE officers SET loyalty_done = 1, ability_tier = 2, skills = ? WHERE id = ?',
                        [json_encode($sk, JSON_UNESCAPED_UNICODE), (int) $o['id']]
                    );
                    $parts[] = "lealtà di {$o['name']} consolidata: abilità potenziata";
                }
            }

            if ($outcome === 'disaster') {
                $victim = $offs[array_rand($offs)];
                Crew::injure((int) $victim['id']);
                $injured = $victim['name'];
                $parts[] = "{$victim['name']} ferito";
                $dmg = mt_rand(1, (int) $m['difficulty']) * 40;
                Database::run('UPDATE ships SET shields = GREATEST(0, shields - ?) WHERE id = ?', [$dmg, (int) $player['ship_id']]);
            }

            Database::run("UPDATE away_missions SET status = 'done' WHERE id = ?", [$missionId]);
            $rewardText = $parts === [] ? 'nessuna ricompensa' : implode(' · ', $parts);
            Database::run(
                'INSERT INTO away_mission_log (player_id, mission_kind, title, officers, outcome, margin, reward_text)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $player['id'], $m['kind'], $m['title'],
                    json_encode(array_map(static fn ($o) => $o['name'], $offs), JSON_UNESCAPED_UNICODE),
                    $outcome, $margin, mb_substr($rewardText, 0, 255),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok' => true, 'outcome' => $outcome, 'label' => self::OUTCOME_LABEL[$outcome],
            'margin' => $margin, 'reward_text' => $parts === [] ? '' : implode(' · ', $parts),
            'module' => $gotModule, 'officer' => $gotOfficer, 'injured' => $injured,
        ];
    }

    private static function blurb(string $kind): string
    {
        return [
            'salvage' => 'Uno scafo alla deriva emette ancora energia. Qualcuno deve entrarci.',
            'anomaly' => 'Le letture non hanno senso. Serve una squadra e sangue freddo.',
            'contact' => 'Un insediamento isolato accetta di parlare. Con le persone giuste.',
            'rescue'  => 'C\'è chi aspetta soccorso, e il tempo non gioca a favore.',
            'recon'   => 'Un tratto di spazio da mappare prima che lo faccia qualcun altro.',
            'patrol'  => 'La rotta va tenuta pulita. A volte con le maniere forti.',
        ][$kind] ?? '';
    }

    private static function pickName(): string
    {
        $g = ['Ash', 'Wren', 'Corin', 'Vess', 'Dain', 'Prya', 'Lex', 'Soren', 'Adae', 'Rook'];
        $s = ['Marr', 'Osei', 'Blane', 'Toma', 'Reyes', 'Vane', 'Okafor', 'Sund', 'Petrov', 'Cai'];
        return $g[array_rand($g)] . ' ' . $s[array_rand($s)];
    }

    private static function jitter(): float
    {
        return ((mt_rand() / mt_getrandmax()) * 2 - 1) * 6;
    }
}
