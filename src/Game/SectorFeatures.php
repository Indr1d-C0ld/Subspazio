<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 9 — scansione & frontiera: feature nascoste dei settori (relitti,
 * depositi, anomalie, pericoli), spawnate dal tick per regione, rivelate
 * dalla scansione, sfruttate con azioni dedicate.
 */
final class SectorFeatures
{
    public const KIND_LABEL = [
        'wreck' => 'Relitto', 'cache' => 'Deposito', 'anomaly' => 'Anomalia', 'hazard' => 'Pericolo',
    ];
    public const HAZARD_LABEL = [
        'radiation' => 'Fascia di radiazioni', 'gravity' => 'Pozzo gravitazionale', 'ion_storm' => 'Tempesta ionica',
    ];

    // --- tick: spawn / scadenze -----------------------------------------

    public static function tick(): array
    {
        $out = ['spawned' => 0, 'expired' => 0];
        try {
            $out['expired'] = Database::run(
                'UPDATE sector_features SET depleted = 1 WHERE depleted = 0 AND expires_at IS NOT NULL AND expires_at <= NOW()'
            )->rowCount();
            Database::run("DELETE FROM sector_features WHERE depleted = 1 AND spawned_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");

            $ttl = GameConfig::int('scan.feature_ttl_hours', 48);
            foreach (['frontier', 'deep'] as $rk) {
                foreach (['wreck', 'cache', 'anomaly', 'hazard'] as $kind) {
                    $target = GameConfig::int("scan.{$kind}_target_{$rk}", 0);
                    if ($target <= 0) {
                        continue;
                    }
                    $have = (int) (Database::first(
                        "SELECT COUNT(*) c FROM sector_features sf
                         JOIN sectors s ON s.id = sf.sector_id
                         JOIN regions r ON r.id = s.region_id
                         WHERE sf.depleted = 0 AND sf.kind = ? AND r.kind = ?",
                        [$kind, $rk]
                    )['c'] ?? 0);
                    $need = min(3, $target - $have);
                    for ($i = 0; $i < $need; $i++) {
                        $sec = Database::first(
                            "SELECT s.id FROM sectors s JOIN regions r ON r.id = s.region_id
                             WHERE r.kind = ? AND s.is_fedspace = 0
                               AND NOT EXISTS (SELECT 1 FROM sector_features f WHERE f.sector_id = s.id AND f.depleted = 0 AND f.kind = ?)
                             ORDER BY RAND() LIMIT 1",
                            [$rk, $kind]
                        );
                        if ($sec === null) {
                            break;
                        }
                        self::spawn((int) $sec['id'], $kind, $rk, $ttl);
                        $out['spawned']++;
                    }
                }
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
    }

    private static function spawn(int $sectorId, string $kind, string $regionKind, int $ttlHours): void
    {
        $deep = $regionKind === 'deep';
        $rich = $deep ? mt_rand(2, 5) : mt_rand(1, 3);
        [$subtype, $data, $expires] = match ($kind) {
            'wreck'   => [['nave', 'stazione', 'ferrengi', 'corsaro'][array_rand(['nave', 'stazione', 'ferrengi', 'corsaro'])], null, null],
            'cache'   => [['minerale', 'equipaggiamento', 'organico', 'misto'][array_rand(['minerale', 'equipaggiamento', 'organico', 'misto'])], null, null],
            'anomaly' => [['gravitazionale', 'spaziale', 'temporale'][array_rand(['gravitazionale', 'spaziale', 'temporale'])], ['need' => $rich * 55], null],
            'hazard'  => (function () use ($deep) {
                $st = $deep
                    ? ['radiation', 'gravity', 'ion_storm'][array_rand(['radiation', 'gravity', 'ion_storm'])]
                    : ['radiation', 'ion_storm'][array_rand(['radiation', 'ion_storm'])];
                $exp = $st === 'ion_storm' ? date('Y-m-d H:i:s', time() + mt_rand(2, 6) * 3600) : null;
                return [$st, null, $exp];
            })(),
            default   => ['', null, null],
        };
        if ($kind !== 'hazard' && $expires === null) {
            $expires = date('Y-m-d H:i:s', time() + $ttlHours * 3600);
        }
        Database::run(
            'INSERT INTO sector_features (sector_id, kind, subtype, richness, data, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$sectorId, $kind, $subtype, $rich, $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null, $expires]
        );
    }

    // --- lettura ------------------------------------------------------

    public static function regionKind(int $sectorId): string
    {
        return (string) (Database::first(
            'SELECT r.kind FROM sectors s LEFT JOIN regions r ON r.id = s.region_id WHERE s.id = ?',
            [$sectorId]
        )['kind'] ?? 'core');
    }

    /** @return list<array<string,mixed>> feature del settore già scoperte dal giocatore */
    public static function visibleFor(int $playerId, int $sectorId): array
    {
        try {
            $rows = Database::all(
                'SELECT sf.*, pfs.progress, pfs.resolved
                 FROM sector_features sf
                 JOIN player_feature_state pfs ON pfs.feature_id = sf.id AND pfs.player_id = ?
                 WHERE sf.sector_id = ? AND sf.depleted = 0
                 ORDER BY FIELD(sf.kind,\'hazard\',\'wreck\',\'cache\',\'anomaly\'), sf.id',
                [$playerId, $sectorId]
            );
        } catch (\Throwable) {
            return [];
        }
        return array_map(static function ($r) {
            $d = $r['data'] ? json_decode((string) $r['data'], true) : [];
            $need = (int) ($d['need'] ?? 0);
            return [
                'id'       => (int) $r['id'],
                'kind'     => $r['kind'],
                'subtype'  => $r['subtype'],
                'richness' => (int) $r['richness'],
                'label'    => self::KIND_LABEL[$r['kind']] ?? $r['kind'],
                'progress' => (int) ($r['progress'] ?? 0),
                'need'     => $need,
                'resolved' => (bool) $r['resolved'],
                'hazard_label' => $r['kind'] === 'hazard' ? (self::HAZARD_LABEL[$r['subtype']] ?? $r['subtype']) : null,
            ];
        }, $rows);
    }

    public static function scanRange(array $ship): int
    {
        $r = ($ship['dev_scanner'] ?? 'none') !== 'none' ? 1 : 0;
        $r += (int) ($ship['crew_scan_range'] ?? 0);
        $r += (int) (($ship['mod_effects']['scan_range'] ?? 0));
        return max(0, $r);
    }

    // --- scansione / sonda ------------------------------------------

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function scan(array $player, array $ship): array
    {
        $cost = GameConfig::int('scan.turn_cost', 8);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti per una scansione (servono {$cost})."];
        }
        $range = self::scanRange($ship);
        $sectors = self::bfs((int) $player['sector_id'], $range);

        $in = implode(',', array_fill(0, count($sectors), '?'));
        $feats = Database::all("SELECT id, sector_id, kind, subtype, richness FROM sector_features WHERE depleted = 0 AND sector_id IN ($in)", $sectors);

        $found = 0;
        $byKind = [];
        foreach ($feats as $f) {
            $n = Database::run(
                'INSERT IGNORE INTO player_feature_state (player_id, feature_id) VALUES (?, ?)',
                [(int) $player['id'], (int) $f['id']]
            )->rowCount();
            if ($n > 0) {
                $found++;
                $byKind[$f['kind']] = ($byKind[$f['kind']] ?? 0) + 1;
                self::codexForFeature((int) $player['id'], $f);
            }
        }
        Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$cost, (int) $player['id']]);
        Codex::unlock((int) $player['id'], 'scan_basics');
        if (self::regionKind((int) $player['sector_id']) === 'deep') {
            Codex::unlock((int) $player['id'], 'deep_space');
        }

        return ['ok' => true, 'found' => $found, 'range' => $range, 'sectors' => count($sectors), 'by_kind' => $byKind];
    }

    public static function probe(array $player, array $ship, int $targetSector): array
    {
        if ((int) ($ship['probes'] ?? 0) <= 0) {
            return ['ok' => false, 'error' => 'Nessuna sonda a bordo (compra dal Cantiere).'];
        }
        if (!in_array($targetSector, Universe::warpsFrom((int) $player['sector_id']), true)) {
            return ['ok' => false, 'error' => 'La sonda raggiunge solo un settore adiacente.'];
        }
        $cost = GameConfig::int('scan.probe_turn_cost', 2);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost})."];
        }
        $feats = Database::all('SELECT id, sector_id, kind, subtype, richness FROM sector_features WHERE depleted = 0 AND sector_id = ?', [$targetSector]);
        $found = 0;
        foreach ($feats as $f) {
            $n = Database::run('INSERT IGNORE INTO player_feature_state (player_id, feature_id) VALUES (?, ?)', [(int) $player['id'], (int) $f['id']])->rowCount();
            if ($n > 0) {
                $found++;
                self::codexForFeature((int) $player['id'], $f);
            }
        }
        Database::run('UPDATE ships SET probes = probes - 1 WHERE id = ?', [(int) $ship['id']]);
        Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$cost, (int) $player['id']]);
        Database::run('INSERT IGNORE INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)', [(int) $player['id'], $targetSector]);
        return ['ok' => true, 'found' => $found, 'sector' => $targetSector];
    }

    // --- sfruttamento -----------------------------------------------

    private static function owned(int $playerId, int $featureId, string $kind): ?array
    {
        return Database::first(
            "SELECT sf.*, pfs.progress, pfs.resolved
             FROM sector_features sf
             JOIN player_feature_state pfs ON pfs.feature_id = sf.id AND pfs.player_id = ?
             WHERE sf.id = ? AND sf.kind = ? AND sf.depleted = 0",
            [$playerId, $featureId, $kind]
        );
    }

    public static function salvage(array $player, array $ship, int $featureId): array
    {
        $f = self::owned((int) $player['id'], $featureId, 'wreck');
        if ($f === null) {
            return ['ok' => false, 'error' => 'Nessun relitto scansionato con quell\'id qui.'];
        }
        if ((int) $f['sector_id'] !== (int) $player['sector_id']) {
            return ['ok' => false, 'error' => 'Devi essere nel settore del relitto.'];
        }
        $cost = GameConfig::int('scan.salvage_turn_cost', 6);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost})."];
        }
        $deep = self::regionKind((int) $f['sector_id']) === 'deep';
        $mult = $deep ? GameConfig::float('scan.deep_mult', 1.8) : 1.0;
        $sal = (int) round(GameConfig::int('scan.salvage_base', 45) * (int) $f['richness'] * $mult * (0.75 + mt_rand() / mt_getrandmax() * 0.6));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $parts = ["+{$sal} Leghe"];
        $module = null;
        $officer = null;
        try {
            Database::run('UPDATE players SET turns = turns - ?, salvage = salvage + ? WHERE id = ?', [$cost, $sal, (int) $player['id']]);

            $modPct = $deep ? GameConfig::int('scan.wreck_module_deep_pct', 60) : GameConfig::int('scan.wreck_module_pct', 35);
            if (mt_rand(1, 100) <= $modPct + (int) $f['richness'] * 3) {
                $module = Loot::grant((int) $player['id'], 'wreck', $deep);
                if ($module !== null) {
                    $parts[] = "modulo: {$module['name']} [{$module['label']}]";
                    if ($module['rarity'] === 'precursor') {
                        Codex::unlock((int) $player['id'], 'precursor_tech');
                    }
                }
            }
            if (mt_rand(1, 100) <= GameConfig::int('scan.wreck_officer_pct', 12)) {
                $role = Crew::ROLES[array_rand(Crew::ROLES)];
                $lvl = mt_rand(1, $deep ? 4 : 2);
                $arch = Database::first('SELECT ckey, weights FROM officer_archetypes WHERE role = ? ORDER BY RAND() LIMIT 1', [$role]);
                $name = ['Ash', 'Wren', 'Corin', 'Vess', 'Dain', 'Prya', 'Lex', 'Soren'][array_rand(range(0, 7))] . ' '
                    . ['Marr', 'Osei', 'Blane', 'Toma', 'Reyes', 'Vane', 'Sund', 'Cai'][array_rand(range(0, 7))];
                Database::run(
                    "INSERT INTO officers (player_id, name, role, archetype, level, skills, assigned, status, origin)
                     VALUES (?, ?, ?, ?, ?, ?, 0, 'injured', 'wreck')",
                    [(int) $player['id'], $name, $role, $arch['ckey'] ?? null, $lvl,
                     json_encode(Crew::rollSkills($role, $lvl, ShipStats::decode($arch['weights'] ?? null)), JSON_UNESCAPED_UNICODE)]
                );
                $officer = $name;
                $parts[] = "sopravvissuto recuperato: {$name} (" . Crew::roleLabel($role) . ", ferito)";
            }

            Database::run('UPDATE sector_features SET depleted = 1 WHERE id = ?', [$featureId]);
            Codex::unlock((int) $player['id'], 'wreck_generic');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'text' => implode(' · ', $parts), 'module' => $module, 'officer' => $officer, 'salvage' => $sal];
    }

    public static function harvest(array $player, array $ship, int $featureId): array
    {
        $f = self::owned((int) $player['id'], $featureId, 'cache');
        if ($f === null || (int) $f['sector_id'] !== (int) $player['sector_id']) {
            return ['ok' => false, 'error' => 'Nessun deposito scansionato con quell\'id qui.'];
        }
        $cost = GameConfig::int('scan.harvest_turn_cost', 4);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost})."];
        }
        $deep = self::regionKind((int) $f['sector_id']) === 'deep';
        $mult = $deep ? GameConfig::float('scan.deep_mult', 1.8) : 1.0;
        $cr = (int) round(GameConfig::int('scan.cache_credits_base', 600) * (int) $f['richness'] * $mult * (0.7 + mt_rand() / mt_getrandmax() * 0.7));
        $sal = (int) round(GameConfig::int('scan.salvage_base', 45) * (int) $f['richness'] * 0.4 * $mult);

        [$col, $commodity] = match ($f['subtype']) {
            'equipaggiamento' => ['hold_equipment', 'equipment'],
            'organico'        => ['hold_organics', 'organics'],
            default           => ['hold_ore', 'ore'],
        };
        $fresh = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        $room = (int) ($ship['holds_total'] ?? 0) - Economy::holdsUsed($fresh);
        $cargo = max(0, min($room, (int) $f['richness'] * mt_rand(3, 8)));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET turns = turns - ?, credits = credits + ?, salvage = salvage + ? WHERE id = ?',
                [$cost, $cr, $sal, (int) $player['id']]);
            if ($cargo > 0) {
                Database::run("UPDATE ships SET {$col} = {$col} + ? WHERE id = ?", [$cargo, (int) $ship['id']]);
            }
            Database::run('UPDATE sector_features SET depleted = 1 WHERE id = ?', [$featureId]);
            Codex::unlock((int) $player['id'], 'cache_generic');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $parts = [number_format($cr, 0, ',', '.') . ' cr', "+{$sal} Leghe"];
        if ($cargo > 0) {
            $parts[] = "{$cargo} " . Economy::label($commodity);
        }
        return ['ok' => true, 'text' => implode(' · ', $parts)];
    }

    public static function study(array $player, array $ship, int $featureId): array
    {
        $f = self::owned((int) $player['id'], $featureId, 'anomaly');
        if ($f === null || (int) $f['sector_id'] !== (int) $player['sector_id']) {
            return ['ok' => false, 'error' => 'Nessuna anomalia scansionata con quell\'id qui.'];
        }
        if ((int) $f['resolved'] === 1) {
            return ['ok' => false, 'error' => 'Anomalia già risolta.'];
        }
        $cost = GameConfig::int('scan.study_turn_cost', 5);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost})."];
        }
        $d = $f['data'] ? json_decode((string) $f['data'], true) : [];
        $need = (int) ($d['need'] ?? ((int) $f['richness'] * 55));
        $hasSci = (int) (Database::first(
            "SELECT COUNT(*) c FROM officers WHERE player_id = ? AND assigned = 1 AND status = 'active' AND role = 'scientist'",
            [(int) $player['id']]
        )['c'] ?? 0) > 0;
        $inc = (int) round(GameConfig::int('scan.anomaly_progress_base', 30)
            * ($hasSci ? GameConfig::float('scan.anomaly_science_bonus', 2.0) : 1.0)
            * (0.8 + mt_rand() / mt_getrandmax() * 0.4));
        $prog = (int) $f['progress'] + $inc;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$cost, (int) $player['id']]);
            if ($prog < $need) {
                Database::run('UPDATE player_feature_state SET progress = ? WHERE player_id = ? AND feature_id = ?',
                    [$prog, (int) $player['id'], $featureId]);
                $pdo->commit();
                return ['ok' => true, 'done' => false, 'text' => "Analisi in corso: {$prog}/{$need}" . ($hasSci ? ' (Scienziato: +bonus)' : '')];
            }
            // risolta
            Database::run('UPDATE player_feature_state SET progress = ?, resolved = 1 WHERE player_id = ? AND feature_id = ?',
                [$need, (int) $player['id'], $featureId]);
            Database::run('UPDATE sector_features SET depleted = 1 WHERE id = ?', [$featureId]);
            $deep = self::regionKind((int) $f['sector_id']) === 'deep';
            $cr = (int) round(1200 * (int) $f['richness'] * ($deep ? 1.6 : 1.0));
            Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [$cr, (int) $player['id']]);
            Crew::awardKillXp((int) $player['id'], 30 * (int) $f['richness']);
            $module = Loot::grant((int) $player['id'], 'anomaly', $deep, 'exp');
            Codex::unlock((int) $player['id'], 'anomaly_generic');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $parts = [number_format($cr, 0, ',', '.') . ' cr', '+XP equipaggio'];
        if (!empty($module)) {
            $parts[] = "modulo: {$module['name']} [{$module['label']}]";
        }
        return ['ok' => true, 'done' => true, 'text' => 'Anomalia risolta! ' . implode(' · ', $parts)];
    }

    // --- hazard all'ingresso (chiamata da Combat::onEnterSector) ---------

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{events:list<string>, ship:array<string,mixed>}
     */
    public static function entryHazards(array $player, array $ship): array
    {
        $events = [];
        try {
            $rows = Database::all(
                "SELECT sf.*, (pfs.feature_id IS NOT NULL) AS known
                 FROM sector_features sf
                 LEFT JOIN player_feature_state pfs ON pfs.feature_id = sf.id AND pfs.player_id = ?
                 WHERE sf.sector_id = ? AND sf.kind = 'hazard' AND sf.depleted = 0",
                [(int) $player['id'], (int) $player['sector_id']]
            );
        } catch (\Throwable) {
            return ['events' => [], 'ship' => $ship];
        }
        $mit = GameConfig::float('scan.hazard_known_mitigation', 0.5);
        foreach ($rows as $h) {
            $k = (float) ($h['known'] ? $mit : 1.0);
            $lab = self::HAZARD_LABEL[$h['subtype']] ?? $h['subtype'];
            if ($h['subtype'] === 'radiation') {
                $drain = (int) round((int) $ship['shields'] * GameConfig::float('scan.hazard_radiation_drain', 0.35) * $k);
                if ($drain > 0) {
                    Database::run('UPDATE ships SET shields = GREATEST(0, shields - ?) WHERE id = ?', [$drain, (int) $ship['id']]);
                    $ship['shields'] = max(0, (int) $ship['shields'] - $drain);
                }
                $events[] = "{$lab}: -{$drain} scudi" . ($h['known'] ? ' (rotta ottimizzata)' : ' — non era segnalata!');
            } elseif ($h['subtype'] === 'ion_storm') {
                $loss = (int) round((int) $ship['fighters'] * GameConfig::float('scan.hazard_ion_fighter_frac', 0.15) * $k);
                $loss = min($loss, (int) floor((int) $ship['fighters'] * 0.4));
                if ($loss > 0) {
                    Database::run('UPDATE ships SET fighters = GREATEST(0, fighters - ?) WHERE id = ?', [$loss, (int) $ship['id']]);
                    $ship['fighters'] = max(0, (int) $ship['fighters'] - $loss);
                }
                $events[] = "{$lab}: -{$loss} caccia" . ($h['known'] ? '' : ' — non era segnalata!');
            }
            // gravity: gestito in Navigation::move (costo turni), qui solo nota
            elseif ($h['subtype'] === 'gravity') {
                $events[] = "{$lab}: i motori faticano in questo settore.";
            }
            Database::run('INSERT IGNORE INTO player_feature_state (player_id, feature_id) VALUES (?, ?)', [(int) $player['id'], (int) $h['id']]);
            Codex::unlock((int) $player['id'], 'hazard_' . ($h['subtype'] === 'ion_storm' ? 'ion' : $h['subtype']));
        }
        return ['events' => $events, 'ship' => $ship];
    }

    /** costo turni extra da pozzo gravitazionale sul settore di destinazione */
    public static function gravityTurnPenalty(int $playerId, int $toSector): int
    {
        try {
            $h = Database::first(
                "SELECT sf.id, (pfs.feature_id IS NOT NULL) AS known FROM sector_features sf
                 LEFT JOIN player_feature_state pfs ON pfs.feature_id = sf.id AND pfs.player_id = ?
                 WHERE sf.sector_id = ? AND sf.kind = 'hazard' AND sf.subtype = 'gravity' AND sf.depleted = 0 LIMIT 1",
                [$playerId, $toSector]
            );
        } catch (\Throwable) {
            return 0;
        }
        if ($h === null) {
            return 0;
        }
        $base = GameConfig::int('scan.hazard_gravity_turns', 1);
        return $h['known'] ? (int) ceil($base * GameConfig::float('scan.hazard_known_mitigation', 0.5)) : $base;
    }

    // --- interni --------------------------------------------------

    /** @return list<int> settori entro $depth salti dal settore dato (incluso) */
    private static function bfs(int $start, int $depth): array
    {
        $seen = [$start => true];
        $frontier = [$start];
        for ($d = 0; $d < $depth; $d++) {
            $next = [];
            foreach ($frontier as $s) {
                foreach (Universe::warpsFrom($s) as $to) {
                    if (!isset($seen[$to])) {
                        $seen[$to] = true;
                        $next[] = $to;
                    }
                }
            }
            $frontier = $next;
            if ($frontier === []) {
                break;
            }
        }
        return array_keys($seen);
    }

    private static function codexForFeature(int $playerId, array $f): void
    {
        Codex::unlock($playerId, match ($f['kind']) {
            'wreck'   => 'wreck_generic',
            'cache'   => 'cache_generic',
            'anomaly' => 'anomaly_generic',
            'hazard'  => 'hazard_' . ($f['subtype'] === 'ion_storm' ? 'ion' : $f['subtype']),
            default   => 'scan_basics',
        });
    }
}
