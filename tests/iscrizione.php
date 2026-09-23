<?php

declare(strict_types=1);

/**
 * Autovalidazione dell'indirizzo e recupero password.
 *
 * Il modello era "approvazione admin": ogni account restava fermo finché
 * qualcuno non lo attivava a mano. Ora chi si iscrive conferma da sé il
 * proprio indirizzo — stesso schema di Atlantik e CthulhuMUD — e
 * l'amministratore esce dalla porta d'ingresso.
 *
 * Nessuna prova spedisce davvero: il trasporto della coda viene sostituito.
 */

use App\Auth\Auth;
use App\Auth\AuthMail;
use App\Core\Database;
use App\Core\Posta;
use App\Game\GameConfig;

return static function (): void {
    $utenti = [];
    $spedite = [];

    /** Crea un iscritto sintetico e restituisce [id, gettone in chiaro]. */
    $iscrivi = static function (string $suffisso) use (&$utenti): array {
        $r = Auth::register([
            'username'         => '__test_i' . $suffisso,
            'email'            => '__test_i' . $suffisso . '@invalid.test',
            'password'         => 'unapasswordlunga',
            'password_confirm' => 'unapasswordlunga',
        ], '127.0.0.1');
        if (empty($r['ok'])) {
            throw new RuntimeException('iscrizione fallita: ' . implode(' ', $r['errors'] ?? []));
        }
        $utenti[] = (int) $r['user_id'];
        return [(int) $r['user_id'], (string) $r['token']];
    };

    try {
        Posta::trasportoDiProva(static function ($a, $o, $c) use (&$spedite) {
            $spedite[] = ['a' => $a, 'oggetto' => $o, 'corpo' => $c];
            return ['ok' => true];
        });

        // --- verifica dell'indirizzo --------------------------------------

        Esito::sezione('Iscrizione — l\'account nasce chiuso');

        Esito::scenario('chi si iscrive non entra finché non conferma');
        [$uid, $token] = $iscrivi('a');
        $u = Database::first('SELECT status, email_verified_at FROM users WHERE id = ?', [$uid]);
        Esito::uguale('lo stato è «pending»', 'pending', (string) $u['status']);
        Esito::verifica('non risulta verificato', $u['email_verified_at'] === null);
        Esito::uguale('il login lo respinge', 'pending', Auth::attempt('__test_ia', 'unapasswordlunga')['code']);

        Esito::scenario('nel database finisce solo l\'impronta del gettone');
        $riga = Database::first('SELECT token_hash, kind FROM user_tokens WHERE user_id = ?', [$uid]);
        Esito::uguale('il tipo è quello giusto', 'verify_email', (string) $riga['kind']);
        Esito::uguale('l\'impronta corrisponde', hash('sha256', $token), (string) $riga['token_hash']);
        Esito::verifica(
            'il gettone in chiaro non compare da nessuna parte',
            Database::first('SELECT 1 x FROM user_tokens WHERE token_hash = ?', [$token]) === null
        );

        Esito::sezione('Iscrizione — la conferma apre la porta');

        Esito::scenario('apertura del collegamento ricevuto');
        $v = Auth::verifyEmail($token, '127.0.0.1');
        $u2 = Database::first('SELECT status, email_verified_at FROM users WHERE id = ?', [$uid]);
        Esito::verifica('la verifica riesce', !empty($v['ok']));
        Esito::uguale('l\'account diventa attivo', 'active', (string) $u2['status']);
        Esito::verifica('e risulta verificato', $u2['email_verified_at'] !== null);
        Esito::uguale('ora il login passa', 'ok', Auth::attempt('__test_ia', 'unapasswordlunga')['code']);

        Esito::scenario('un secondo clic sullo stesso collegamento');
        $v2 = Auth::verifyEmail($token, '127.0.0.1');
        // Chi clicca due volte ha fatto la cosa giusta: non merita una schermata
        // d'errore, e il gettone resta comunque bruciato.
        Esito::verifica('non viene trattato come errore', !empty($v2['ok']));
        Esito::verifica('ma è riconosciuto come già fatto', !empty($v2['gia_attivo']));

        Esito::scenario('collegamenti che non devono funzionare');
        Esito::verifica('gettone inventato', empty(Auth::verifyEmail(str_repeat('ab', 32))['ok']));
        Esito::verifica('gettone vuoto', empty(Auth::verifyEmail('')['ok']));
        Esito::verifica('gettone non esadecimale', empty(Auth::verifyEmail('non-un-gettone')['ok']));

        Esito::scenario('un gettone scaduto non vale');
        [$uidB, $tokenB] = $iscrivi('b');
        Database::run(
            'UPDATE user_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?',
            [$uidB]
        );
        $vs = Auth::verifyEmail($tokenB);
        Esito::verifica('viene respinto', empty($vs['ok']));
        Esito::verifica('e lo dice chiaramente', str_contains((string) ($vs['error'] ?? ''), 'scadut'));
        Esito::uguale(
            'l\'account resta chiuso',
            'pending',
            (string) Database::first('SELECT status FROM users WHERE id = ?', [$uidB])['status']
        );

        Esito::scenario('chiedere un gettone nuovo invalida il precedente');
        $tokenC = Auth::issueToken($uidB, 'verify_email', '127.0.0.1');
        Esito::verifica('il vecchio non vale più', empty(Auth::verifyEmail($tokenB)['ok']));
        Esito::verifica('il nuovo funziona', !empty(Auth::verifyEmail($tokenC)['ok']));

        // --- l'e-mail ------------------------------------------------------

        Esito::sezione('Iscrizione — il messaggio che riceve il giocatore');

        $spedite = [];
        [$uidD, $tokenD] = $iscrivi('d');
        AuthMail::sendVerification($uidD, '__test_id@invalid.test', '__test_id', $tokenD);
        $msg = end($spedite);

        Esito::verifica('parte un messaggio', $msg !== false);
        Esito::verifica('al destinatario giusto', ($msg['a'] ?? '') === '__test_id@invalid.test');
        Esito::verifica('contiene il collegamento col gettone', str_contains($msg['corpo'] ?? '', $tokenD));
        // Il collegamento porta un gettone monouso: se viaggia in chiaro, chi
        // ascolta la rete entra al posto del giocatore.
        Esito::verifica(
            'e il collegamento è in https',
            (bool) preg_match('#https://\S*/verifica\?token=#', $msg['corpo'] ?? ''),
            'app.public_url = ' . (string) App\Core\Config::get('app.public_url')
        );
        Esito::verifica(
            'il contatore dei rinvii è stato mosso',
            (int) Database::first('SELECT verify_count FROM users WHERE id = ?', [$uidD])['verify_count'] >= 1
        );

        // --- recupero password ---------------------------------------------

        Esito::sezione('Recupero password');

        Esito::scenario('il collegamento consente di cambiarla una volta sola');
        $tokenR = Auth::issueToken($uid, 'reset_password', '127.0.0.1');
        $letto = Auth::readToken($tokenR, 'reset_password');
        Esito::verifica('il gettone è valido', !empty($letto['ok']));
        Esito::verifica(
            'ma non vale per la verifica e-mail',
            empty(Auth::readToken($tokenR, 'verify_email')['ok'])
        );

        $epocaPrima = (int) Database::first('SELECT session_epoch FROM users WHERE id = ?', [$uid])['session_epoch'];
        Auth::setPassword($uid, 'unaltrapasswordlunga');
        Auth::consumeToken((int) $letto['row']['id']);

        Esito::uguale('la vecchia password non vale più', 'bad_credentials', Auth::attempt('__test_ia', 'unapasswordlunga')['code']);
        Esito::uguale('la nuova funziona', 'ok', Auth::attempt('__test_ia', 'unaltrapasswordlunga')['code']);
        // Se la password è stata cambiata perché qualcuno se l'era presa,
        // lasciargli la sessione aperta vanificherebbe il cambio.
        $epocaDopo = (int) Database::first('SELECT session_epoch FROM users WHERE id = ?', [$uid])['session_epoch'];
        Esito::verifica('le sessioni aperte vengono chiuse', $epocaDopo > $epocaPrima, "epoca {$epocaPrima} → {$epocaDopo}");
        Esito::verifica('il gettone è bruciato', empty(Auth::readToken($tokenR, 'reset_password')['ok']));

        // --- iscrizioni mai confermate ---------------------------------------

        Esito::sezione('Iscrizioni mai confermate — decadono davvero');

        // L'e-mail lo prometteva e nessuno lo faceva: chi sbagliava a scrivere
        // l'indirizzo restava con il nome utente occupato per sempre.
        $vecchia = static function (int $id, int $giorni): void {
            Database::run(
                'UPDATE users SET created_at = DATE_SUB(NOW(), INTERVAL ? DAY),
                                  verify_sent_at = DATE_SUB(NOW(), INTERVAL ? DAY) WHERE id = ?',
                [$giorni, $giorni, $id]
            );
        };
        $esiste = static fn (int $id): bool => Database::first('SELECT 1 x FROM users WHERE id = ?', [$id]) !== null;

        [$uScaduta]   = $iscrivi('s1');  $vecchia($uScaduta, 8);
        [$uFresca]    = $iscrivi('s2');  $vecchia($uFresca, 6);
        [$uRinviata]  = $iscrivi('s3');  $vecchia($uRinviata, 30);
        Database::run('UPDATE users SET verify_sent_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?', [$uRinviata]);
        [$uAMano]     = $iscrivi('s4');  $vecchia($uAMano, 30);
        Database::run("UPDATE users SET status = 'active' WHERE id = ?", [$uAMano]);   // attivata dall'admin
        [$uAdmin]     = $iscrivi('s5');  $vecchia($uAdmin, 30);
        Database::run("UPDATE users SET role = 'admin' WHERE id = ?", [$uAdmin]);

        Auth::gcPending(7);

        Esito::scenario('chi non ha mai confermato, oltre il termine, se ne va');
        Esito::verifica('l\'iscrizione di 8 giorni fa e\' cancellata', !$esiste($uScaduta));
        Esito::scenario('ma nessun altro');
        Esito::verifica('quella di 6 giorni fa resta', $esiste($uFresca));
        Esito::verifica('chi ha chiesto un rinvio ieri ha il suo tempo intero', $esiste($uRinviata));
        Esito::verifica('un account attivato a mano dall\'amministratore resta', $esiste($uAMano));
        Esito::verifica('un amministratore non si tocca mai', $esiste($uAdmin));

        Esito::scenario('l\'e-mail dice il termine vero');
        $spedite = [];
        [$uMsg, $tMsg] = $iscrivi('s6');
        AuthMail::sendVerification($uMsg, '__test_is6@invalid.test', '__test_is6', $tMsg);
        $corpo = (string) (end($spedite)['corpo'] ?? '');
        Esito::verifica(
            'e cita i giorni che il clock applica',
            str_contains($corpo, Auth::pendingTtlDays() . ' giorni sparisce da solo'),
            'termine: ' . Auth::pendingTtlDays() . ' giorni'
        );

        // --- potatura -------------------------------------------------------

        Esito::sezione('Potatura dei gettoni');

        Esito::scenario('i gettoni consumati o scaduti non restano per sempre');
        Database::run(
            'UPDATE user_tokens SET created_at = DATE_SUB(NOW(), INTERVAL 30 DAY) WHERE user_id IN (' .
            implode(',', array_fill(0, count($utenti), '?')) . ')',
            $utenti
        );
        $prima = (int) Database::first('SELECT COUNT(*) n FROM user_tokens')['n'];
        $tolti = Auth::gcTokens(7);
        $dopo  = (int) Database::first('SELECT COUNT(*) n FROM user_tokens')['n'];
        Esito::verifica('ne vengono rimossi', $tolti > 0, "{$prima} → {$dopo}");
    } finally {
        Posta::trasportoDiProva(null);
        foreach ($utenti as $id) {
            Database::run('DELETE FROM players WHERE user_id = ?', [$id]);
            Database::run('DELETE FROM users WHERE id = ?', [$id]);
        }
        Database::run("DELETE FROM mail_queue WHERE destinatario LIKE '\\_\\_test%'");
        $residui = (int) (Database::first(
            "SELECT COUNT(*) n FROM users WHERE username LIKE '\\_\\_test\\_i%'"
        )['n'] ?? 0);
        Esito::uguale('nessun iscritto di prova lasciato indietro', 0, $residui);
    }
};
