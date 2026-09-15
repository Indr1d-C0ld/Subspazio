<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Accesso in lettura alla topologia dell'universo (settori e warp).
 */
final class Universe
{
    /**
     * Cache di processo per settori e rotte.
     *
     * La topologia non cambia durante una richiesta ne' durante un tick: le
     * uniche scritture sono la generazione dell'universo, quella dei porti e
     * la posa di un faro, e tutte e tre chiamano forget(). Senza questa cache
     * il movimento NPC rileggeva gli stessi settori centinaia di volte al
     * minuto, ognuna con una JOIN su regions.
     *
     * @var array<int, array<string,mixed>|null>
     */
    private static array $settori = [];

    /** @var array<int, list<int>> */
    private static array $rotte = [];

    /**
     * Svuota la cache: tutta, oppure il solo settore indicato.
     * Da chiamare dopo ogni scrittura su `sectors` o `warps`.
     */
    public static function forget(?int $sectorId = null): void
    {
        if ($sectorId === null) {
            self::$settori = [];
            self::$rotte = [];
            return;
        }
        unset(self::$settori[$sectorId], self::$rotte[$sectorId]);
    }

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
        if (array_key_exists($id, self::$settori)) {
            return self::$settori[$id];
        }
        return self::$settori[$id] = Database::first(
            'SELECT s.*, r.name AS region_name, r.color AS region_color, r.kind AS region_kind
             FROM sectors s LEFT JOIN regions r ON r.id = s.region_id
             WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * Carica molti settori in una sola query e li lascia in cache, cosi' le
     * sector() successive non toccano il database. Serve a chi sta per
     * esaminare un insieme noto di settori — tipicamente le destinazioni
     * possibili di un gruppo di NPC.
     *
     * @param list<int> $ids
     * @return array<int, array<string,mixed>> solo i settori esistenti
     */
    public static function sectorsMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $mancanti = array_values(array_filter($ids, static fn (int $id) => !array_key_exists($id, self::$settori)));

        if ($mancanti !== []) {
            $in = implode(',', array_fill(0, count($mancanti), '?'));
            $trovati = [];
            foreach (Database::all(
                "SELECT s.*, r.name AS region_name, r.color AS region_color, r.kind AS region_kind
                 FROM sectors s LEFT JOIN regions r ON r.id = s.region_id
                 WHERE s.id IN ({$in})",
                $mancanti
            ) as $row) {
                $trovati[(int) $row['id']] = $row;
                self::$settori[(int) $row['id']] = $row;
            }
            // anche le assenze vanno memorizzate, altrimenti si riprova a ogni giro
            foreach ($mancanti as $id) {
                if (!isset($trovati[$id])) {
                    self::$settori[$id] = null;
                }
            }
        }

        $out = [];
        foreach ($ids as $id) {
            if (self::$settori[$id] !== null) {
                $out[$id] = self::$settori[$id];
            }
        }
        return $out;
    }

    /** @return list<int> id dei settori raggiungibili con un warp da $id */
    public static function warpsFrom(int $id): array
    {
        if (array_key_exists($id, self::$rotte)) {
            return self::$rotte[$id];
        }
        return self::$rotte[$id] = array_map(
            static fn ($r) => (int) $r['to_sector'],
            Database::all('SELECT to_sector FROM warps WHERE from_sector = ? ORDER BY to_sector', [$id])
        );
    }

    /**
     * Rotte uscenti da molti settori in una sola query, lasciate in cache.
     *
     * @param list<int> $ids
     * @return array<int, list<int>> mappa from => [to, ...]
     */
    public static function warpsFromMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $mancanti = array_values(array_filter($ids, static fn (int $id) => !array_key_exists($id, self::$rotte)));

        if ($mancanti !== []) {
            $in = implode(',', array_fill(0, count($mancanti), '?'));
            $raccolta = array_fill_keys($mancanti, []);
            foreach (Database::all(
                "SELECT from_sector, to_sector FROM warps WHERE from_sector IN ({$in}) ORDER BY from_sector, to_sector",
                $mancanti
            ) as $row) {
                $raccolta[(int) $row['from_sector']][] = (int) $row['to_sector'];
            }
            foreach ($raccolta as $from => $verso) {
                self::$rotte[$from] = $verso;
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::$rotte[$id];
        }
        return $out;
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
