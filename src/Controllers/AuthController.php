<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

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

    public function showRegister(Request $request): Response
    {
        if ((string) config('registration.open', 'approval') === 'closed') {
            Session::flash('error', 'Le registrazioni sono momentaneamente chiuse.');
            return redirect('/login');
        }
        return Response::html(view('auth/register', ['title' => 'Registrati']));
    }

    public function register(Request $request): Response
    {
        if (!RateLimiter::hit('register:ip:' . $request->ip(), 5, 3600)) {
            Session::flash('error', 'Troppe registrazioni da questo indirizzo. Riprova piu' . "'" . ' tardi.');
            return redirect('/registrati');
        }

        $result = Auth::register($request->all());

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flashInput($request->all());
            return redirect('/registrati');
        }

        $this->audit('auth.register', $result['user_id'], $request->ip());
        Session::flash('success', 'Registrazione ricevuta. Un amministratore validera' . "'" . ' il tuo account a breve.');
        return redirect('/login');
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
