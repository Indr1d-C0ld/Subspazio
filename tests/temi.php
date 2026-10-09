<?php

declare(strict_types=1);

/**
 * Temi grafici (09/10/2026): catalogo, fogli e caratteri coerenti fra loro,
 * scelta che segue l'account e il cookie, ritorno solo a pagine interne.
 */

use App\Core\Database;
use App\Core\Temi;

return static function (): void {
    $radice = (string) ($GLOBALS['__project_root'] ?? dirname(__DIR__));

    Esito::sezione('Temi — catalogo, fogli e caratteri');

    Esito::verifica('il tema di base esiste', Temi::valido(Temi::PREDEFINITO));
    Esito::verifica('un nome sconosciuto non e\' un tema', !Temi::valido('../app') && !Temi::valido(''));
    Esito::verifica('il tema di base non ha un foglio suo', Temi::foglio(Temi::PREDEFINITO) === null);

    $mancanti = [];
    $caratteri = [];
    foreach (array_keys(Temi::CATALOGO) as $k) {
        $foglio = Temi::foglio($k);
        if ($foglio === null) {
            continue;
        }
        $css = @file_get_contents($radice . '/assets/' . $foglio);
        if ($css === false || !str_contains($css, 'html[data-tema="' . $k . '"]')) {
            $mancanti[] = $k;
            continue;
        }
        preg_match_all('#url\("\.\./\.\./fonts/([^"]+)"\)#', $css, $m);
        foreach ($m[1] as $f) {
            $caratteri[$f] = $k;
        }
    }
    Esito::uguale('ogni tema ha il suo foglio, che ridefinisce le variabili', [], $mancanti);

    $assenti = array_keys(array_filter($caratteri, static fn ($k, $f) => !is_file($radice . '/assets/fonts/' . $f), ARRAY_FILTER_USE_BOTH));
    Esito::uguale('ogni carattere dichiarato e\' sul server', [], $assenti);
    $senzaLicenza = [];
    foreach (array_keys($caratteri) as $f) {
        $famiglia = preg_replace('/-(var|\d+)\.woff2$/', '', $f);
        if (!is_file($radice . '/assets/fonts/OFL-' . $famiglia . '.txt')) {
            $senzaLicenza[] = $f;
        }
    }
    Esito::uguale('e accanto c\'e\' la sua licenza', [], $senzaLicenza);

    $anteprimeRotte = array_keys(array_filter(Temi::CATALOGO, static fn ($t) => count($t['anteprima']) !== 5
        || array_filter($t['anteprima'], static fn ($c) => preg_match('/^#[0-9a-f]{6}$/i', $c) !== 1) !== []));
    Esito::uguale('ogni anteprima ha cinque colori esadecimali', [], $anteprimeRotte);

    Esito::sezione('Temi — dove torna chi sceglie');

    $ritorno = new ReflectionMethod(\App\Controllers\TemaController::class, 'ritorno');
    Esito::uguale('una pagina del gioco', '/gioco/porto', $ritorno->invoke(null, '/gioco/porto'));
    // Mai fuori dal sito: il campo arriva dal browser.
    Esito::uguale('non un altro sito', '/', $ritorno->invoke(null, '//altro-sito.example/x'));
    Esito::uguale('non un indirizzo intero', '/', $ritorno->invoke(null, 'https://altro-sito.example/'));
    Esito::uguale('non un percorso con trucchi', '/', $ritorno->invoke(null, '/gioco/../../etc'));
    Esito::uguale('con l\'ancora della sezione', '/#temi', $ritorno->invoke(null, '/#temi'));
    Esito::uguale('ma un\'ancora sola, e semplice', '/', $ritorno->invoke(null, '/#temi#"onload'));

    Esito::sezione('Temi — nel notiziario della Federazione');

    // Sei comandanti col Terminale: piu' di quanti giocatori veri ci siano oggi.
    $ids = [];
    for ($i = 0; $i < 6; $i++) {
        [$c] = Finti::comandante(0, []);
        $ids[] = (int) $c['user_id'];
    }
    Database::run('UPDATE users SET tema = ? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge(['muthur'], $ids));
    $titoli = (new ReflectionMethod(\App\Game\FedNews::class, 'compose'))->invoke(null);
    $moda = (string) (array_values(array_filter($titoli, static fn ($t) => str_starts_with($t, 'Moda di plancia:')))[0] ?? '');
    Esito::verifica('racconta il tema piu\' scelto', str_contains($moda, 'Terminale MU/TH/UR') && str_contains($moda, 'comandanti'), $moda);
    $avvisi = (new ReflectionMethod(\App\Game\FedNews::class, 'avvisiDiServizio'))->invoke(null);
    $temiNomi = array_column(Temi::CATALOGO, 'nome');
    Esito::verifica('e fra gli avvisi di servizio c\'e\' quello sui temi, con tutti i nomi',
        array_filter($avvisi, static fn ($a) => array_filter($temiNomi, static fn ($n) => !str_contains($a, $n)) === []) !== []);

    Esito::sezione('Temi — nell\'aiuto contestuale');

    $tuttiINomi = static fn (string $testo): bool => array_filter($temiNomi, static fn ($n) => !str_contains($testo, $n)) === [];
    foreach (['profilo.tema', 'tema.selettore'] as $chiave) {
        $testo = (string) \App\Game\Help::get($chiave);
        Esito::verifica("«{$chiave}» elenca tutti i temi, dal catalogo", !str_contains($testo, '{temi}') && $tuttiINomi($testo), $testo);
    }
    // La mappa disegna adiacenti e prede col colore del tema, e i pericoli
    // non li disegna affatto: «anello ciano», «rosso = pericolo» erano falsi.
    $mappa = (string) \App\Game\Help::get('plancia.mappa');
    Esito::verifica('l\'aiuto della mappa non promette colori fissi', !preg_match('/ciano|ambra|rosso/i', $mappa), $mappa);

    Esito::sezione('Temi — la scelta segue l\'account');

    [$p] = Finti::comandante(0, []);
    $uid = (int) $p['user_id'];
    $utente = Database::first('SELECT * FROM users WHERE id = ?', [$uid]);
    $auth = static function (?array $u): void {
        (function () use ($u) {
            self::$resolved = true;
            self::$cachedUser = $u;
        })->bindTo(null, \App\Auth\Auth::class)();
    };
    $cookie = $_COOKIE[Temi::COOKIE] ?? null;
    try {
        $auth(null);
        unset($_COOKIE[Temi::COOKIE]);
        Esito::uguale('senza scelta, il tema di base', Temi::PREDEFINITO, Temi::attuale());
        Temi::scegli('neon');
        Esito::uguale('chi non ha fatto l\'accesso lo tiene nel cookie', 'neon', Temi::attuale());
        Esito::verifica('un tema inventato non si sceglie', !Temi::scegli('inventato'));

        $auth($utente);
        Temi::scegli('lcars');
        Esito::uguale('chi ha fatto l\'accesso lo salva sull\'account', 'lcars',
            (string) Database::first('SELECT tema FROM users WHERE id = ?', [$uid])['tema']);
        $_COOKIE[Temi::COOKIE] = 'cintura';   // un altro dispositivo, un altro cookie
        Esito::uguale('e l\'account vale su ogni dispositivo', 'lcars', Temi::attuale());
    } finally {
        $auth(null);
        (function () {
            self::$resolved = false;
        })->bindTo(null, \App\Auth\Auth::class)();
        if ($cookie === null) {
            unset($_COOKIE[Temi::COOKIE]);
        } else {
            $_COOKIE[Temi::COOKIE] = $cookie;
        }
    }
};
