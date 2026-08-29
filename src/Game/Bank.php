<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Banca Intergalattica (IGB): deposito fruttifero, operabile allo StarDock.
 * Gli interessi maturano in modo lazy dal timestamp + un passaggio dal tick.
 */
final class Bank
{
    public static function enabled(): bool
    {
        return GameConfig::bool('bank.enabled', true);
    }

    private static function dailyRate(): float
    {
        return GameConfig::float('bank.daily_interest_pct', 0.5) / 100.0;
    }

    /**
     * Conto del giocatore, con interessi maturati e persistiti.
     *
     * @return array{player_id:int, balance:int, last_interest_at:string}
     */
    public static function account(int $playerId): array
    {
        $row = Database::first('SELECT * FROM bank_accounts WHERE player_id = ?', [$playerId]);
        if ($row === null) {
            Database::run('INSERT INTO bank_accounts (player_id, balance) VALUES (?, 0)', [$playerId]);
            return ['player_id' => $playerId, 'balance' => 0, 'last_interest_at' => date('Y-m-d H:i:s')];
        }
        return self::accrue($row);
    }

    /** @param array<string,mixed> $row */
    private static function accrue(array $row): array
    {
        $bal = (int) $row['balance'];
        $elapsed = time() - strtotime((string) $row['last_interest_at']);
        if ($bal > 0 && $elapsed >= 3600) {
            $days = $elapsed / 86400.0;
            $grown = (int) floor($bal * ((1.0 + self::dailyRate()) ** $days));
            if ($grown !== $bal) {
                Database::run(
                    'UPDATE bank_accounts SET balance = ?, last_interest_at = NOW() WHERE player_id = ?',
                    [$grown, $row['player_id']]
                );
                $bal = $grown;
            }
        }
        return ['player_id' => (int) $row['player_id'], 'balance' => $bal, 'last_interest_at' => (string) $row['last_interest_at']];
    }

    /**
     * @param array<string,mixed> $player
     * @return array{ok:bool, error?:string, balance?:int, credits?:int}
     */
    public static function deposit(array $player, int $amount): array
    {
        return self::move($player, $amount, 'deposit');
    }

    /**
     * @param array<string,mixed> $player
     * @return array{ok:bool, error?:string, balance?:int, credits?:int}
     */
    public static function withdraw(array $player, int $amount): array
    {
        return self::move($player, $amount, 'withdraw');
    }

    /** @param array<string,mixed> $player */
    private static function move(array $player, int $amount, string $dir): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'Servizio bancario non disponibile.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Importo non valido.'];
        }
        if (!self::atBank((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'La banca opera solo allo StarDock.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::account((int) $player['id']); // assicura riga + interessi
            $acct = Database::first('SELECT * FROM bank_accounts WHERE player_id = ? FOR UPDATE', [$player['id']]);
            $p = Database::first('SELECT credits FROM players WHERE id = ? FOR UPDATE', [$player['id']]);
            $bal = (int) $acct['balance'];
            $cr = (int) $p['credits'];

            if ($dir === 'deposit') {
                if ($cr < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Crediti a bordo insufficienti.'];
                }
                $bal += $amount;
                $cr -= $amount;
            } else {
                if ($bal < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Saldo insufficiente.'];
                }
                $bal -= $amount;
                $cr += $amount;
            }

            Database::run('UPDATE bank_accounts SET balance = ? WHERE player_id = ?', [$bal, $player['id']]);
            Database::run('UPDATE players SET credits = ? WHERE id = ?', [$cr, $player['id']]);
            $pdo->commit();

            return ['ok' => true, 'balance' => $bal, 'credits' => $cr];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function atBank(int $sectorId): bool
    {
        return Database::first('SELECT 1 AS x FROM sectors WHERE id = ? AND is_stardock = 1', [$sectorId]) !== null;
    }

    /** Passaggio dal tick: matura gli interessi su tutti i conti. */
    public static function accrueAll(): int
    {
        $rate = self::dailyRate();
        if ($rate <= 0) {
            return 0;
        }
        $n = 0;
        foreach (Database::all('SELECT * FROM bank_accounts WHERE balance > 0') as $row) {
            $before = (int) $row['balance'];
            $after = self::accrue($row)['balance'];
            if ($after !== $before) {
                $n++;
            }
        }
        return $n;
    }
}
