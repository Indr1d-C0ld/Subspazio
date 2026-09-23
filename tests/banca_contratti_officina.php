<?php

declare(strict_types=1);

/**
 * Banca, contratti, Officina: dove i crediti potevano nascere dal nulla.
 *
 * Tre difetti dell'audit del 23/09/2026, tutti dello stesso tipo — denaro
 * pagato due volte, o pagato per un tempo in cui non c'era. Ogni prova e'
 * costruita per fallire contro il codice di prima.
 */

use App\Core\Database;
use App\Game\Bank;
use App\Game\Contracts;
use App\Game\Industry;

return static function (): void {
    $contratti = [];
    $lavori = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $crediti = static fn (int $pid): int => (int) Database::first('SELECT credits FROM players WHERE id = ?', [$pid])['credits'];

    /** N processi separati, stessa azione, stesso istante. Restituisce quanti sono riusciti. */
    $corsa = static function (int $n, string $azione, int ...$arg): int {
        $t0 = microtime(true) + 1.2;
        $proc = [];
        for ($i = 0; $i < $n; $i++) {
            $proc[] = proc_open(
                array_merge(['php', dirname(__DIR__) . '/tests/_corsa.php', $azione, (string) $t0], array_map('strval', $arg)),
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipe
            );
        }
        $ok = 0;
        foreach ($proc as $ph) {
            if (is_resource($ph) && proc_close($ph) === 0) {
                $ok++;
            }
        }
        return $ok;
    };

    try {
        // --- banca ----------------------------------------------------------

        Esito::sezione('Banca — gli interessi valgono dal momento del versamento');

        Esito::scenario('10 milioni versati su un conto fermo da un mese');
        [$p] = Finti::comandante(10_000_000, [], $sd);
        Database::run(
            'INSERT INTO bank_accounts (player_id, balance, last_interest_at) VALUES (?, 0, DATE_SUB(NOW(), INTERVAL 30 DAY))',
            [(int) $p['id']]
        );
        $r = Bank::deposit($p, 10_000_000);
        $saldo = (int) Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [(int) $p['id']])['balance'];
        Esito::verifica('il versamento riesce', !empty($r['ok']), (string) ($r['error'] ?? ''));
        // Prima l'orologio degli interessi non si spostava col versamento: il
        // primo passaggio del clock pagava un mese intero, circa +1,6 milioni.
        Esito::uguale('il saldo e\' esattamente il versato', 10_000_000, $saldo);
        Bank::accrueAll();
        $dopo = (int) Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [(int) $p['id']])['balance'];
        Esito::uguale('e il clock non paga interessi per un tempo passato', 10_000_000, $dopo);

        Esito::scenario('gli interessi non cancellano un prelievo concluso nel frattempo');
        [$p2] = Finti::comandante(0, [], $sd);
        Database::run(
            'INSERT INTO bank_accounts (player_id, balance, last_interest_at) VALUES (?, 1000000, DATE_SUB(NOW(), INTERVAL 2 HOUR))',
            [(int) $p2['id']]
        );
        // la fotografia che il clock avrebbe preso PRIMA del prelievo
        $vecchia = Database::first('SELECT * FROM bank_accounts WHERE player_id = ?', [(int) $p2['id']]);
        Bank::withdraw(Database::first('SELECT * FROM players WHERE id = ?', [(int) $p2['id']]), 1_000_000);
        $accrue = new ReflectionMethod(Bank::class, 'accrue');
        $accrue->invoke(null, $vecchia);   // il clock scrive con la fotografia vecchia
        $s2 = (int) Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [(int) $p2['id']])['balance'];
        // Prima il saldo tornava a 1.000.083: il prelievo era annullato e il
        // milione restava anche a bordo.
        Esito::verifica('il saldo resta quello dopo il prelievo', $s2 < 1000, "saldo {$s2}");
        Esito::verifica('e il milione e\' a bordo una volta sola', $crediti((int) $p2['id']) >= 1_000_000 && $crediti((int) $p2['id']) < 1_001_000);

        // --- contratti ------------------------------------------------------

        Esito::sezione('Contratti — la cauzione si paga una volta sola');

        [$mand] = Finti::comandante(0, [], $sd);
        [$bers] = Finti::comandante(0, [], $sd);
        $nuovo = static function (string $kind, int $reward, ?string $scade = null) use (&$contratti, $mand, $bers, $sd): int {
            Database::run(
                "INSERT INTO contracts (kind, issuer_player_id, target_player_id, commodity, qty, sector_id, reward, status, expires_at)
                 VALUES (?, ?, ?, 'ore', 10, ?, ?, 'open', ?)",
                [$kind, (int) $mand['id'], $kind === 'bounty' ? (int) $bers['id'] : null, $sd, $reward, $scade]
            );
            $id = Database::lastInsertId();
            $contratti[] = $id;
            return $id;
        };

        Esito::scenario('tre annulli simultanei dello stesso contratto');
        // Processi separati che partono allo stesso istante: leggono tutti
        // «aperto» prima che uno solo abbia scritto. E' il doppio clic da due
        // sessioni; in sequenza il difetto non si vede.
        $k = $nuovo('bounty', 50_000);
        $prima = $crediti((int) $mand['id']);
        $riusciti = $corsa(3, 'annulla_contratto', (int) $mand['id'], $k);
        Esito::uguale('uno solo va a buon fine', 1, $riusciti);
        Esito::uguale('rimborso incassato una volta', 50_000, $crediti((int) $mand['id']) - $prima);

        Esito::scenario('annullo e scadenza nello stesso istante');
        $k2 = $nuovo('bounty', 30_000, date('Y-m-d H:i:s', time() - 60));
        $prima = $crediti((int) $mand['id']);
        Contracts::cancel(Database::first('SELECT * FROM players WHERE id = ?', [(int) $mand['id']]), $k2);
        Contracts::expireDue();
        Esito::uguale('il rimborso arriva una volta sola', 30_000, $crediti((int) $mand['id']) - $prima);

        Esito::scenario('la chiusura e\' vincolata allo stato «aperto»');
        // Prova diretta del vincolo: un contratto gia' chiuso non si chiude
        // di nuovo, qualunque percorso ci arrivi.
        $k3 = $nuovo('bounty', 10_000);
        Database::run("UPDATE contracts SET status = 'cancelled' WHERE id = ?", [$k3]);
        $prima = $crediti((int) $mand['id']);
        Contracts::expireDue();
        Contracts::onPlayerKilled((int) $bers['id'], (int) $mand['id']);
        Esito::uguale('nessun pagamento su un contratto gia\' chiuso', 0, $crediti((int) $mand['id']) - $prima);

        // --- officina -------------------------------------------------------

        Esito::sezione('Officina — un lavoro si annulla o si consegna, non entrambe le cose');

        [$art, $naveArt] = Finti::comandante(0, [], $sd);
        $lavoro = static function (int $pid, array $costo, int $pronto = 3600) use (&$lavori): int {
            Database::run(
                "INSERT INTO craft_jobs (player_id, recipe_key, item_key, item_name, rarity, cost, ready_at)
                 VALUES (?, 'r_prova', (SELECT ckey FROM item_types LIMIT 1), '__test_modulo', 'civ', ?, DATE_ADD(NOW(), INTERVAL ? SECOND))",
                [$pid, json_encode($costo), $pronto]
            );
            $id = Database::lastInsertId();
            $lavori[] = $id;
            return $id;
        };

        Esito::scenario('tre annulli simultanei dello stesso lavoro');
        $j = $lavoro((int) $art['id'], ['credits' => 30_000]);
        $prima = $crediti((int) $art['id']);
        $riusciti = $corsa(3, 'annulla_lavoro', (int) $art['id'], $j);
        Esito::uguale('uno solo va a buon fine', 1, $riusciti);
        Esito::uguale('rimborso incassato una volta', 30_000, $crediti((int) $art['id']) - $prima);

        Esito::scenario('il carico torna in stiva solo allo StarDock e se c\'e\' posto');
        $fuori = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 0 LIMIT 1')['id'];
        Database::run('UPDATE players SET sector_id = ? WHERE id = ?', [$fuori, (int) $art['id']]);
        $j2 = $lavoro((int) $art['id'], ['cargo_ore' => 30]);
        $rf = Industry::cancelJob(Database::first('SELECT * FROM players WHERE id = ?', [(int) $art['id']]), $j2);
        Esito::verifica('lontano dall\'Officina si rifiuta', empty($rf['ok']), (string) ($rf['error'] ?? ''));
        Database::run('UPDATE players SET sector_id = ? WHERE id = ?', [$sd, (int) $art['id']]);
        Database::run('UPDATE ships SET hold_organics = holds_total WHERE id = ?', [(int) $naveArt['id']]);
        $rp = Industry::cancelJob(Database::first('SELECT * FROM players WHERE id = ?', [(int) $art['id']]), $j2);
        Esito::verifica('a stive piene si rifiuta', empty($rp['ok']), (string) ($rp['error'] ?? ''));
        $n = Database::first('SELECT holds_total, hold_ore + hold_organics + hold_equipment + hold_colonists AS usate FROM ships WHERE id = ?', [(int) $naveArt['id']]);
        Esito::verifica('e la stiva non supera mai la capienza', (int) $n['usate'] <= (int) $n['holds_total'], "{$n['usate']}/{$n['holds_total']}");

        Esito::scenario('un lavoro senza proprietario non blocca le consegne degli altri');
        $orfano = $lavoro(2147480000, ['credits' => 1], -7200);   // il piu' vecchio: sarebbe il primo
        $buono  = $lavoro((int) $art['id'], ['credits' => 1], -60);
        $prima = (int) Database::first("SELECT COUNT(*) n FROM player_items WHERE player_id = ?", [(int) $art['id']])['n'];
        $out = Industry::craftJobsTick();
        $dopo = (int) Database::first("SELECT COUNT(*) n FROM player_items WHERE player_id = ?", [(int) $art['id']])['n'];
        Esito::verifica('il ciclo non si interrompe', !isset($out['error']), (string) ($out['error'] ?? ''));
        Esito::uguale('il modulo dell\'altro giocatore arriva', 1, $dopo - $prima);
        Esito::verifica('e il lavoro orfano viene tolto', Database::first('SELECT 1 x FROM craft_jobs WHERE id = ?', [$orfano]) === null);
    } finally {
        foreach ($contratti as $id) {
            Database::run('DELETE FROM contracts WHERE id = ?', [$id]);
        }
        foreach ($lavori as $id) {
            Database::run('DELETE FROM craft_jobs WHERE id = ?', [$id]);
        }
        Database::run("DELETE bk FROM bank_accounts bk LEFT JOIN players p ON p.id = bk.player_id WHERE p.id IS NULL OR p.handle LIKE '\\_\\_test\\_%'");
        Esito::uguale(
            'nessun contratto o lavoro di prova lasciato indietro',
            0,
            (int) Database::first("SELECT (SELECT COUNT(*) FROM craft_jobs WHERE item_name = '__test_modulo') n")['n']
        );
    }
};
