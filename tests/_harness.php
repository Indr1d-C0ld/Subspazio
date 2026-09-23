<?php

declare(strict_types=1);

/**
 * Impianto minimo per i test di integrazione.
 *
 * Non c'e' un database separato: le prove girano su quello configurato, ma
 * toccano solo righe sintetiche che creano loro stesse, riconoscibili dal
 * prefisso `__test_`. La pulizia e' registrata come shutdown function, quindi
 * avviene anche se un test muore per errore fatale; a ogni avvio viene inoltre
 * spazzato via quel che eventuali corse interrotte avessero lasciato indietro.
 */

use App\Core\Database;

/** Esito delle verifiche della corsa in corso. */
final class Esito
{
    /** @var list<array{titolo:string, ok:bool, nota:string}> */
    private static array $righe = [];
    private static string $sezione = '';

    public static function sezione(string $titolo): void
    {
        self::$sezione = $titolo;
        echo "\n  " . mb_strtoupper($titolo) . "\n";
    }

    public static function scenario(string $descrizione): void
    {
        echo "    · {$descrizione}\n";
    }

    public static function verifica(string $titolo, bool $ok, string $nota = ''): void
    {
        self::$righe[] = ['titolo' => $titolo, 'ok' => $ok, 'nota' => $nota];
        printf("      [%s] %s%s\n", $ok ? ' ok ' : 'FALL', $titolo, $nota !== '' ? "  ({$nota})" : '');
    }

    public static function uguale(string $titolo, mixed $atteso, mixed $ottenuto): void
    {
        $ok = $atteso === $ottenuto;
        self::verifica($titolo, $ok, $ok ? '' : sprintf('atteso %s, ottenuto %s', var_export($atteso, true), var_export($ottenuto, true)));
    }

    public static function falliti(): int
    {
        return count(array_filter(self::$righe, static fn ($r) => !$r['ok']));
    }

    public static function totali(): int
    {
        return count(self::$righe);
    }
}

/**
 * Comandanti e navi finti, creati per la durata di una prova e poi rimossi.
 */
final class Finti
{
    public const PREFISSO = '__test_';

    /** @var array{users:list<int>, players:list<int>, ships:list<int>} */
    private static array $creati = ['users' => [], 'players' => [], 'ships' => []];
    private static int $contatore = 0;
    private static bool $pulizaRegistrata = false;

    /**
     * Un comandante con la cassa e le stive volute, in un settore scelto.
     *
     * @param array<string,int> $stive es. ['hold_ore' => 50]
     * @return array{0:array<string,mixed>, 1:array<string,mixed>} [player, ship]
     */
    public static function comandante(int $crediti = 0, array $stive = [], ?int $settore = null, int $turni = 500): array
    {
        self::registraPulizia();
        self::$contatore++;
        $n = self::$contatore;
        $settore ??= (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];

        Database::run(
            'INSERT INTO users (username, email, password_hash, status, role) VALUES (?, ?, ?, ?, ?)',
            [self::PREFISSO . "u{$n}", self::PREFISSO . "u{$n}@invalid.test", 'x', 'active', 'player']
        );
        $uid = Database::lastInsertId();
        self::$creati['users'][] = $uid;

        Database::run(
            'INSERT INTO players (user_id, handle, sector_id, credits, turns) VALUES (?, ?, ?, ?, ?)',
            [$uid, self::PREFISSO . "p{$n}", $settore, $crediti, $turni]
        );
        $pid = Database::lastInsertId();
        self::$creati['players'][] = $pid;

        $colonne = ['player_id', 'type_key', 'name', 'sector_id', 'holds_total'];
        $valori  = [$pid, 'merchant_cruiser', self::PREFISSO . "s{$n}", $settore, 50];
        foreach ($stive as $col => $qty) {
            if (!preg_match('/^(hold_[a-z]+|fighters|shields|mines_[a-z]+|probes|genesis)$/', $col)) {
                throw new InvalidArgumentException("Colonna nave non ammessa nei test: {$col}");
            }
            $colonne[] = $col;
            $valori[] = (int) $qty;
        }
        Database::run(
            'INSERT INTO ships (' . implode(', ', $colonne) . ') VALUES (' . implode(', ', array_fill(0, count($colonne), '?')) . ')',
            $valori
        );
        $sid = Database::lastInsertId();
        self::$creati['ships'][] = $sid;
        Database::run('UPDATE players SET ship_id = ? WHERE id = ?', [$sid, $pid]);

        return self::ricarica($pid);
    }

    /**
     * Rilegge dal database comandante e nave: serve a distinguere quel che il
     * codice ha scritto davvero da quel che la fotografia in memoria diceva.
     *
     * @return array{0:array<string,mixed>, 1:array<string,mixed>}
     */
    public static function ricarica(int $playerId): array
    {
        $p = Database::first('SELECT * FROM players WHERE id = ?', [$playerId]);
        $s = Database::first('SELECT * FROM ships WHERE player_id = ?', [$playerId]);
        return [$p ?? [], $s ?? []];
    }

    public static function crediti(int $playerId): int
    {
        return (int) (Database::first('SELECT credits FROM players WHERE id = ?', [$playerId])['credits'] ?? 0);
    }

    public static function stiva(int $shipId, string $colonna): int
    {
        if (!preg_match('/^(hold_[a-z]+|fighters|shields|mines_[a-z]+|probes|genesis)$/', $colonna)) {
            throw new InvalidArgumentException("Colonna nave non ammessa: {$colonna}");
        }
        return (int) (Database::first("SELECT {$colonna} FROM ships WHERE id = ?", [$shipId])[$colonna] ?? 0);
    }

    private static function registraPulizia(): void
    {
        if (self::$pulizaRegistrata) {
            return;
        }
        self::$pulizaRegistrata = true;
        register_shutdown_function(static function (): void {
            self::pulisci();
        });
    }

    /** Rimuove tutto quel che questa corsa ha creato. Idempotente. */
    public static function pulisci(): void
    {
        foreach (array_reverse(self::$creati['ships']) as $id) {
            try {
                Database::run('DELETE FROM ships WHERE id = ?', [$id]);
            } catch (\Throwable) {
            }
        }
        foreach (array_reverse(self::$creati['players']) as $id) {
            try {
                Database::run('DELETE FROM players WHERE id = ?', [$id]);
            } catch (\Throwable) {
            }
        }
        foreach (array_reverse(self::$creati['users']) as $id) {
            try {
                Database::run('DELETE FROM users WHERE id = ?', [$id]);
            } catch (\Throwable) {
            }
        }
        self::$creati = ['users' => [], 'players' => [], 'ships' => []];
    }

    /**
     * Spazza via i resti di corse interrotte: righe col prefisso di prova che
     * nessuno ha ripulito. Ritorna quante ne ha tolte.
     */
    public static function spazzaOrfani(): int
    {
        $p = self::PREFISSO . '%';
        $n = 0;
        try {
            $n += Database::run(
                'DELETE s FROM ships s JOIN players pl ON pl.id = s.player_id WHERE pl.handle LIKE ?',
                [$p]
            )->rowCount();
            $n += Database::run('DELETE FROM players WHERE handle LIKE ?', [$p])->rowCount();
            $n += Database::run('DELETE FROM users WHERE username LIKE ?', [$p])->rowCount();
        } catch (\Throwable) {
        }
        return $n;
    }

    /**
     * Tracce che le prove lasciano in tabelle senza vincolo verso players.
     *
     * Cancellare comandanti, navi e utenti non basta: il registro battaglie, il
     * giornale di bordo e il codex non hanno chiavi esterne, e le prove ci
     * scrivevano dentro a ogni esecuzione. Al 23/09/2026 erano 205 righe su 220
     * del registro battaglie reale: battaglie di comandanti che non esistevano.
     *
     * Si toglie solo cio' che e' nato DURANTE questa esecuzione (id oltre la
     * soglia presa all'inizio, o data dopo l'inizio) e punta a comandanti che
     * non esistono piu': la storia dei giocatori veri non viene sfiorata.
     *
     * @param array{combat_log:int, ship_log:int, inizio:string} $soglia
     */
    public static function spazzaTracce(array $soglia): int
    {
        $n = 0;
        try {
            $n += Database::run(
                'DELETE c FROM combat_log c
                   LEFT JOIN players pa ON pa.id = c.attacker_player_id
                   LEFT JOIN players pd ON pd.id = c.defender_player_id
                  WHERE c.id > ?
                    AND (c.attacker_player_id IS NULL OR pa.id IS NULL)
                    AND (c.defender_player_id IS NULL OR pd.id IS NULL)',
                [$soglia['combat_log']]
            )->rowCount();
            $n += Database::run(
                'DELETE l FROM ship_log l LEFT JOIN players p ON p.id = l.player_id
                  WHERE l.id > ? AND p.id IS NULL',
                [$soglia['ship_log']]
            )->rowCount();
            $n += Database::run(
                'DELETE c FROM player_codex c LEFT JOIN players p ON p.id = c.player_id
                  WHERE c.unlocked_at >= ? AND p.id IS NULL',
                [$soglia['inizio']]
            )->rowCount();
        } catch (\Throwable) {
        }
        return $n;
    }

    /** @return array{combat_log:int, ship_log:int, inizio:string} */
    public static function sogliaTracce(): array
    {
        return [
            'combat_log' => (int) (Database::first('SELECT COALESCE(MAX(id), 0) m FROM combat_log')['m'] ?? 0),
            'ship_log'   => (int) (Database::first('SELECT COALESCE(MAX(id), 0) m FROM ship_log')['m'] ?? 0),
            'inizio'     => (string) Database::first('SELECT NOW() t')['t'],
        ];
    }

    /** Quante righe sintetiche risultano ancora presenti. */
    public static function residui(): int
    {
        $p = self::PREFISSO . '%';
        return (int) (Database::first('SELECT COUNT(*) n FROM users WHERE username LIKE ?', [$p])['n'] ?? 0)
            + (int) (Database::first('SELECT COUNT(*) n FROM players WHERE handle LIKE ?', [$p])['n'] ?? 0);
    }
}
