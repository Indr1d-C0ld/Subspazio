<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * NPC: Ferrengi (alieni ostili con regione natale), pirati (predoni di
 * frontiera), mercanti (civili da depredare). Movimento, ingaggio e
 * respawn sono gestiti dal tick.
 */
final class Npc
{
    private const FERRENGI_NAMES = ['Grubnash', 'Vek Tarr', 'Ssora', 'Krul', 'Nix Ferro', 'Ombra di Cygnus', 'Draak', 'Vorlok'];
    private const PIRATE_NAMES   = ['Sciacallo', 'Lama Nera', 'Corvo', 'Randagio', 'Cicatrice', 'Fantasma', 'Avvoltoio'];
    private const TRADER_NAMES   = ['Mercuria', 'Buon Affare', 'Via della Seta', 'Peregrina', 'Fortuna', 'Rotta d\'Oro'];

    /** @return list<array<string,mixed>> */
    public static function inSector(int $sectorId): array
    {
        return array_map(static fn ($n) => [
            'id'       => (int) $n['id'],
            'kind'     => $n['kind'],
            'name'     => $n['name'],
            'ship'     => $n['ship_type'],
            'fighters' => (int) $n['fighters'],
            'hostile'  => (int) $n['aggression'] > 0,
        ], Database::all('SELECT * FROM npcs WHERE sector_id = ? ORDER BY id', [$sectorId]));
    }

    /** @return array<string,mixed>|null */
    public static function get(int $id): ?array
    {
        return Database::first('SELECT * FROM npcs WHERE id = ?', [$id]);
    }

    // --- tick -------------------------------------------------------

    /** @return array{moved:int, engaged:int, spawned:int, despawned:int} */
    public static function tick(): array
    {
        $moved = self::move();
        $engaged = self::engage();
        $spawned = self::spawn();
        $despawned = self::despawn();
        return compact('moved', 'engaged', 'spawned', 'despawned');
    }

    private static function move(): int
    {
        $interval = GameConfig::int('npc.move_interval_min', 3);
        $due = Database::all(
            'SELECT * FROM npcs WHERE last_move_at < DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 200',
            [$interval]
        );
        $n = 0;
        foreach ($due as $npc) {
            $adj = Universe::warpsFrom((int) $npc['sector_id']);
            if ($adj === []) {
                continue;
            }
            // Ferrengi evitano la Fedspace; i mercanti preferiscono i settori con porto
            $pick = $adj[array_rand($adj)];
            if ($npc['kind'] === 'ferrengi') {
                $safe = array_values(array_filter($adj, static fn ($s) => !(bool) (Universe::sector($s)['is_fedspace'] ?? 0)));
                if ($safe !== []) {
                    $pick = $safe[array_rand($safe)];
                }
            } elseif ($npc['kind'] === 'trader') {
                $ports = array_values(array_filter($adj, static fn ($s) => (int) (Database::first('SELECT has_port FROM sectors WHERE id = ?', [$s])['has_port'] ?? 0) === 1));
                if ($ports !== [] && mt_rand(0, 1)) {
                    $pick = $ports[array_rand($ports)];
                }
            }
            Database::run('UPDATE npcs SET sector_id = ?, last_move_at = NOW() WHERE id = ?', [$pick, $npc['id']]);
            $n++;
        }
        return $n;
    }

    private static function engage(): int
    {
        $chance = GameConfig::int('npc.engage_chance_pct', 65);
        $rows = Database::all(
            "SELECT n.*, p.id AS player_id FROM npcs n
             JOIN players p ON p.sector_id = n.sector_id
             JOIN sectors s ON s.id = n.sector_id
             WHERE n.aggression > 0 AND s.is_fedspace = 0"
        );
        $seen = [];
        $n = 0;
        foreach ($rows as $r) {
            if (isset($seen[$r['id']])) {
                continue; // un ingaggio per NPC per tick
            }
            if (mt_rand(1, 100) > $chance) {
                continue;
            }
            $player = Database::first('SELECT * FROM players WHERE id = ?', [$r['player_id']]);
            $ship = PlayerService::ship((int) $player['ship_id']);
            if ($ship === null || $ship['type_key'] === 'escape_pod') {
                continue;
            }
            // i Ferrengi puntano soprattutto i non-malvagi
            if ($r['kind'] === 'ferrengi' && Ranks::isEvil((int) $player['alignment']) && mt_rand(0, 1)) {
                continue;
            }
            Combat::npcEngagePlayer($r, $player, $ship, true);
            $seen[$r['id']] = true;
            $n++;
        }
        return $n;
    }

    private static function spawn(): int
    {
        $perTick = GameConfig::int('npc.spawn_per_tick', 4);
        $n = 0;
        foreach ([
            ['ferrengi', GameConfig::int('npc.ferrengi_target', 40)],
            ['pirate', GameConfig::int('npc.pirate_target', 25)],
            ['trader', GameConfig::int('npc.trader_target', 30)],
        ] as [$kind, $target]) {
            $have = (int) (Database::first('SELECT COUNT(*) c FROM npcs WHERE kind = ?', [$kind])['c'] ?? 0);
            $deficit = min($perTick, max(0, $target - $have));
            for ($i = 0; $i < $deficit; $i++) {
                self::spawnOne($kind);
                $n++;
            }
        }
        return $n;
    }

    public static function spawnOne(string $kind): void
    {
        [$sector, $home] = self::spawnSector($kind);
        if ($sector === null) {
            return;
        }
        [$name, $type, $ftr, $shd, $rating, $creds, $aggr] = match ($kind) {
            'ferrengi' => [
                'Ferrengi ' . self::FERRENGI_NAMES[array_rand(self::FERRENGI_NAMES)],
                mt_rand(0, 1) ? 'havoc_gunstar' : 'missile_frigate',
                mt_rand(2500, 9000), mt_rand(400, 1600), 1.6 + mt_rand(0, 60) / 100,
                mt_rand(20000, 120000), 100,
            ],
            'pirate' => [
                'Predone ' . self::PIRATE_NAMES[array_rand(self::PIRATE_NAMES)],
                mt_rand(0, 1) ? 'scout_marauder' : 'missile_frigate',
                mt_rand(600, 3000), mt_rand(100, 500), 1.0 + mt_rand(0, 50) / 100,
                mt_rand(3000, 30000), 100,
            ],
            default => [
                'Mercantile ' . self::TRADER_NAMES[array_rand(self::TRADER_NAMES)],
                mt_rand(0, 1) ? 'merchant_freighter' : 'cargo_transport',
                mt_rand(50, 700), mt_rand(50, 400), 0.6,
                mt_rand(5000, 60000), 0,
            ],
        };

        Database::run(
            'INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, fighters, shields, combat_rating, credits, cargo_ore, cargo_org, cargo_equ, aggression)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $kind, $name, $type, $sector, $home, $ftr, $shd, $rating, $creds,
                $kind === 'trader' ? mt_rand(0, 400) : mt_rand(0, 60),
                $kind === 'trader' ? mt_rand(0, 400) : mt_rand(0, 60),
                $kind === 'trader' ? mt_rand(0, 400) : mt_rand(0, 60),
                $aggr,
            ]
        );
    }

    /** @return array{0:?int,1:?int} settore di spawn, settore natale */
    private static function spawnSector(string $kind): array
    {
        if ($kind === 'ferrengi') {
            $region = GameConfig::str('npc.ferrengi_home_region', 'Abisso di Cygnus');
            $row = Database::first(
                'SELECT s.id FROM sectors s JOIN regions r ON r.id = s.region_id
                 WHERE r.name = ? AND s.is_fedspace = 0 ORDER BY RAND() LIMIT 1',
                [$region]
            );
            $home = $row ? (int) $row['id'] : null;
            $near = Database::first(
                'SELECT s.id FROM sectors s JOIN regions r ON r.id = s.region_id
                 WHERE r.name = ? AND s.is_fedspace = 0 ORDER BY RAND() LIMIT 1',
                [$region]
            );
            return [$near ? (int) $near['id'] : $home, $home];
        }
        $row = Database::first(
            "SELECT s.id FROM sectors s JOIN regions r ON r.id = s.region_id
             WHERE s.is_fedspace = 0 AND r.kind IN ('frontier','deep') ORDER BY RAND() LIMIT 1"
        );
        return [$row ? (int) $row['id'] : null, null];
    }

    private static function despawn(): int
    {
        // NPC finiti in Fedspace (Ferrengi/pirati) o troppo vecchi e inerti
        return Database::run(
            "DELETE n FROM npcs n JOIN sectors s ON s.id = n.sector_id
             WHERE (n.kind IN ('ferrengi','pirate') AND s.is_fedspace = 1)
                OR (n.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))"
        )->rowCount();
    }

    public static function remove(int $id): void
    {
        Database::run('DELETE FROM npcs WHERE id = ?', [$id]);
    }
}
