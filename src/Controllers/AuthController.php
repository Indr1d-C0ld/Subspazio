<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\AuthMail;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\GameConfig;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(view('auth/login', ['title' => 'Accedi']));
    }

    public function login(Request $request): Response
    {
        $login    = $request->str('login');
        $password = (string) $request->input('password', '');

        $ipKey   = 'login:ip:' . $request->ip();
        $userKey = 'login:user:' . mb_strtolower($login);

        if (!RateLimiter::hit($ipKey, 15, 600) || !RateLimiter::hit($userKey, 8, 600)) {
            Session::flash('error', 'Troppi tentativi di accesso. Riprova tra qualche minuto.');
            return redirect('/login');
        }

        if ($login === '' || $password === '') {
            Session::flash('error', 'Inserisci nome utente/email e password.');
            Session::flashInput($request->all());
            return redirect('/login');
        }

        $result = Auth::attempt($login, $password);

        if ($result['ok']) {
            RateLimiter::clear($ipKey);
            RateLimiter::clear($userKey);
            Auth::login($result['user'], $request->ip());
            $this->audit('auth.login', (int) $result['user']['id'], $request->ip());
            return redirect('/');
        }

        $message = match ($result['code']) {
            'pending'   => 'Account in attesa di approvazione da parte di un amministratore.',
            'suspended' => 'Account sospeso. Contatta un amministratore.',
            'banned'    => 'Accesso non consentito.',
            default     => 'Credenziali non valide.',
        };

        Session::flash('error', $message);
        Session::flashInput($request->all());
        return redirect('/login');
    }

    /**
     * Iscrizioni aperte o chiuse.
     *
     * La chiave vive in `game_config` — e' li' che l'amministratore la trova
     * nel pannello — mentre prima veniva letta da `config()`, che pesca dal
     * file di configurazione: quella chiave li' non c'e' mai stata, quindi il
     * valore ricadeva sempre sul default e l'interruttore non chiudeva niente.
     */
    private static function iscrizioniChiuse(): bool
    {
        return GameConfig::str('registration.open', 'verify') === 'closed';
    }

    public function showRegister(Request $request): Response
    {
        if (self::iscrizioniChiuse()) {
            Session::flash('error', 'Le registrazioni sono momentaneamente chiuse.');
            return redirect('/login');
        }
        return Response::html(view('auth/register', ['title' => 'Registrati']));
    }

    public function register(Request $request): Response
    {
        // Nascondere il modulo non chiude niente: la domanda si puo' inviare
        // lo stesso. Il controllo che conta e' questo.
        if (self::iscrizioniChiuse()) {
            Session::flash('error', 'Le registrazioni sono momentaneamente chiuse.');
            return redirect('/login');
        }
        if (!RateLimiter::hit('register:ip:' . $request->ip(), 5, 3600)) {
            Session::flash('error', 'Troppe registrazioni da questo indirizzo. Riprova piu' . "'" . ' tardi.');
            return redirect('/registrati');
        }

        $result = Auth::register($request->all(), $request->ip());

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flashInput($request->all());
            return redirect('/registrati');
        }

        $email = mb_strtolower(trim((string) $request->str('email')));
        $username = trim((string) $request->str('username'));

        AuthMail::sendVerification((int) $result['user_id'], $email, $username, (string) $result['token']);
        AuthMail::notifyAdmin((int) $result['user_id'], $username, $email);

        $this->audit('auth.register', $result['user_id'], $request->ip());
        Session::put('verifica_email', $email);
        return redirect('/verifica-inviata');
    }

    public function logout(Request $request): Response
    {
        $id = Auth::id();
        Auth::logout();
        if ($id !== null) {
            $this->audit('auth.logout', $id, $request->ip());
        }
        Session::flash('success', 'Sei uscito dal sistema.');
        return redirect('/login');
    }

    public function pending(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return redirect('/login');
        }
        if ($user['status'] === 'active') {
            return redirect('/');
        }
        return Response::html(view('auth/pending', [
            'title' => 'Account in attesa',
            'user'  => $user,
        ]));
    }

    // --- Autovalidazione dell'indirizzo ----------------------------------

    public function verificationSent(Request $request): Response
    {
        return Response::html(view('auth/verifica_inviata', [
            'title' => 'Controlla la posta',
            'email' => (string) Session::get('verifica_email', ''),
        ]));
    }

    public function verify(Request $request): Response
    {
        $res = Auth::verifyEmail($request->str('token'), $request->ip());

        if (empty($res['ok'])) {
            Session::flash('error', $res['error'] ?? 'Collegamento non valido.');
            return redirect('/login');
        }

        $this->audit('auth.verify_email', (int) ($res['user']['user_id'] ?? 0), $request->ip());
        Session::forget('verifica_email');
        Session::flash('success', empty($res['gia_attivo'])
            ? "Indirizzo confermato: il tuo account e' attivo. Benvenuto a bordo, comandante."
            : "Il tuo account risulta gia' attivo: puoi accedere.");
        return redirect('/login');
    }

    /**
     * Rinvio del collegamento. Non rivela MAI se l'indirizzo esista o no: la
     * risposta e' identica nei due casi, altrimenti questa pagina diventerebbe
     * un modo per scoprire chi e' iscritto — la stessa falla chiusa sul login.
     */
    public function resend(Request $request): Response
    {
        $email = mb_strtolower(trim($request->str('email')));

        if (!RateLimiter::hit('resend:ip:' . $request->ip(), 5, 3600)) {
            Session::flash('error', "Troppe richieste da questo indirizzo. Riprova piu' tardi.");
            return redirect('/login');
        }

        $u = Database::first(
            "SELECT id, username, email, verify_sent_at, verify_count
             FROM users WHERE email = ? AND status = 'pending'",
            [$email]
        );

        if ($u !== null) {
            $attesa = \App\Game\GameConfig::int('auth.resend_wait_min', 10);
            $tetto  = \App\Game\GameConfig::int('auth.resend_max', 5);
            $troppoPresto = $u['verify_sent_at'] !== null
                && (time() - strtotime((string) $u['verify_sent_at'])) < $attesa * 60;

            if (!$troppoPresto && (int) $u['verify_count'] < $tetto) {
                $token = Auth::issueToken((int) $u['id'], 'verify_email', $request->ip());
                AuthMail::sendVerification((int) $u['id'], (string) $u['email'], (string) $u['username'], $token);
            }
        }

        Session::flash('success', "Se quell'indirizzo risulta in attesa di conferma, il collegamento e' stato rispedito.");
        return redirect('/login');
    }

    // --- Recupero della password -----------------------------------------

    public function showForgot(Request $request): Response
    {
        return Response::html(view('auth/password_dimenticata', ['title' => 'Password dimenticata']));
    }

    /** Anche qui la risposta e' sempre la stessa: nessuna enumerazione. */
    public function forgot(Request $request): Response
    {
        $email = mb_strtolower(trim($request->str('email')));

        if (!RateLimiter::hit('forgot:ip:' . $request->ip(), 5, 3600)) {
            Session::flash('error', "Troppe richieste da questo indirizzo. Riprova piu' tardi.");
            return redirect('/password-dimenticata');
        }

        $u = Database::first(
            "SELECT id, username, email FROM users WHERE email = ? AND status IN ('active','pending')",
            [$email]
        );
        if ($u !== null) {
            $token = Auth::issueToken((int) $u['id'], 'reset_password', $request->ip());
            AuthMail::sendPasswordReset((string) $u['email'], (string) $u['username'], $token);
            $this->audit('auth.reset_requested', (int) $u['id'], $request->ip());
        }

        Session::flash('success', "Se quell'indirizzo e' registrato, ti arrivera' un collegamento per reimpostare la password.");
        return redirect('/login');
    }

    public function showReset(Request $request): Response
    {
        $token = $request->str('token');
        $letto = Auth::readToken($token, 'reset_password');
        if (!$letto['ok']) {
            Session::flash('error', ($letto['error'] ?? 'Collegamento non valido.') . ' Puoi richiederne uno nuovo.');
            return redirect('/password-dimenticata');
        }
        return Response::html(view('auth/reimposta', [
            'title' => 'Nuova password',
            'token' => $token,
        ]));
    }

    public function reset(Request $request): Response
    {
        $token = $request->str('token');
        $letto = Auth::readToken($token, 'reset_password');
        if (!$letto['ok']) {
            Session::flash('error', ($letto['error'] ?? 'Collegamento non valido.') . ' Puoi richiederne uno nuovo.');
            return redirect('/password-dimenticata');
        }

        $pw  = (string) $request->str('password');
        $pw2 = (string) $request->str('password_confirm');
        $minimo = Auth::minPasswordLength();

        if (strlen($pw) < $minimo) {
            Session::flash('errors', ["La password deve avere almeno {$minimo} caratteri."]);
            return redirect('/reimposta?token=' . urlencode($token));
        }
        if ($pw !== $pw2) {
            Session::flash('errors', ['Le password non coincidono.']);
            return redirect('/reimposta?token=' . urlencode($token));
        }

        $uid = (int) $letto['row']['user_id'];
        Auth::setPassword($uid, $pw);
        Auth::consumeToken((int) $letto['row']['id']);
        $this->audit('auth.reset_done', $uid, $request->ip());

        Session::flash('success', 'Password aggiornata. Le sessioni aperte sono state chiuse: accedi con quella nuova.');
        return redirect('/login');
    }

    private function audit(string $action, ?int $targetId, string $ip): void
    {
        try {
            Database::run(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, ip)
                 VALUES (?, ?, ?, ?, ?)',
                [Auth::id(), $action, 'user', $targetId, @inet_pton($ip) ?: null]
            );
        } catch (\Throwable) {
            // non bloccante
        }
    }
}
