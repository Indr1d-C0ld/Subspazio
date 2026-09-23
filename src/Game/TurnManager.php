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
    /**
     * Istante d'inizio del giorno di gioco corrente, nell'ora del database.
     * Serve a contare "oggi" come lo conta il gioco (dal reset delle 03:00),
     * non come lo conta il calendario (dalla mezzanotte).
     */
    public static function gameDayStart(): string
    {
        $inizio = new \DateTimeImmutable(self::gameDay() . sprintf(' %02d:00:00', self::resetHour()), self::timezone());
        return $inizio->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    public static function gameDay(): string
    {
        return self::gameDayAt(time());
    }

    /** Il giorno di gioco a cui appartiene un istante qualunque. */
    public static function gameDayAt(int $ts): string
    {
        $t = (new \DateTimeImmutable('@' . $ts))->setTimezone(self::timezone());
        if ((int) $t->format('G') < self::resetHour()) {
            $t = $t->modify('-1 day');
        }
        return $t->format('Y-m-d');
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

        // La data va vincolata nella WHERE, non solo controllata sulla
        // fotografia qui sopra: due richieste che entrano insieme nel cambio
        // giorno la condividono, e la seconda — arrivata magari dopo che il
        // giocatore aveva gia' speso dei turni — li rimetterebbe al massimo,
        // restituendo di fatto quel che era stato consumato.
        $applicato = Database::run(
            'UPDATE players SET turns = ?, turns_reset_on = ?
             WHERE id = ? AND (turns_reset_on IS NULL OR turns_reset_on < ?)',
            [$perDay, $today, $player['id'], $today]
        )->rowCount() > 0;

        if ($applicato) {
            $player['turns'] = $perDay;
            $player['turns_reset_on'] = $today;
            return $player;
        }

        // Il refill l'ha gia' fatto qualcun altro: la fotografia in mano e'
        // vecchia per definizione, quindi si rilegge invece di inventare.
        return Database::first('SELECT * FROM players WHERE id = ?', [$player['id']]) ?? $player;
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
