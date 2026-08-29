<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Ciclo di vita del giocatore: creazione al primo ingresso in gioco,
 * con nave e posizione iniziali.
 */
final class PlayerService
{
    /** @return array<string,mixed>|null */
    public static function forUser(int $userId): ?array
    {
        return Database::first('SELECT * FROM players WHERE user_id = ?', [$userId]);
    }

    /** @return array<string,mixed>|null */
    public static function ship(int $shipId): ?array
    {
        return Database::first(
            'SELECT s.*, t.name AS type_name, t.turns_per_warp, t.max_holds, t.max_fighters,
                    t.max_shields, t.can_transwarp, t.combat_rating, t.hold_price, t.base_cost
             FROM ships s JOIN ship_types t ON t.ckey = s.type_key
             WHERE s.id = ?',
            [$shipId]
        );
    }

    /**
     * Restituisce (creandolo se serve) il giocatore per l'utente dato.
     *
     * @param array<string,mixed> $user riga della tabella users
     * @return array{player:array<string,mixed>, ship:array<string,mixed>, created:bool}
     */
    public static function ensureForUser(array $user): array
    {
        $existing = self::forUser((int) $user['id']);
        if ($existing !== null) {
            $ship = self::ship((int) $existing['ship_id']);
            return ['player' => $existing, 'ship' => $ship ?? [], 'created' => false];
        }

        if (!Universe::exists()) {
            throw new \RuntimeException('universe_missing');
        }

        $stardock = (int) (Database::first(
            'SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1'
        )['id'] ?? GameConfig::int('universe.stardock_sector', 1));

        $startShip    = GameConfig::str('player.start_ship', 'merchant_cruiser');
        $startCredits = GameConfig::int('player.start_credits', 1000);
        $startHolds   = GameConfig::int('player.start_holds', 20);
        $today        = TurnManager::gameDay();
        $perDay       = TurnManager::perDay();

        $type = Database::first('SELECT * FROM ship_types WHERE ckey = ?', [$startShip])
            ?? Database::first('SELECT * FROM ship_types ORDER BY sort_order LIMIT 1');
        if ($type === null) {
            throw new \RuntimeException('no_ship_types');
        }
        $startShip = (string) $type['ckey'];
        $startHolds = max($startHolds, (int) $type['base_holds']);

        $handle = self::uniqueHandle((string) ($user['display_name'] ?: $user['username']));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $protectHours = GameConfig::int('newbie.protect_hours', 48);
            Database::run(
                'INSERT INTO players (user_id, handle, sector_id, credits, turns, turns_reset_on, protected_until)
                 VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))',
                [(int) $user['id'], $handle, $stardock, $startCredits, $perDay, $today, $protectHours]
            );
            $playerId = Database::lastInsertId();

            Database::run(
                'INSERT INTO ships (player_id, type_key, name, sector_id, holds_total, shields, fighters)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $playerId,
                    $startShip,
                    'SS ' . $handle,
                    $stardock,
                    $startHolds,
                    (int) $type['base_shields'],
                    (int) $type['base_fighters'],
                ]
            );
            $shipId = Database::lastInsertId();

            Database::run('UPDATE players SET ship_id = ? WHERE id = ?', [$shipId, $playerId]);
            Database::run(
                'INSERT INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)',
                [$playerId, $stardock]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $player = self::forUser((int) $user['id']);
        $ship = self::ship((int) $player['ship_id']);
        return ['player' => $player ?? [], 'ship' => $ship ?? [], 'created' => true];
    }

    private static function uniqueHandle(string $base): string
    {
        $base = preg_replace('/[^A-Za-z0-9_ -]/', '', $base) ?: 'Comandante';
        $base = trim(substr($base, 0, 24));
        $candidate = $base;
        $n = 1;
        while (Database::first('SELECT 1 AS x FROM players WHERE handle = ?', [$candidate]) !== null) {
            $candidate = $base . ' ' . (++$n);
        }
        return $candidate;
    }
}
