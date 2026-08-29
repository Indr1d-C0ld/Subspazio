<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Cronologia degli spostamenti (dal move_log) e settori piu' frequentati.
 */
final class RouteLog
{
    /** @return list<array<string,mixed>> */
    public static function recent(int $playerId, int $limit = 60): array
    {
        return array_map(static fn ($r) => [
            'from'  => (int) $r['from_sector'],
            'to'    => (int) $r['to_sector'],
            'to_name' => $r['to_name'],
            'turns' => (int) $r['turns_spent'],
            'mode'  => $r['mode'],
            'at'    => $r['created_at'],
        ], Database::all(
            'SELECT ml.from_sector, ml.to_sector, ml.turns_spent, ml.mode, ml.created_at, s.name AS to_name
             FROM move_log ml LEFT JOIN sectors s ON s.id = ml.to_sector
             WHERE ml.player_id = ?
             ORDER BY ml.id DESC LIMIT ?',
            [$playerId, $limit]
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function mostVisited(int $playerId, int $limit = 15): array
    {
        return array_map(static fn ($r) => [
            'sector' => (int) $r['sector_id'],
            'name'   => $r['name'],
            'visits' => (int) $r['visits'],
            'last'   => $r['last_seen'],
        ], Database::all(
            'SELECT pvs.sector_id, pvs.visits, pvs.last_seen, s.name
             FROM player_visited_sectors pvs JOIN sectors s ON s.id = pvs.sector_id
             WHERE pvs.player_id = ?
             ORDER BY pvs.visits DESC, pvs.last_seen DESC
             LIMIT ?',
            [$playerId, $limit]
        ));
    }

    public static function stats(int $playerId): array
    {
        $row = Database::first(
            'SELECT COUNT(*) AS moves, COALESCE(SUM(turns_spent),0) AS turns, COUNT(DISTINCT to_sector) AS distinct_dest
             FROM move_log WHERE player_id = ?',
            [$playerId]
        ) ?? [];
        return [
            'moves'        => (int) ($row['moves'] ?? 0),
            'turns'        => (int) ($row['turns'] ?? 0),
            'distinct_dest' => (int) ($row['distinct_dest'] ?? 0),
        ];
    }
}
