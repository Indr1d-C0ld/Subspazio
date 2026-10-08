<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Contatori di carriera del comandante: sopravvivono alle stagioni, come i
 * traguardi che li leggono. Si incrementano dagli eventi di gioco e non si
 * ricostruiscono dai registri (che le stagioni azzerano).
 */
final class Stats
{
    public static function add(int $playerId, string $chiave, int $n = 1): void
    {
        if ($playerId <= 0 || $n === 0) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO player_stats (player_id, chiave, valore) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valore = valore + VALUES(valore)',
                [$playerId, $chiave, $n]
            );
        } catch (\Throwable) {
            // un contatore non deve mai far fallire un'azione di gioco
        }
    }

    /** Tiene il massimo raggiunto (fascia piu' lontana, gradino di notorieta'...). */
    public static function max(int $playerId, string $chiave, int $v): void
    {
        if ($playerId <= 0) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO player_stats (player_id, chiave, valore) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valore = GREATEST(valore, VALUES(valore))',
                [$playerId, $chiave, $v]
            );
        } catch (\Throwable) {
        }
    }

    /** @return array<string,int> */
    public static function di(int $playerId): array
    {
        $out = [];
        foreach (Database::all('SELECT chiave, valore FROM player_stats WHERE player_id = ?', [$playerId]) as $r) {
            $out[(string) $r['chiave']] = (int) $r['valore'];
        }
        return $out;
    }
}
