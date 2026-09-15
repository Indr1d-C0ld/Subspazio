<?php

declare(strict_types=1);

/**
 * Messaggi flash e refill dei turni — reperti 07 e 08 dell'audit del
 * 2026-09-15.
 *
 * 07: il ciclo dei flash avanzava a ogni avvio di sessione, comprese le
 *     chiamate di sfondo. Il polling della radio, ogni ~6 secondi, poteva
 *     consumare il messaggio destinato alla pagina — circa una azione su
 *     venti restava senza la propria conferma.
 *
 * 08: il refill giornaliero scriveva il valore pieno senza vincolare la data
 *     sulla riga, quindi una richiesta con fotografia vecchia poteva
 *     rimborsare turni gia' spesi.
 */

use App\Core\Database;
use App\Core\Session;
use App\Game\TurnManager;

return static function (): void {
    // --- Reperto 07 -------------------------------------------------------

    Esito::sezione('Reperto 07 — i flash non li mangia il polling');

    // Il confine fra due richieste, in produzione, e' Session::start().
    // Qui lo si evoca direttamente: e' l'unico modo di simulare piu'
    // richieste dentro un solo processo.
    $nuovaRichiesta = static function (): void {
        $m = new ReflectionMethod(Session::class, 'apriFlash');
        $m->setAccessible(true);
        $m->invoke(null);
    };

    $_SESSION = [];
    $nuovaRichiesta();

    Esito::scenario('la richiesta POST deposita un messaggio e reindirizza');
    Session::flash('success', 'Immagine rimossa.');
    Esito::uguale('il messaggio è in attesa', 'Immagine rimossa.', $_SESSION['_flash_next']['success'] ?? null);

    Esito::scenario('nel frattempo parte il polling di /api/alerts, che non renderizza nulla');
    $nuovaRichiesta();          // la chiamata di sfondo avvia la sessione...
    // ...e non legge alcun flash, perché serve solo JSON
    Esito::uguale(
        'il polling non ha toccato il messaggio',
        'Immagine rimossa.',
        $_SESSION['_flash_next']['success'] ?? null
    );

    Esito::scenario('arriva la pagina di destinazione');
    $nuovaRichiesta();
    Esito::uguale('la pagina vede il messaggio', 'Immagine rimossa.', Session::getFlash('success'));
    Esito::verifica('e lo consuma, così non ricompare', !isset($_SESSION['_flash_next']['success']));

    Esito::scenario('la pagina successiva non deve rivederlo');
    $nuovaRichiesta();
    Esito::uguale('nessun messaggio residuo', null, Session::getFlash('success'));

    Esito::scenario('più letture nella stessa pagina (layout legge success, error ed errors)');
    $_SESSION = [];
    $nuovaRichiesta();
    Session::flash('error', 'Crediti insufficienti.');
    $nuovaRichiesta();
    $primo = Session::getFlash('error');
    $secondo = Session::getFlash('error');
    Esito::uguale('la prima lettura vede il messaggio', 'Crediti insufficienti.', $primo);
    Esito::uguale('anche la seconda, nella stessa richiesta', 'Crediti insufficienti.', $secondo);

    Esito::scenario('un flash scritto durante la richiesta è per la prossima, non per questa');
    $_SESSION = [];
    $nuovaRichiesta();
    Session::flash('success', 'vecchio');
    $nuovaRichiesta();
    Session::getFlash('success');                 // consuma il vecchio
    Session::flash('success', 'nuovo');           // ne scrive uno per dopo
    Esito::uguale('il nuovo resta in attesa', 'nuovo', $_SESSION['_flash_next']['success'] ?? null);
    $nuovaRichiesta();
    Esito::uguale('e arriva alla richiesta seguente', 'nuovo', Session::getFlash('success'));

    Esito::scenario('i vecchi input dei form seguono la stessa regola');
    $_SESSION = [];
    $nuovaRichiesta();
    Session::flashInput(['login' => 'comandante', 'password' => 'segreta']);
    Esito::verifica('la password non viene mai conservata', !isset($_SESSION['_flash_next']['_old']['password']));
    $nuovaRichiesta();                            // il polling passa di qui
    Esito::verifica('il polling non li consuma', isset($_SESSION['_flash_next']['_old']));
    $nuovaRichiesta();
    Esito::uguale('il form li ritrova', 'comandante', Session::old('login'));

    $_SESSION = [];

    // --- Reperto 08 -------------------------------------------------------

    Esito::sezione('Reperto 08 — il refill non rimborsa turni già spesi');

    $perDay = TurnManager::perDay();
    $oggi = TurnManager::gameDay();
    $ieri = date('Y-m-d', strtotime($oggi . ' -1 day'));

    [$p] = Finti::comandante(1000);
    $pid = (int) $p['id'];
    Database::run('UPDATE players SET turns = 0, turns_reset_on = ? WHERE id = ?', [$ieri, $pid]);
    [$p] = Finti::ricarica($pid);

    Esito::scenario("il comandante entra nel nuovo giorno con 0 turni (ultimo refill {$ieri})");
    $stantia = $p;                                 // la fotografia che due richieste concorrenti condividono
    $dopoPrima = TurnManager::sync($stantia);
    Esito::uguale('la prima richiesta ricarica i turni', $perDay, (int) $dopoPrima['turns']);
    Esito::uguale('e annota la data di oggi', $oggi, (string) $dopoPrima['turns_reset_on']);

    Esito::scenario('il giocatore spende 100 turni');
    Database::run('UPDATE players SET turns = turns - 100 WHERE id = ?', [$pid]);
    $attesi = $perDay - 100;
    Esito::uguale('turni rimasti', $attesi, (int) Database::first('SELECT turns FROM players WHERE id = ?', [$pid])['turns']);

    Esito::scenario('arriva la seconda richiesta, che ha ancora in mano la fotografia vecchia');
    $dopoSeconda = TurnManager::sync($stantia);    // stessa fotografia di prima: turns_reset_on = ieri
    $inBanca = (int) Database::first('SELECT turns FROM players WHERE id = ?', [$pid])['turns'];

    // Questa è la prova: prima della correzione qui si tornava a 2500.
    Esito::uguale('i 100 turni spesi NON vengono restituiti', $attesi, $inBanca);
    Esito::uguale('e la funzione riporta il valore vero, non quello immaginato', $attesi, (int) $dopoSeconda['turns']);

    Esito::scenario('il giorno dopo il refill deve invece avvenire');
    Database::run('UPDATE players SET turns_reset_on = ? WHERE id = ?', [$ieri, $pid]);
    [$p2] = Finti::ricarica($pid);
    $rinfrescato = TurnManager::sync($p2);
    Esito::uguale('turni di nuovo al massimo', $perDay, (int) $rinfrescato['turns']);
};
