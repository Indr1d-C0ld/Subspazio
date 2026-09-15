<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Movimenti atomici sulle risorse di un comandante.
 *
 * Il motivo di esistere di questa classe: le funzioni di gioco ricevono
 * $player come fotografia scattata a inizio richiesta. Controllare la
 * capienza su quella copia e poi scrivere senza vincolo lascia passare due
 * richieste concorrenti che spendono lo stesso saldo (saldi negativi, merce
 * non pagata). Qui il vincolo sta nella WHERE dell'UPDATE, dove il database
 * lo applica una volta sola: la seconda richiesta non trova capienza e torna
 * a mani vuote senza aver scritto nulla.
 *
 * Stesso principio gia' usato da Economy::trade(), Corp::treasury() e Bank,
 * che pero' lo ottengono con SELECT ... FOR UPDATE dentro una transazione:
 * quella forma resta preferibile quando servono piu' letture coerenti, questa
 * quando l'addebito e' una singola istruzione.
 */
final class Wallet
{
    /** Colonne addebitabili: scalate solo se c'e' capienza. */
    private const SPENDABLE = ['credits', 'salvage', 'crystals', 'components', 'turns'];

    /** Colonne accreditabili senza vincolo. */
    private const CREDITABLE = ['credits', 'salvage', 'crystals', 'components', 'turns', 'experience', 'bounty'];

    /** Colonne a variazione libera (possono andare in negativo per disegno). */
    private const ADJUSTABLE = ['alignment', 'experience', 'bounty'];

    /** Colonne assegnabili a un valore secco. */
    private const ASSIGNABLE = ['bounty'];

    /** Stive e dotazioni di bordo scalabili con guardia di capienza. */
    private const SHIP_TAKEABLE = [
        'hold_ore', 'hold_organics', 'hold_equipment', 'hold_colonists',
        'fighters', 'shields', 'mines_armid', 'mines_limpet', 'probes', 'genesis',
    ];

    /**
     * Addebito atomico. Ritorna false — senza aver scritto nulla — se anche
     * una sola delle risorse richieste non era capiente.
     *
     * @param array<string,int>   $costs  colonne da scalare, con guardia
     * @param array<string,int>   $deltas variazioni senza guardia (es. alignment)
     * @param array<string,mixed> $set    assegnazioni secche (es. bounty => 0)
     */
    public static function charge(int $playerId, array $costs, array $deltas = [], array $set = []): bool
    {
        $sets = [];
        $setParams = [];
        $guards = [];
        $guardParams = [];

        foreach ($costs as $col => $amount) {
            self::assert($col, self::SPENDABLE, 'addebitabile');
            $amount = (int) $amount;
            if ($amount < 0) {
                throw new InvalidArgumentException("Importo negativo per {$col}: usa un accredito.");
            }
            if ($amount === 0) {
                continue;
            }
            $sets[] = "{$col} = {$col} - ?";
            $setParams[] = $amount;
            $guards[] = "{$col} >= ?";
            $guardParams[] = $amount;
        }

        foreach ($deltas as $col => $delta) {
            self::assert($col, self::ADJUSTABLE, 'a variazione libera');
            if ((int) $delta === 0) {
                continue;
            }
            $sets[] = "{$col} = {$col} + ?";
            $setParams[] = (int) $delta;
        }

        foreach ($set as $col => $value) {
            self::assert($col, self::ASSIGNABLE, 'assegnabile');
            $sets[] = "{$col} = ?";
            $setParams[] = $value;
        }

        if ($sets === []) {
            return true;
        }

        $sql = 'UPDATE players SET ' . implode(', ', $sets) . ' WHERE id = ?'
            . ($guards === [] ? '' : ' AND ' . implode(' AND ', $guards));

        $affected = Database::run($sql, [...$setParams, $playerId, ...$guardParams])->rowCount();

        // Senza guardie non c'e' niente da fallire: rowCount() puo' essere 0
        // solo perche' i valori coincidevano gia' con quelli richiesti.
        return $guards === [] ? true : $affected > 0;
    }

    /**
     * Accredito senza vincolo (ricompense, rimborsi, incassi).
     *
     * @param array<string,int> $amounts
     */
    public static function credit(int $playerId, array $amounts): void
    {
        $sets = [];
        $params = [];
        foreach ($amounts as $col => $amount) {
            self::assert($col, self::CREDITABLE, 'accreditabile');
            if ((int) $amount === 0) {
                continue;
            }
            $sets[] = "{$col} = {$col} + ?";
            $params[] = (int) $amount;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $playerId;
        Database::run('UPDATE players SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /**
     * Trasferimento fra comandanti, tutto o niente: se il pagatore non ha
     * capienza nessuno dei due saldi si muove, e il denaro non puo' sparire
     * a meta' strada come farebbero due UPDATE indipendenti.
     */
    public static function transfer(int $fromPlayerId, int $toPlayerId, int $amount): bool
    {
        $amount = (int) $amount;
        if ($amount <= 0 || $fromPlayerId === $toPlayerId) {
            return false;
        }

        $pdo = Database::pdo();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            if (!self::charge($fromPlayerId, ['credits' => $amount])) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }
            self::credit($toPlayerId, ['credits' => $amount]);
            if ($ownTransaction) {
                $pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Confisca fino a $max crediti, restituendo quanto e' stato davvero
     * sottratto. Serve dove l'importo e' calcolato su una percentuale del
     * saldo altrui: se nel frattempo la vittima ha speso, si prende quel che
     * c'e' invece di spingere il saldo sotto zero.
     */
    public static function seize(int $playerId, int $max): int
    {
        $max = (int) $max;
        if ($max <= 0) {
            return 0;
        }

        $pdo = Database::pdo();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $row = Database::first('SELECT credits FROM players WHERE id = ? FOR UPDATE', [$playerId]);
            $take = $row === null ? 0 : max(0, min($max, (int) $row['credits']));
            if ($take > 0) {
                Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$take, $playerId]);
            }
            if ($ownTransaction) {
                $pdo->commit();
            }
            return $take;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Scala una dotazione di bordo solo se presente in quantita' sufficiente.
     * Ritorna false senza scrivere se il carico non bastava.
     */
    public static function takeFromShip(int $shipId, string $column, int $qty): bool
    {
        self::assert($column, self::SHIP_TAKEABLE, 'scalabile dalla nave');
        $qty = (int) $qty;
        if ($qty <= 0) {
            return false;
        }
        return Database::run(
            "UPDATE ships SET {$column} = {$column} - ? WHERE id = ? AND {$column} >= ?",
            [$qty, $shipId, $qty]
        )->rowCount() > 0;
    }

    /**
     * @param list<string> $allowed
     */
    private static function assert(string $column, array $allowed, string $ruolo): void
    {
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException("Colonna non {$ruolo}: {$column}");
        }
    }
}
