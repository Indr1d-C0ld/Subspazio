<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Gestione dei turni giornalieri.
 *
 * Il tick (bin/tick.php) fa il reset di massa dopo l'ora configurata; qui
 * teniamo un refill "lazy" come rete di sicurezza al primo movimento del
 * giocatore dopo il rollover del giorno di gioco.
 */
final class TurnManager
{
    public static function perDay(): int
    {
        return GameConfig::int('turns.per_day', 2500);
    }

    private static function resetHour(): int
    {
        return GameConfig::int('turns.reset_hour', 3);
    }

    private static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(GameConfig::str('turns.timezone', 'Europe/Rome'));
    }

    /** Giorno di gioco corrente (Y-m-d) tenendo conto dell'ora di reset. */
    public static function gameDay(): string
    {
        $now = new \DateTimeImmutable('now', self::timezone());
        if ((int) $now->format('G') < self::resetHour()) {
            $now = $now->modify('-1 day');
        }
        return $now->format('Y-m-d');
    }

    /**
     * Applica il refill se il giocatore non ha ancora "girato pagina" oggi.
     * Ritorna la riga giocatore aggiornata.
     *
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function sync(array $player): array
    {
        $today = self::gameDay();
        if (($player['turns_reset_on'] ?? null) === $today) {
            return $player;
        }

        $perDay = self::perDay();
        Database::run(
            'UPDATE players SET turns = ?, turns_reset_on = ? WHERE id = ?',
            [$perDay, $today, $player['id']]
        );
        $player['turns'] = $perDay;
        $player['turns_reset_on'] = $today;
        return $player;
    }

    /** Reset di massa, invocato dal tick. Ritorna il numero di giocatori aggiornati. */
    public static function bulkReset(): int
    {
        $today = self::gameDay();
        return Database::run(
            'UPDATE players SET turns = ?, turns_reset_on = ?
             WHERE turns_reset_on IS NULL OR turns_reset_on < ?',
            [self::perDay(), $today, $today]
        )->rowCount();
    }
}
