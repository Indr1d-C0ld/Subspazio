<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use App\Core\Session;

/**
 * Autenticazione: registrazione, login, stato utente.
 * Il modello di registrazione e' "approvazione admin": ogni nuovo
 * account nasce in stato 'pending' e va attivato dalla dashboard.
 */
final class Auth
{
    private const SESSION_KEY = 'uid';

    /** @var array<string,mixed>|null */
    private static ?array $cachedUser = null;
    private static bool $resolved = false;

    // --- Lettura stato ----------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }
        self::$resolved = true;

        $uid = Session::get(self::SESSION_KEY);
        if (!is_int($uid) && !(is_string($uid) && ctype_digit($uid))) {
            return self::$cachedUser = null;
        }

        try {
            $user = Database::first(
                'SELECT id, username, email, display_name, status, role, created_at, approved_at, last_login_at, session_epoch
                 FROM users WHERE id = ?',
                [(int) $uid]
            );
        } catch (\Throwable $e) {
            return self::$cachedUser = null;
        }

        if ($user === null || in_array($user['status'], ['banned'], true)) {
            Session::forget(self::SESSION_KEY);
            return self::$cachedUser = null;
        }

        // sessione invalidata dall'admin (kick / sospensione)
        if ((int) ($user['session_epoch'] ?? 0) !== (int) Session::get('epoch', 0)) {
            Session::forget(self::SESSION_KEY);
            return self::$cachedUser = null;
        }

        return self::$cachedUser = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function status(): ?string
    {
        $u = self::user();
        return $u['status'] ?? null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin' && $u['status'] === 'active';
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], ['admin', 'moderator'], true) && $u['status'] === 'active';
    }

    // --- Azioni ---------------------------------------------------------------

    /**
     * @return array{ok:bool, code:string, user?:array<string,mixed>}
     *   code: ok | bad_credentials | pending | suspended | banned
     */
    public static function attempt(string $login, string $password): array
    {
        $login = trim($login);
        $row = Database::first(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$login, mb_strtolower($login)]
        );

        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            return ['ok' => false, 'code' => 'bad_credentials'];
        }

        if (password_needs_rehash((string) $row['password_hash'], self::algo(), self::algoOptions())) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, self::algo(), self::algoOptions()),
                $row['id'],
            ]);
        }

        return match ($row['status']) {
            'active'    => ['ok' => true, 'code' => 'ok', 'user' => $row],
            'pending'   => ['ok' => false, 'code' => 'pending'],
            'suspended' => ['ok' => false, 'code' => 'suspended'],
            default     => ['ok' => false, 'code' => 'banned'],
        };
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user, string $ip = ''): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put('epoch', (int) ($user['session_epoch'] ?? 0));
        self::$cachedUser = null;
        self::$resolved = false;

        try {
            Database::run(
                'UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
                [$ip !== '' ? @inet_pton($ip) ?: null : null, (int) $user['id']]
            );
        } catch (\Throwable) {
            // non bloccante
        }
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
        self::$cachedUser = null;
        self::$resolved = false;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool, errors:array<string,string>, user_id?:int}
     */
    public static function register(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $email    = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirm  = (string) ($input['password_confirm'] ?? '');

        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            $errors['username'] = 'Da 3 a 32 caratteri: lettere, numeri e underscore.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'Indirizzo email non valido.';
        }
        if (strlen($password) < 10) {
            $errors['password'] = 'La password deve avere almeno 10 caratteri.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Le password non coincidono.';
        }

        if ($errors === []) {
            $clash = Database::first(
                'SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1',
                [$username, $email]
            );
            if ($clash !== null) {
                if (strcasecmp((string) $clash['username'], $username) === 0) {
                    $errors['username'] = 'Nome utente gia' . "'" . ' in uso.';
                } else {
                    $errors['email'] = 'Email gia' . "'" . ' registrata.';
                }
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        Database::run(
            'INSERT INTO users (username, email, password_hash, display_name, status, role)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $username,
                $email,
                password_hash($password, self::algo(), self::algoOptions()),
                $username,
                'pending',
                'player',
            ]
        );

        return ['ok' => true, 'errors' => [], 'user_id' => Database::lastInsertId()];
    }

    // --- Hashing ------------------------------------------------------------

    private static function algo(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    /** @return array<string,int> */
    private static function algoOptions(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
        }
        return ['cost' => 12];
    }
}
