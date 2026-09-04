<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Navigazione: descrizione del settore corrente (con fog-of-war),
 * movimento fra warp adiacenti, plotting di rotte e autopilota.
 */
final class Navigation
{
    /**
     * "Guarda" il settore corrente del giocatore.
     *
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function look(array $player): array
    {
        $sectorId = (int) $player['sector_id'];
        $sector = Universe::sector($sectorId);
        if ($sector === null) {
            throw new \RuntimeException("Settore {$sectorId} inesistente.");
        }

        $visited = self::visitedSet((int) $player['id']);
        $warpTargets = Universe::warpsFrom($sectorId);

        $warps = [];
        foreach ($warpTargets as $to) {
            $warps[] = [
                'to'           => $to,
                'visited'      => isset($visited[$to]),
                'return_known' => isset($visited[$to]) || Universe::warpExists($to, $sectorId),
            ];
        }

        $playersHere = Database::all(
            'SELECT p.id, p.handle, p.alignment, p.protected_until, t.name AS ship_type
             FROM players p
             JOIN ships s ON s.id = p.ship_id
             JOIN ship_types t ON t.ckey = s.type_key
             WHERE p.sector_id = ? AND p.id <> ?',
            [$sectorId, (int) $player['id']]
        );
        $playersHere = array_map(static fn ($o) => [
            'id'        => (int) $o['id'],
            'handle'    => $o['handle'],
            'ship_type' => $o['ship_type'],
            'protected' => $o['protected_until'] !== null && strtotime((string) $o['protected_until']) > time(),
        ], $playersHere);

        $ownShip = Database::first('SELECT dev_scanner FROM ships s JOIN players p ON p.ship_id = s.id WHERE p.id = ?', [(int) $player['id']]);
        $seesMines = ($ownShip['dev_scanner'] ?? 'none') !== 'none';
        $forces = Deploy::forces($sectorId, (int) $player['id'], $seesMines);

        $planets = array_map(static function ($pl) use ($player) {
            return [
                'id'       => (int) $pl['id'],
                'name'     => $pl['name'],
                'type'     => $pl['type_key'],
                'type_name' => $pl['type_name'],
                'owner'    => $pl['owner_handle'],
                'corp'     => $pl['corp_tag'],
                'citadel'  => (int) $pl['citadel_level'],
                'quasar'   => (int) $pl['quasar_level'],
                'mine'     => Planets::isOwn($pl, $player),
            ];
        }, Planets::inSector($sectorId));

        $port = null;
        if ((bool) $sector['has_port']) {
            $portRow = Economy::portAt($sectorId);
            if ($portRow !== null) {
                $port = Economy::portSummary($portRow);
            }
        }

        return [
            'id'          => $sectorId,
            'name'        => $sector['name'],
            'region'      => $sector['region_name'],
            'region_color' => $sector['region_color'],
            'is_fedspace' => (bool) $sector['is_fedspace'],
            'is_stardock' => (bool) $sector['is_stardock'],
            'has_port'    => (bool) $sector['has_port'],
            'port'        => $port,
            'nebula'      => $sector['nebula'],
            'beacon'      => $sector['beacon'],
            'coords'      => ['x' => (float) $sector['x'], 'y' => (float) $sector['y']],
            'warps'       => $warps,
            'players_here' => $playersHere,
            'forces'      => $forces,
            'planets'     => $planets,
            'npcs'        => Npc::inSector($sectorId),
            'note'        => SectorNotes::get((int) $player['id'], $sectorId),
            'pinned'      => SectorNotes::pinned((int) $player['id']),
            'can_attack'  => !((bool) $sector['is_fedspace']) && $playersHere !== [],
            'region_kind' => $sector['region_kind'] ?? 'core',
            'features'    => SectorFeatures::visibleFor((int) $player['id'], $sectorId),
        ];
    }

    /**
     * Muove il giocatore verso un settore adiacente.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{ok:bool, error?:string, code?:string, turns_left?:int, cost?:int, sector?:array<string,mixed>, player?:array<string,mixed>}
     */
    public static function move(array $player, array $ship, int $toSector, string $mode = 'warp'): array
    {
        $player = TurnManager::sync($player);
        $from = (int) $player['sector_id'];

        if ($toSector === $from) {
            return ['ok' => false, 'code' => 'same', 'error' => 'Sei gia\' in questo settore.'];
        }
        if (!Universe::warpExists($from, $toSector)) {
            return ['ok' => false, 'code' => 'no_warp', 'error' => "Nessun warp dal settore {$from} al settore {$toSector}."];
        }

        $cost = max(1, (int) ($ship['turns_per_warp'] ?? 1));
        $warpNote = null;
        if (\App\Game\Crew::consumePending((int) $player['id'], 'free_warp') !== null) {
            $cost = 0;
            $warpNote = 'Rotta rapida: salto gratuito.';
        } elseif ($cost > 1 && ($wd = (float) ($ship['crew_warp_discount_pct'] ?? 0)) > 0 && mt_rand(1, 100) <= $wd) {
            $cost--;
            $warpNote = 'Il Navigatore ottimizza la rotta: -1 turno.';
        }
        $grav = \App\Game\SectorFeatures::gravityTurnPenalty((int) $player['id'], $toSector);
        if ($grav > 0) {
            $cost += $grav;
            $warpNote = trim(($warpNote ?? '') . " Pozzo gravitazionale: +{$grav} turno/i.");
        }
        if ((int) $player['turns'] < $cost) {
            return [
                'ok' => false,
                'code' => 'no_turns',
                'error' => "Turni insufficienti: servono {$cost}, disponibili " . (int) $player['turns'] . '.',
                'turns_left' => (int) $player['turns'],
            ];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE players SET turns = turns - ?, sector_id = ?, total_warps = total_warps + 1, last_move_at = NOW()
                 WHERE id = ? AND turns >= ?',
                [$cost, $toSector, (int) $player['id'], $cost]
            );
            Database::run('UPDATE ships SET sector_id = ? WHERE id = ?', [$toSector, (int) $ship['id']]);
            $regen = (int) ($ship['mod_shield_regen'] ?? 0);
            if ($regen > 0) {
                Database::run(
                    'UPDATE ships SET shields = LEAST(?, shields + ?) WHERE id = ?',
                    [(int) ($ship['max_shields'] ?? 0) ?: 999999, $regen, (int) $ship['id']]
                );
            }
            Database::run(
                'INSERT INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE last_seen = NOW(), visits = visits + 1',
                [(int) $player['id'], $toSector]
            );
            Database::run(
                'INSERT INTO move_log (player_id, from_sector, to_sector, turns_spent, mode)
                 VALUES (?, ?, ?, ?, ?)',
                [(int) $player['id'], $from, $toSector, $cost, $mode]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $handle = (string) $player['handle'];
        $player['turns'] = (int) $player['turns'] - $cost;
        $player['sector_id'] = $toSector;
        $player['total_warps'] = (int) $player['total_warps'] + 1;

        Live::sector($from, 'move_out', null, "{$handle} ha lasciato il settore", ['handle' => $handle]);
        Live::sector($toSector, 'move_in', null, "{$handle} e' entrato nel settore", ['handle' => $handle, 'ship' => $ship['type_name'] ?? null]);

        // intercettazioni: mine, caccia dispiegati
        $enc = Combat::onEnterSector($player, $ship);
        $player = $enc['player'];
        $ship = $enc['ship'];
        if ($warpNote !== null) {
            array_unshift($enc['events'], $warpNote);
        }

        if (!empty($enc['destroyed'])) {
            Live::alert((int) $player['id'], 'destroyed', 'Nave distrutta', implode(' ', $enc['events']), '/gioco');
        } elseif (!empty($enc['events'])) {
            Live::player((int) $player['id'], 'entry_combat', 'Contatto nel settore', implode(' ', $enc['events']));
        }

        \App\Game\ShipLog::fromEntryEvents((int) $player['id'], $toSector, $enc['events'], !empty($enc['destroyed']));

        return [
            'ok'          => true,
            'cost'        => $cost,
            'turns_left'  => (int) $player['turns'],
            'entry_events' => $enc['events'],
            'destroyed'   => $enc['destroyed'],
            'sector'      => self::look($player),
            'player'      => $player,
            'ship'        => $ship,
        ];
    }

    /**
     * Traccia una rotta (percorso minimo in numero di warp).
     *
     * @param array<string,mixed> $player
     * @return array{ok:bool, error?:string, path?:list<int>, hops?:int, turns?:int}
     */
    public static function plotCourse(array $player, int $toSector, bool $knownOnly = true, int $turnsPerWarp = 1): array
    {
        $from = (int) $player['sector_id'];
        if (Universe::sector($toSector) === null) {
            return ['ok' => false, 'error' => "Settore {$toSector} inesistente."];
        }

        $restrict = null;
        if ($knownOnly) {
            $restrict = self::visitedSet((int) $player['id']);
        }

        $path = Universe::shortestPath($from, $toSector, $restrict);
        if ($path === null) {
            return [
                'ok' => false,
                'error' => $knownOnly
                    ? 'Nessuna rotta nota: esplora altri settori o traccia dal computer di bordo.'
                    : "Nessuna rotta dal settore {$from} al settore {$toSector}.",
            ];
        }

        $hops = count($path) - 1;
        return [
            'ok'    => true,
            'path'  => $path,
            'hops'  => $hops,
            'turns' => $hops * max(1, $turnsPerWarp),
        ];
    }

    /**
     * Autopilota: segue una rotta finche' possibile.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{ok:bool, error?:string, moved:list<int>, stopped:string, sector?:array<string,mixed>, player?:array<string,mixed>}
     */
    public static function autopilot(array $player, array $ship, int $toSector, bool $knownOnly = true): array
    {
        $player = TurnManager::sync($player);
        $cost = max(1, (int) ($ship['turns_per_warp'] ?? 1));

        $plot = self::plotCourse($player, $toSector, $knownOnly, $cost);
        if (!$plot['ok']) {
            return ['ok' => false, 'error' => $plot['error'], 'moved' => [], 'stopped' => 'no_route'];
        }

        $path = $plot['path'];
        array_shift($path); // via il settore di partenza
        $maxHops = GameConfig::int('nav.autopilot_max_hops', 200);

        $moved = [];
        $stopped = 'arrived';
        foreach ($path as $i => $next) {
            if ($i >= $maxHops) {
                $stopped = 'max_hops';
                break;
            }
            $step = self::move($player, $ship, (int) $next, 'autopilot');
            if (!$step['ok']) {
                $stopped = $step['code'] ?? 'blocked';
                break;
            }
            $moved[] = (int) $next;
            $player = $step['player'];
            $ship = $step['ship'] ?? $ship;
            if (!empty($step['entry_events'])) {
                $stopped = 'contact';
                break;
            }
            if (!empty($step['destroyed'])) {
                $stopped = 'destroyed';
                break;
            }
        }

        return [
            'ok'      => true,
            'moved'   => $moved,
            'stopped' => $stopped,
            'sector'  => self::look($player),
            'player'  => $player,
        ];
    }

    public static function setBeacon(array $player, string $text): array
    {
        $text = trim(mb_substr($text, 0, 80));
        Database::run(
            'UPDATE sectors SET beacon = ?, beacon_by = ? WHERE id = ?',
            [$text === '' ? null : $text, (int) $player['id'], (int) $player['sector_id']]
        );
        return ['ok' => true, 'beacon' => $text === '' ? null : $text];
    }

    /**
     * Dati per il rendering della mappa: settori noti (visitati + adiacenti
     * ai visitati) e warp noti (uscenti dai visitati).
     *
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function mapData(array $player): array
    {
        $pid = (int) $player['id'];
        $visited = self::visitedSet($pid);

        if ($visited === []) {
            $visited = [(int) $player['sector_id'] => true];
        }

        $ids = array_keys($visited);
        $in = implode(',', array_fill(0, count($ids), '?'));

        // warp uscenti dai settori visitati = warp noti
        $warpRows = Database::all(
            "SELECT from_sector, to_sector FROM warps WHERE from_sector IN ($in)",
            $ids
        );

        $known = $visited;
        $warps = [];
        foreach ($warpRows as $w) {
            $warps[] = [(int) $w['from_sector'], (int) $w['to_sector']];
            $known[(int) $w['to_sector']] = $known[(int) $w['to_sector']] ?? false;
        }

        $knownIds = array_keys($known);
        $in2 = implode(',', array_fill(0, count($knownIds), '?'));
        $sectorRows = Database::all(
            "SELECT s.id, s.name, s.x, s.y, s.is_fedspace, s.is_stardock, s.has_port,
                    r.color AS region_color
             FROM sectors s LEFT JOIN regions r ON r.id = s.region_id
             WHERE s.id IN ($in2)",
            $knownIds
        );

        $sectors = [];
        foreach ($sectorRows as $s) {
            $sid = (int) $s['id'];
            $sectors[] = [
                'id'       => $sid,
                'name'     => $s['name'],
                'x'        => (float) $s['x'],
                'y'        => (float) $s['y'],
                'visited'  => isset($visited[$sid]),
                'fedspace' => (bool) $s['is_fedspace'],
                'stardock' => (bool) $s['is_stardock'],
                'has_port' => (bool) $s['has_port'],
                'color'    => $s['region_color'] ?: '#5b6b8c',
            ];
        }

        return [
            'current' => (int) $player['sector_id'],
            'sectors' => $sectors,
            'warps'   => $warps,
        ];
    }

    /** @return array<int,bool> */
    private static function visitedSet(int $playerId): array
    {
        $set = [];
        foreach (
            Database::all('SELECT sector_id FROM player_visited_sectors WHERE player_id = ?', [$playerId]) as $row
        ) {
            $set[(int) $row['sector_id']] = true;
        }
        return $set;
    }
}
