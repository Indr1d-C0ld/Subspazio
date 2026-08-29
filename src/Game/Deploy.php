<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Dispiegamento e ritiro di caccia e mine nel settore corrente.
 */
final class Deploy
{
    /**
     * Forze presenti in un settore (per la scheda settore / look).
     *
     * @return array{fighters:list<array<string,mixed>>, mines:list<array<string,mixed>>}
     */
    public static function forces(int $sectorId, int $viewerPlayerId, bool $seesMines = false): array
    {
        $fighters = Database::all(
            'SELECT sf.owner_player_id, sf.qty, sf.mode, sf.toll, p.handle
             FROM sector_fighters sf JOIN players p ON p.id = sf.owner_player_id
             WHERE sf.sector_id = ? AND sf.qty > 0
             ORDER BY sf.qty DESC',
            [$sectorId]
        );
        $fighters = array_map(static fn ($f) => [
            'owner_id' => (int) $f['owner_player_id'],
            'handle'   => $f['handle'],
            'qty'      => (int) $f['qty'],
            'mode'     => $f['mode'],
            'toll'     => (int) $f['toll'],
            'mine'     => (int) $f['owner_player_id'] === $viewerPlayerId,
        ], $fighters);

        $mines = [];
        foreach (Database::all('SELECT owner_player_id, type, qty FROM sector_mines WHERE sector_id = ? AND qty > 0', [$sectorId]) as $m) {
            $own = (int) $m['owner_player_id'] === $viewerPlayerId;
            if (!$own && !$seesMines) {
                continue;
            }
            $mines[] = ['owner_id' => (int) $m['owner_player_id'], 'type' => $m['type'], 'qty' => (int) $m['qty'], 'mine' => $own];
        }

        return ['fighters' => $fighters, 'mines' => $mines];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function deployFighters(array $player, array $ship, int $qty, string $mode, int $toll = 0): array
    {
        if (!in_array($mode, ['offensive', 'defensive', 'toll'], true)) {
            return ['ok' => false, 'error' => 'Modalita\' non valida.'];
        }
        if ($qty <= 0 || $qty > (int) $ship['fighters']) {
            return ['ok' => false, 'error' => 'Caccia insufficienti a bordo.'];
        }
        $sectorId = (int) $player['sector_id'];
        $sector = Universe::sector($sectorId);
        if ((bool) $sector['is_fedspace'] && $mode !== 'defensive') {
            return ['ok' => false, 'error' => 'In spazio Federazione puoi lasciare solo caccia difensivi.'];
        }
        $toll = $mode === 'toll' ? max(0, $toll) : 0;

        Database::run('UPDATE ships SET fighters = fighters - ? WHERE id = ?', [$qty, $ship['id']]);
        Database::run(
            'INSERT INTO sector_fighters (sector_id, owner_player_id, corp_id, qty, mode, toll)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), mode = VALUES(mode), toll = VALUES(toll)',
            [$sectorId, $player['id'], $player['corp_id'] ?? null, $qty, $mode, $toll]
        );
        return ['ok' => true, 'deployed' => $qty, 'mode' => $mode];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function pullFighters(array $player, array $ship): array
    {
        $sectorId = (int) $player['sector_id'];
        $row = Database::first(
            'SELECT * FROM sector_fighters WHERE sector_id = ? AND owner_player_id = ?',
            [$sectorId, $player['id']]
        );
        if ($row === null || (int) $row['qty'] <= 0) {
            return ['ok' => false, 'error' => 'Non hai caccia dispiegati qui.'];
        }
        $type = Database::first('SELECT max_fighters FROM ship_types WHERE ckey = ?', [$ship['type_key']]);
        $room = (int) ($type['max_fighters'] ?? 0) - (int) $ship['fighters'];
        $take = min((int) $row['qty'], max(0, $room));
        if ($take <= 0) {
            return ['ok' => false, 'error' => 'Stiva caccia piena.'];
        }
        Database::run('UPDATE ships SET fighters = fighters + ? WHERE id = ?', [$take, $ship['id']]);
        if ($take >= (int) $row['qty']) {
            Database::run('DELETE FROM sector_fighters WHERE id = ?', [$row['id']]);
        } else {
            Database::run('UPDATE sector_fighters SET qty = qty - ? WHERE id = ?', [$take, $row['id']]);
        }
        return ['ok' => true, 'recovered' => $take];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function deployMines(array $player, array $ship, string $type, int $qty): array
    {
        if (!in_array($type, ['armid', 'limpet'], true)) {
            return ['ok' => false, 'error' => 'Tipo di mina non valido.'];
        }
        $col = $type === 'armid' ? 'mines_armid' : 'mines_limpet';
        if ($qty <= 0 || $qty > (int) $ship[$col]) {
            return ['ok' => false, 'error' => 'Mine insufficienti a bordo.'];
        }
        $sectorId = (int) $player['sector_id'];
        if ((bool) Universe::sector($sectorId)['is_fedspace']) {
            return ['ok' => false, 'error' => 'Vietato minare lo spazio della Federazione.'];
        }

        Database::run("UPDATE ships SET {$col} = {$col} - ? WHERE id = ?", [$qty, $ship['id']]);
        Database::run(
            'INSERT INTO sector_mines (sector_id, owner_player_id, type, qty) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
            [$sectorId, $player['id'], $type, $qty]
        );
        return ['ok' => true, 'deployed' => $qty, 'type' => $type];
    }
}
