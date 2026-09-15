<?php

declare(strict_types=1);

/**
 * Difese — reperti 09, 10, 11 e 12 dell'audit del 2026-09-15.
 *
 * 09: il login saltava del tutto password_verify() per gli utenti
 *     inesistenti, e con Argon2id a 64 MB la differenza di tempo si misura
 *     dall'esterno: un modo per scoprire chi è iscritto.
 * 10: il parametro `back` della rimozione immagini finiva dritto nell'header
 *     Location, innocuo solo grazie al prefisso di deploy.
 * 11: nessun freno sulle azioni di gioco.
 * 12: lo scudo novizio era verificato nel solo attacco diretto; mine e caccia
 *     altrui potevano comunque distruggere un nuovo arrivato.
 */

use App\Auth\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Game\Combat;
use App\Game\Universe;

return static function (): void {
    // --- Reperto 12 -------------------------------------------------------

    Esito::sezione('Reperto 12 — lo scudo novizio copre mine e caccia');

    // Un settore fuori Fedspace, altrimenti onEnterSector esce subito.
    $settore = Database::first(
        'SELECT id FROM sectors WHERE is_fedspace = 0 AND is_stardock = 0 ORDER BY RAND() LIMIT 1'
    );
    if ($settore === null) {
        Esito::verifica('nessun settore fuori Fedspace: prova saltata', true);
        return;
    }
    $sid = (int) $settore['id'];

    [$aggressore] = Finti::comandante(0, [], $sid);
    $idAgg = (int) $aggressore['id'];

    $mineId = null;
    $caccia = false;
    try {
        // Un veterano semina mine e schiera caccia offensivi nel settore.
        Database::run(
            "INSERT INTO sector_mines (sector_id, owner_player_id, type, qty) VALUES (?, ?, 'armid', ?)",
            [$sid, $idAgg, 500]
        );
        $mineId = Database::lastInsertId();
        Database::run(
            "INSERT INTO sector_fighters (sector_id, owner_player_id, qty, mode, toll) VALUES (?, ?, ?, 'offensive', 0)",
            [$sid, $idAgg, 5000]
        );
        $caccia = true;

        // --- il novizio protetto -----------------------------------------
        Esito::scenario('un novizio protetto entra in un settore minato e presidiato');
        [$novizio, $naveN] = Finti::comandante(1000, ['fighters' => 10, 'shields' => 10], $sid);
        Database::run(
            'UPDATE players SET protected_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?',
            [(int) $novizio['id']]
        );
        [$novizio, $naveN] = Finti::ricarica((int) $novizio['id']);

        $esito = Combat::onEnterSector($novizio, $naveN);
        $scudo = false;
        foreach ($esito['events'] as $ev) {
            if (str_contains($ev, 'Protezione novizio')) {
                $scudo = true;
            }
        }
        Esito::verifica('non viene distrutto', empty($esito['destroyed']));
        Esito::uguale('la nave è intatta', 10, Finti::stiva((int) $naveN['id'], 'fighters'));
        Esito::verifica('e gli viene spiegato perché non è successo nulla', $scudo);
        Esito::verifica(
            'il campo minato è ancora lì, non consumato da lui',
            Database::first('SELECT 1 x FROM sector_mines WHERE id = ?', [$mineId]) !== null
        );

        // --- lo stesso comandante, senza scudo ----------------------------
        Esito::scenario('lo stesso comandante, a protezione scaduta');
        Database::run('UPDATE players SET protected_until = NULL WHERE id = ?', [(int) $novizio['id']]);
        [$esposto, $naveE] = Finti::ricarica((int) $novizio['id']);

        $esito2 = Combat::onEnterSector($esposto, $naveE);
        $colpito = false;
        foreach ($esito2['events'] as $ev) {
            if (str_contains($ev, 'Campo minato') || str_contains($ev, 'caccia')) {
                $colpito = true;
            }
        }
        // Questa è la controprova: senza di essa il test passerebbe anche se
        // lo scudo non c'entrasse nulla e il settore fosse semplicemente inerte.
        Esito::verifica('senza scudo le difese lo colpiscono davvero', $colpito);
    } finally {
        if ($mineId !== null) {
            Database::run('DELETE FROM sector_mines WHERE id = ?', [$mineId]);
        }
        if ($caccia) {
            Database::run('DELETE FROM sector_fighters WHERE owner_player_id = ?', [$idAgg]);
        }
        Database::run('DELETE FROM sector_mines WHERE owner_player_id = ?', [$idAgg]);
        Universe::forget();
    }

    // --- Reperto 09 -------------------------------------------------------

    Esito::sezione('Reperto 09 — il login non rivela chi è iscritto');

    $misura = static function (string $login) : float {
        $t = microtime(true);
        Auth::attempt($login, 'password-sicuramente-sbagliata');
        return (microtime(true) - $t) * 1000;
    };

    // Serve un utente con un hash VERO: i comandanti sintetici nascono con
    // un segnaposto, e password_verify() su una stringa non valida torna
    // subito, falsando il confronto (preso in castagna proprio così).
    [$conHash] = Finti::comandante(0);
    Database::run(
        'UPDATE users SET password_hash = ? WHERE id = ?',
        [password_hash('una password qualunque', PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]),
         (int) $conHash['user_id']]
    );
    $esistente = (string) Database::first('SELECT username FROM users WHERE id = ?', [(int) $conHash['user_id']])['username'];

    // scalda: il primo giro paga la connessione e il calcolo dell'hash fittizio
    $misura('__test_inesistente');

    Esito::scenario('tempo di risposta per un utente inesistente contro uno reale');
    $tInesistente = min($misura('__test_nessuno_1'), $misura('__test_nessuno_2'));
    $tEsistente   = min($misura($esistente), $misura($esistente));
    $rapporto = $tEsistente > 0 ? $tInesistente / $tEsistente : 0;

    Esito::verifica(
        'i due rami costano circa lo stesso (scarto sotto il 50%)',
        $rapporto > 0.5 && $rapporto < 1.5,
        sprintf('inesistente %.1f ms, reale %.1f ms, rapporto %.2f', $tInesistente, $tEsistente, $rapporto)
    );
    Esito::verifica(
        'entrambi i rami fanno davvero il lavoro di hashing',
        $tInesistente > 5.0,
        sprintf('%.1f ms', $tInesistente)
    );

    // Il confronto qui sopra, da solo, non basta: dentro un processo CLI una
    // cache statica maschererebbe un hash ricalcolato, mentre sotto PHP-FPM
    // ogni richiesta riparte da zero. È esattamente l'errore preso in questa
    // sessione — l'hash fittizio veniva *calcolato* e poi verificato, cioè
    // lavoro doppio rispetto al ramo con l'utente vero: 370 ms contro 210.
    Esito::scenario("l'hash di riferimento è precalcolato, non generato a ogni richiesta");
    $metodo = new ReflectionMethod(Auth::class, 'hashFittizio');
    $metodo->setAccessible(true);

    $t = microtime(true);
    $primo = $metodo->invoke(null);
    $costoPrimaChiamata = (microtime(true) - $t) * 1000;
    $secondo = $metodo->invoke(null);

    Esito::verifica(
        'non costa nulla ottenerlo',
        $costoPrimaChiamata < 5.0,
        sprintf('%.3f ms', $costoPrimaChiamata)
    );
    Esito::uguale('è sempre lo stesso, quindi non è un hash fresco', $primo, $secondo);
    Esito::verifica(
        'ed è un Argon2id valido con i parametri di produzione',
        str_starts_with($primo, '$argon2id$v=19$m=65536,t=4,p=2$'),
        substr($primo, 0, 34)
    );

    // --- Reperto 10 -------------------------------------------------------

    Esito::sezione('Reperto 10 — destinazioni di ritorno vincolate');

    $sicura = new ReflectionMethod(App\Controllers\AdminController::class, 'destinazioneSicura');
    $sicura->setAccessible(true);
    $vaglia = static fn (string $back): string => $sicura->invoke(null, $back, '/admin');

    Esito::scenario('percorsi interni legittimi');
    Esito::uguale('un percorso semplice passa', '/admin/gioco', $vaglia('/admin/gioco'));
    Esito::uguale('anche con àncora', '/admin/gioco#giocatori', $vaglia('/admin/gioco#giocatori'));

    Esito::scenario('tentativi di dirottamento verso l\'esterno');
    foreach ([
        '//sito-esterno.example',
        '///sito-esterno.example',
        'https://sito-esterno.example',
        '/\\sito-esterno.example',
        'javascript:alert(1)',
        '',
    ] as $cattivo) {
        Esito::uguale(
            'respinto: ' . ($cattivo === '' ? '(vuoto)' : $cattivo),
            '/admin',
            $vaglia($cattivo)
        );
    }

    // --- Reperto 11 -------------------------------------------------------

    Esito::sezione('Reperto 11 — freno sulle azioni di gioco');

    Esito::scenario('la manopola è registrata e regolabile dal pannello');
    $riga = Database::first('SELECT cvalue, default_value FROM game_config WHERE ckey = ?', ['limits.player_actions_per_min']);
    Esito::verifica('limits.player_actions_per_min è in game_config', $riga !== null);
    Esito::uguale('con il default previsto', '120', (string) ($riga['default_value'] ?? ''));

    Esito::scenario('la soglia lascia passare un ritmo umano e ferma il martellamento');
    $chiave = 'azioni:__test_' . bin2hex(random_bytes(4));
    try {
        $passate = 0;
        for ($i = 0; $i < 15; $i++) {
            if (RateLimiter::hit($chiave, 10, 60)) {
                $passate++;
            }
        }
        Esito::uguale('oltre la soglia le richieste vengono respinte', 10, $passate);
    } finally {
        RateLimiter::clear($chiave);
    }

    $soglia = App\Game\GameConfig::int('limits.player_actions_per_min', 120);
    Esito::verifica(
        'la soglia reale resta larga rispetto al polling di sfondo (~10/min)',
        $soglia >= 60,
        "{$soglia}/min"
    );
};
