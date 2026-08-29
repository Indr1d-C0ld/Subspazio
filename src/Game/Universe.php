<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Accesso in lettura alla topologia dell'universo (settori e warp).
 */
final class Universe
{
    public static function exists(): bool
    {
        try {
            return (int) (Database::first('SELECT COUNT(*) AS c FROM sectors')['c'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function sectorCount(): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS c FROM sectors')['c'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public static function sector(int $id): ?array
    {
        return Database::first(
            'SELECT s.*, r.name AS region_name, r.color AS region_color, r.kind AS region_kind
             FROM sectors s LEFT JOIN regions r ON r.id = s.region_id
             WHERE s.id = ?',
            [$id]
        );
    }

    /** @return list<int> id dei settori raggiungibili con un warp da $id */
    public static function warpsFrom(int $id): array
    {
        return array_map(
            static fn ($r) => (int) $r['to_sector'],
            Database::all('SELECT to_sector FROM warps WHERE from_sector = ? ORDER BY to_sector', [$id])
        );
    }

    /** @return list<int> id dei settori da cui si puo' raggiungere $id */
    public static function warpsTo(int $id): array
    {
        return array_map(
            static fn ($r) => (int) $r['from_sector'],
            Database::all('SELECT from_sector FROM warps WHERE to_sector = ? ORDER BY from_sector', [$id])
        );
    }

    public static function warpExists(int $from, int $to): bool
    {
        return Database::first(
            'SELECT 1 AS x FROM warps WHERE from_sector = ? AND to_sector = ?',
            [$from, $to]
        ) !== null;
    }

    /**
     * Intera lista di archi, per il plotting di rotte lato server.
     * @return array<int, list<int>> mappa from => [to, ...]
     */
    public static function adjacency(): array
    {
        $adj = [];
        foreach (Database::all('SELECT from_sector, to_sector FROM warps') as $row) {
            $adj[(int) $row['from_sector']][] = (int) $row['to_sector'];
        }
        return $adj;
    }

    /**
     * BFS: percorso minimo (in numero di warp) fra due settori.
     * $restrictFrom, se fornito, limita gli archi a quelli uscenti da
     * settori in quell'insieme (fog-of-war: si conoscono solo i link dei
     * settori gia' visitati).
     *
     * @param array<int,bool>|null $restrictFrom
     * @return list<int>|null percorso completo [from, ..., to] oppure null
     */
    public static function shortestPath(int $from, int $to, ?array $restrictFrom = null): ?array
    {
        if ($from === $to) {
            return [$from];
        }

        $adj = self::adjacency();
        $queue = [$from];
        $prev = [$from => null];

        while ($queue !== []) {
            $cur = array_shift($queue);
            if ($restrictFrom !== null && !isset($restrictFrom[$cur])) {
                // non conosciamo i link uscenti da qui: vicolo cieco per il plotter
                continue;
            }
            foreach ($adj[$cur] ?? [] as $next) {
                if (array_key_exists($next, $prev)) {
                    continue;
                }
                $prev[$next] = $cur;
                if ($next === $to) {
                    $path = [$to];
                    $step = $cur;
                    while ($step !== null) {
                        array_unshift($path, $step);
                        $step = $prev[$step];
                    }
                    return $path;
                }
                $queue[] = $next;
            }
        }

        return null;
    }
}
