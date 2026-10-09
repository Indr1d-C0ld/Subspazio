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
        } catch (\Throwable $e) {
            // un contatore non deve mai far fallire un'azione di gioco...
            self::seAnnullata($e);
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
        } catch (\Throwable $e) {
            self::seAnnullata($e);
        }
    }

    /**
     * ...tranne quando il database ha gia' annullato la transazione in cui il
     * contatore stava (stallo fra due richieste): ingoiare l'errore lasciava
     * proseguire il chiamante in autocommit, come se il salto o lo scambio
     * fossero andati a buon fine, con turni e merce mai scalati.
     */
    private static function seAnnullata(\Throwable $e): void
    {
        $info = $e instanceof \PDOException ? ($e->errorInfo ?? []) : [];
        if (($info[0] ?? '') === '40001' || (int) ($info[1] ?? 0) === 1213) {
            throw $e;
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
