<?php

declare(strict_types=1);

/**
 * Due richieste dello stesso comandante nello stesso istante.
 *
 * Le funzioni di gioco ricevono $player e $ship come fotografia scattata a
 * inizio richiesta. Controllare la capienza su quella copia e poi scrivere
 * senza vincolo lascia passare due richieste che spendono lo stesso saldo.
 * L'audit di settembre aveva costruito App\Game\Wallet per questo, ma lo
 * aveva collegato ai soli crediti: i turni, e le risorse a colpo singolo
 * sparse nei settori, erano rimaste al vecchio schema.
 *
 * Quasi tutte le prove qui non hanno bisogno di concorrenza vera: basta
 * passare alla funzione una fotografia VECCHIA — che e' esattamente cio' che
 * la concorrenza produce — e guardare se il codice se ne accorge. Una sola,
 * quella sul deposito, lancia processi separati con una barriera comune.
 */

use App\Core\Database;
use App\Game\Navigation;
use App\Game\PlayerService;
use App\Game\SectorFeatures;
use App\Game\Wallet;

return static function (): void {
    $creati = [];
    $feature = 0;

    /** Comandante sintetico in un settore scelto, senza rifornimento automatico. */
    $comandante = static function (int $settore, int $turni) use (&$creati): array {
        Database::run(
            'INSERT INTO users (username, email, password_hash, status, role) VALUES (?, ?, ?, ?, ?)',
            ['__test_c' . count($creati), '__test_c' . count($creati) . '@invalid.test', 'x', 'active', 'player']
        );
        $uid = Database::lastInsertId();
        // turns_reset_on = oggi: il rifornimento giornaliero non deve rimpinguare
        // la cassa a meta' prova, o si misura uno scenario che non esiste.
        Database::run(
            'INSERT INTO players (user_id, handle, sector_id, credits, turns, turns_reset_on)
             VALUES (?, ?, ?, 0, ?, CURDATE())',
            [$uid, '__test_cp' . count($creati), $settore, $turni]
        );
        $pid = Database::lastInsertId();
        Database::run(
            'INSERT INTO ships (player_id, type_key, name, sector_id, holds_total) VALUES (?, ?, ?, ?, 300)',
            [$pid, 'merchant_cruiser', '__test_cs' . count($creati), $settore]
        );
        $sid = Database::lastInsertId();
        Database::run('UPDATE players SET ship_id = ? WHERE id = ?', [$sid, $pid]);
        $creati[] = ['u' => $uid, 'p' => $pid, 's' => $sid];
        return [$uid, $pid, $sid];
    };

    try {
        // --- il portafoglio sa gia' scalare i turni -------------------------

        Esito::sezione('Concorrenza — l\'addebito dei turni');

        Esito::scenario('il vincolo sta nella WHERE, non nella fotografia');
        $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
        [, $pid] = $comandante($sd, 1);
        Esito::verifica('il primo addebito passa', Wallet::charge($pid, ['turns' => 1]));
        Esito::verifica('il secondo no: la cassa e\' vuota', !Wallet::charge($pid, ['turns' => 1]));
        Esito::uguale(
            'e i turni non sono andati sotto zero',
            0,
            (int) Database::first('SELECT turns FROM players WHERE id = ?', [$pid])['turns']
        );

        // --- il warp ---------------------------------------------------------

        Esito::sezione('Concorrenza — il warp');

        Esito::scenario('una fotografia vecchia non compra uno spostamento');
        // Settore con un'uscita, senza NPC: si misura il movimento, non un incontro.
        $part = null;
        foreach (Database::all(
            'SELECT from_sector s FROM warps GROUP BY from_sector HAVING COUNT(*) >= 1 LIMIT 60'
        ) as $r) {
            $s = (int) $r['s'];
            if ((int) Database::first('SELECT COUNT(*) n FROM npcs WHERE sector_id = ?', [$s])['n'] === 0) {
                $part = $s;
                break;
            }
        }
        Esito::verifica('c\'e\' un settore adatto da cui partire', $part !== null);
        if ($part !== null) {
            $dest = (int) Database::first('SELECT to_sector FROM warps WHERE from_sector = ? LIMIT 1', [$part])['to_sector'];
            [, $pid2, $sid2] = $comandante($part, 0);

            $vecchio = Database::first('SELECT * FROM players WHERE id = ?', [$pid2]);
            $vecchio['turns'] = 99;          // la cassa che il giocatore CREDE di avere
            $nave = PlayerService::ship($sid2);

            $res = Navigation::move($vecchio, $nave, $dest);
            $dopoP = Database::first('SELECT sector_id, turns, total_warps FROM players WHERE id = ?', [$pid2]);
            $dopoS = Database::first('SELECT sector_id FROM ships WHERE id = ?', [$sid2]);

            Esito::verifica('il warp viene rifiutato', empty($res['ok']), (string) ($res['error'] ?? 'nessun errore'));
            // Il guasto vero non erano i turni — quelli la guardia li salvava
            // gia' — ma tutto cio' che proseguiva come se fossero stati pagati:
            // la nave si spostava e il comandante restava indietro.
            Esito::uguale('il comandante non si e\' mosso', $part, (int) $dopoP['sector_id']);
            Esito::uguale('e nemmeno la nave', $part, (int) $dopoS['sector_id']);
            Esito::verifica(
                'comandante e nave restano nello stesso settore',
                (int) $dopoP['sector_id'] === (int) $dopoS['sector_id']
            );
            Esito::uguale('nessun warp contato', 0, (int) $dopoP['total_warps']);
            Esito::uguale(
                'nessuna riga nel registro degli spostamenti',
                0,
                (int) Database::first('SELECT COUNT(*) n FROM move_log WHERE player_id = ?', [$pid2])['n']
            );
            Esito::uguale('i turni restano a zero', 0, (int) $dopoP['turns']);
        }

        // --- il deposito a colpo singolo -------------------------------------

        Esito::sezione('Concorrenza — le risorse a colpo singolo');

        Esito::scenario('tre raccolte simultanee dello stesso deposito');
        $sec = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 0 LIMIT 1')['id'];
        [, $pid3, $sid3] = $comandante($sec, 500);
        Database::run(
            "INSERT INTO sector_features (sector_id, kind, subtype, richness, data, spawned_at, depleted)
             VALUES (?, 'cache', 'minerale', 5, '{}', NOW(), 0)",
            [$sec]
        );
        $feature = Database::lastInsertId();
        Database::run(
            'INSERT INTO player_feature_state (player_id, feature_id, discovered_at, progress, resolved) VALUES (?, ?, NOW(), 0, 0)',
            [$pid3, $feature]
        );

        $worker = dirname(__DIR__) . '/tests/_corsa_deposito.php';
        $t0 = microtime(true) + 1.2;
        $proc = [];
        for ($i = 0; $i < 3; $i++) {
            $proc[] = proc_open(
                ['php', $worker, (string) $sid3, (string) $pid3, (string) $feature, (string) $t0],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipe
            );
        }
        $riuscite = 0;
        foreach ($proc as $ph) {
            if (is_resource($ph) && proc_close($ph) === 0) {
                $riuscite++;
            }
        }

        // Le Leghe sono l'unica ricompensa senza casualita': 45 * ricchezza 5 *
        // 0.4 = 90 esatte a raccolta. Contarle dice quante ne sono passate.
        $sal = (int) Database::first('SELECT salvage FROM players WHERE id = ?', [$pid3])['salvage'];
        Esito::uguale('una sola raccolta va a buon fine', 1, $riuscite);
        Esito::uguale('e il bottino e\' quello di una sola', 90, $sal);
        Esito::uguale(
            'il deposito risulta esaurito',
            1,
            (int) Database::first('SELECT depleted FROM sector_features WHERE id = ?', [$feature])['depleted']
        );

        // --- le chiavi che comandano davvero ---------------------------------

        Esito::sezione('Concorrenza — l\'interruttore delle iscrizioni');

        Esito::scenario('la manopola del pannello e\' quella che il codice legge');
        // Il guasto era questo: la chiave vive in game_config (dove l'admin la
        // vede e la cambia) ma veniva letta da Config, che pesca dal file di
        // configurazione — dove non c'e' mai stata.
        Esito::verifica(
            'registration.open esiste in game_config',
            Database::first('SELECT 1 x FROM game_config WHERE ckey = ?', ['registration.open']) !== null
        );
        Esito::verifica(
            'il codice la legge da GameConfig, non da Config',
            str_contains(
                (string) file_get_contents(dirname(__DIR__) . '/src/Controllers/AuthController.php'),
                "GameConfig::str('registration.open'"
            )
        );
        // Nascondere il modulo non chiude niente: l'invio diretto va fermato.
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/Controllers/AuthController.php');
        $posDopo = strpos($ctrl, 'public function register(');
        Esito::verifica(
            'anche l\'invio diretto passa dal controllo',
            $posDopo !== false && str_contains(substr($ctrl, $posDopo, 600), 'iscrizioniChiuse()')
        );
    } finally {
        if ($feature > 0) {
            Database::run('DELETE FROM player_feature_state WHERE feature_id = ?', [$feature]);
            Database::run('DELETE FROM sector_features WHERE id = ?', [$feature]);
        }
        foreach ($creati as $c) {
            Database::run('DELETE FROM move_log WHERE player_id = ?', [$c['p']]);
            Database::run('DELETE FROM player_visited_sectors WHERE player_id = ?', [$c['p']]);
            Database::run('DELETE FROM ships WHERE id = ?', [$c['s']]);
            Database::run('DELETE FROM players WHERE id = ?', [$c['p']]);
            Database::run('DELETE FROM users WHERE id = ?', [$c['u']]);
        }
        Esito::uguale(
            'nessun comandante di prova lasciato indietro',
            0,
            (int) Database::first("SELECT COUNT(*) n FROM users WHERE username LIKE '\\_\\_test\\_c%'")['n']
        );
    }
};
