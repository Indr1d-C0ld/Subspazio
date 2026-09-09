<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Mine Limpet (Tema B roadmap): non fanno danno — si agganciano allo scafo di
 * chi entra nel settore e permettono al proprietario di seguirne la posizione
 * (in tempo reale, l'occultamento non aiuta) finche':
 *   - non scadono (limpet.ttl_hours), oppure
 *   - la preda raggiunge lo StarDock, dove i tecnici le rimuovono tutte.
 *
 * Schema preesistente (0007): ship_limpets(ship_id, owner_player_id, attached_at),
 * sector_mines.type='limpet', ships.mines_limpet. Il dispiegamento passa gia' da
 * Deploy::deployMines (INSERT in sector_mines); qui c'e' l'aggancio e il tracking.
 */
final class Limpet
{
    public static function ttlHours(): int
    {
        return max(1, GameConfig::int('limpet.ttl_hours', 12));
    }

    public static function maxTracked(): int
    {
        return max(1, GameConfig::int('limpet.max_tracked', 15));
    }

    public static function fieldCap(): int
    {
        return max(1, GameConfig::int('limpet.field_cap', 50));
    }

    /**
     * Ingresso in un settore: ogni campo di Limpet non amico aggancia una mina
     * allo scafo (una per proprietario, non ne consuma una seconda se gia'
     * agganciata). Non infligge danno.
     *
     * @param callable(int):bool $isFriendly  true se il proprietario del campo e' alleato di chi entra
     * @return list<string> righe-evento per chi entra
     */
    public static function onEnter(int $entrantPlayerId, int $shipId, int $sectorId, callable $isFriendly): array
    {
        $events = [];

        $fields = Database::all(
            "SELECT sm.id, sm.owner_player_id, sm.qty, p.handle
             FROM sector_mines sm JOIN players p ON p.id = sm.owner_player_id
             WHERE sm.sector_id = ? AND sm.type = 'limpet' AND sm.qty > 0",
            [$sectorId]
        );
        if ($fields === []) {
            return [];
        }

        $entrant = Database::first('SELECT handle FROM players WHERE id = ?', [$entrantPlayerId]);
        $entrantHandle = (string) ($entrant['handle'] ?? ('#' . $entrantPlayerId));

        foreach ($fields as $f) {
            $ownerId = (int) $f['owner_player_id'];
            if ($ownerId === $entrantPlayerId || $isFriendly($ownerId)) {
                continue;
            }

            // gia' agganciata da questo proprietario: non consuma un'altra mina
            $already = Database::first(
                'SELECT 1 AS x FROM ship_limpets WHERE ship_id = ? AND owner_player_id = ?',
                [$shipId, $ownerId]
            );
            if ($already !== null) {
                continue;
            }

            // tetto prede per proprietario: se pieno, stacca la piu' vecchia
            $tracking = (int) (Database::first(
                'SELECT COUNT(*) AS n FROM ship_limpets WHERE owner_player_id = ?',
                [$ownerId]
            )['n'] ?? 0);
            if ($tracking >= self::maxTracked()) {
                $oldest = Database::first(
                    'SELECT ship_id FROM ship_limpets WHERE owner_player_id = ? ORDER BY attached_at ASC LIMIT 1',
                    [$ownerId]
                );
                if ($oldest !== null) {
                    Database::run(
                        'DELETE FROM ship_limpets WHERE owner_player_id = ? AND ship_id = ?',
                        [$ownerId, (int) $oldest['ship_id']]
                    );
                }
            }

            // consuma una mina dal campo
            if ((int) $f['qty'] <= 1) {
                Database::run('DELETE FROM sector_mines WHERE id = ?', [(int) $f['id']]);
            } else {
                Database::run('UPDATE sector_mines SET qty = qty - 1 WHERE id = ?', [(int) $f['id']]);
            }

            Database::run(
                'INSERT INTO ship_limpets (ship_id, owner_player_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE attached_at = NOW()',
                [$shipId, $ownerId]
            );

            $events[] = "Una mina Limpet di {$f['handle']} si aggancia allo scafo: sei tracciato finche' non raggiungi lo StarDock.";

            // il proprietario e' assente: giornale + avviso
            ShipLog::write(
                $ownerId,
                'system',
                'info',
                "Limpet agganciata: {$entrantHandle}",
                "Una tua mina Limpet si e' agganciata a {$entrantHandle} nel settore {$sectorId}. "
                    . 'Ne segui la posizione dalla plancia finche\' non raggiunge lo StarDock o la mina scade.',
                $sectorId,
                ['limpet_target' => $entrantPlayerId]
            );
            Live::alert(
                $ownerId,
                'limpet',
                'Limpet agganciata',
                "{$entrantHandle} e' ora tracciato (settore {$sectorId}).",
                '/gioco'
            );

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, defender_player_id, outcome, detail)
                 VALUES (?, ?, ?, ?, ?, ?)',
                ['mines', $sectorId, $ownerId, $entrantPlayerId, 'passed', json_encode(['limpet' => 1], JSON_UNESCAPED_UNICODE)]
            );
        }

        return $events;
    }

    /** Allo StarDock i tecnici staccano tutte le Limpet dallo scafo. @return int quante */
    public static function scrape(int $shipId): int
    {
        $n = (int) (Database::first('SELECT COUNT(*) AS n FROM ship_limpets WHERE ship_id = ?', [$shipId])['n'] ?? 0);
        if ($n > 0) {
            Database::run('DELETE FROM ship_limpets WHERE ship_id = ?', [$shipId]);
        }
        return $n;
    }

    /**
     * Le prede che sto tracciando, con posizione attuale (live).
     * @return list<array{player_id:int,handle:string,sector_id:int,ship_type:string,expires_in_min:int}>
     */
    public static function tracked(int $ownerId): array
    {
        $ttlMin = self::ttlHours() * 60;
        $rows = Database::all(
            "SELECT sl.attached_at, pl.id AS player_id, pl.handle, pl.sector_id, t.name AS ship_type
             FROM ship_limpets sl
             JOIN ships s      ON s.id = sl.ship_id
             JOIN players pl   ON pl.ship_id = s.id
             JOIN ship_types t ON t.ckey = s.type_key
             WHERE sl.owner_player_id = ?
             ORDER BY sl.attached_at DESC",
            [$ownerId]
        );
        return array_map(static function ($r) use ($ttlMin) {
            $ageMin = (int) floor((time() - strtotime((string) $r['attached_at'])) / 60);
            return [
                'player_id'      => (int) $r['player_id'],
                'handle'         => (string) $r['handle'],
                'sector_id'      => (int) $r['sector_id'],
                'ship_type'      => (string) $r['ship_type'],
                'expires_in_min' => max(0, $ttlMin - $ageMin),
            ];
        }, $rows);
    }

    /** Quante Limpet ho agganciate addosso (avviso in plancia). */
    public static function tagCount(int $shipId): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS n FROM ship_limpets WHERE ship_id = ?', [$shipId])['n'] ?? 0);
    }

    /** Id dei settori dove si trovano le mie prede (per la mappa stellare). @return list<int> */
    public static function trackedSectorIds(int $ownerId): array
    {
        return array_values(array_unique(array_map(
            static fn ($t) => $t['sector_id'],
            self::tracked($ownerId)
        )));
    }

    /** GC dal tick: stacca le Limpet scadute. @return int */
    public static function gc(): int
    {
        return Database::run(
            'DELETE FROM ship_limpets WHERE attached_at < DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [self::ttlHours()]
        )->rowCount();
    }
}
