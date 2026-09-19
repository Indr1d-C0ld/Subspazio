<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Config;
use App\Core\Database;
use App\Core\Posta;
use App\Game\GameConfig;

/**
 * Le e-mail dell'autenticazione. Solo testo semplice — il Mailer non fa HTML —
 * nel tono della Rete Comm della Flotta: per chi si iscrive e' la prima cosa
 * che legge di SubSpazio.
 *
 * Tutto passa dalla coda (App\Core\Posta), non dall'invio diretto: se l'SMTP
 * non risponde il messaggio resta in coda e riparte da solo. La verifica va a
 * priorita' 1 perche' e' l'unica porta d'ingresso al gioco; gli avvisi
 * all'amministratore possono aspettare.
 */
final class AuthMail
{
    /** URL assoluto: nelle e-mail un percorso relativo non serve a nulla. */
    private static function publicUrl(string $path = '/'): string
    {
        $base = rtrim((string) Config::get('app.public_url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private static function nomeGioco(): string
    {
        return (string) Config::get('app.name', 'SubSpazio');
    }

    /** @return array{ok:bool, id?:int, error?:string} */
    public static function sendVerification(int $userId, string $email, string $username, string $token): array
    {
        $link = self::publicUrl('/verifica?token=' . $token);
        $ore  = GameConfig::int('auth.verify_ttl_hours', 48);
        $gioco = self::nomeGioco();

        $corpo = <<<TXT
        RETE COMM DELLA FLOTTA
        Ufficio arruolamenti — {$gioco}

        Comandante {$username},

        la tua domanda di arruolamento e' stata registrata. Per prendere
        servizio conferma l'indirizzo aprendo questo collegamento:

        {$link}

        Il collegamento resta valido {$ore} ore. Se scade, puoi chiederne un
        altro dalla pagina di accesso.

        Se non hai richiesto nulla, ignora questo messaggio: senza conferma
        l'account non viene attivato e sparisce da solo.

        --
        {$gioco} — la door BBS TradeWars reimmaginata per il web
        Messaggio automatico: non rispondere a questo indirizzo.
        TXT;

        $res = Posta::invia($email, "{$gioco} — conferma il tuo arruolamento", $corpo, 'verifica', 1);

        // Il contatore segna il momento in cui il messaggio e' stato PRESO IN
        // CARICO, non quello in cui e' partito: e' quello che regola il freno
        // sui rinvii, e deve scattare anche se l'SMTP e' momentaneamente giu'.
        Database::run(
            'UPDATE users SET verify_sent_at = NOW(), verify_count = verify_count + 1 WHERE id = ?',
            [$userId]
        );
        if (empty($res['ok'])) {
            logger("verifica per l'utente {$userId} messa in coda: " . ($res['error'] ?? '?'), 'warning');
        }
        return $res;
    }

    /** @return array{ok:bool, id?:int, error?:string} */
    public static function sendPasswordReset(string $email, string $username, string $token): array
    {
        $link = self::publicUrl('/reimposta?token=' . $token);
        $ore  = GameConfig::int('auth.reset_ttl_hours', 2);
        $gioco = self::nomeGioco();

        $corpo = <<<TXT
        RETE COMM DELLA FLOTTA
        Sicurezza di bordo — {$gioco}

        Comandante {$username},

        qualcuno ha chiesto di reimpostare la password del tuo account.
        Se sei stato tu, apri questo collegamento:

        {$link}

        Vale {$ore} ore e una volta sola. Aprendolo verranno chiuse anche le
        sessioni gia' aperte sul tuo account.

        Se non hai chiesto nulla non devi fare niente: la password attuale
        resta valida e il collegamento scade da solo.

        --
        {$gioco} — Messaggio automatico: non rispondere a questo indirizzo.
        TXT;

        return Posta::invia($email, "{$gioco} — reimpostazione della password", $corpo, 'reset', 1);
    }

    /**
     * Avviso all'amministratore di una nuova iscrizione. Non deve mai bloccare
     * la registrazione: se fallisce resta soltanto una riga di log.
     *
     * Con l'autovalidazione non c'e' piu' nulla da approvare — e' una notizia,
     * non una richiesta — quindi va in coda a priorita' bassa.
     */
    public static function notifyAdmin(int $userId, string $username, string $email): void
    {
        $to = trim((string) Config::get('notify.admin_email', ''));
        if ($to === '' || !Config::get('notify.new_registration', true)) {
            return;
        }
        $gioco = self::nomeGioco();
        $quando = date('d/m/Y H:i');

        $corpo = <<<TXT
        Nuova iscrizione a {$gioco}.

          comandante : {$username}
          e-mail     : {$email}
          id         : {$userId}
          quando     : {$quando}

        Non serve fare nulla: l'account si attiva da solo quando
        l'interessato conferma l'indirizzo. Resta 'pending' finche' non lo fa.

        Elenco: php bin/console.php user:list
        TXT;

        Posta::invia($to, "{$gioco} — nuova iscrizione: {$username}", $corpo, 'avviso_admin', 7);
        try {
            Database::run('UPDATE users SET reg_notified_at = NOW() WHERE id = ?', [$userId]);
        } catch (\Throwable) {
            // colonna assente in installazioni vecchie: non e' un motivo per fallire
        }
    }
}
