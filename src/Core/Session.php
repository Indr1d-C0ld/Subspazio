<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = (string) Config::get('security.session_name', 'subspazio_sess');
        $ttl  = (int) Config::get('security.session_ttl', 0);
        $https = (($_SERVER['HTTPS'] ?? '') === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $ttl,
            'path'     => self::cookiePath(),
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        if (!isset($_SESSION['_born'])) {
            $_SESSION['_born'] = $now;
        } elseif ($now - (int) $_SESSION['_born'] > 1800) {
            // Rotazione periodica dell'id. NON distruttiva (false): il vecchio
            // id resta valido finche' il GC non lo raccoglie, cosi' una
            // richiesta gia' in volo o un client che tarda a salvare il nuovo
            // cookie (tipico su mobile: tab sospese, cambio rete) non resta
            // orfano di sessione.
            session_regenerate_id(false);
            $_SESSION['_born'] = $now;
        }

        self::startFlashCycle();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        // false = non elimina subito la vecchia sessione: evita che un client
        // lento a persistere il nuovo cookie (frequente su mobile) si ritrovi
        // senza sessione appena dopo il login. Contro la session fixation
        // basta che l'id cambi; il vecchio id sara' raccolto dal GC.
        session_regenerate_id(false);
        $_SESSION['_born'] = time();
    }

    /** Ambito del cookie di sessione: la sottocartella del deploy, non tutto il dominio. */
    private static function cookiePath(): string
    {
        $base = Config::get('app.base_path');
        if (!is_string($base) || $base === '') {
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $base = rtrim(dirname($script), '/');
        }
        $base = '/' . trim($base, '/');
        return $base === '/' ? '/' : $base . '/';
    }

    public static function flush(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    // --- Flash ---------------------------------------------------------------

    /** Promuove i flash impostati nella richiesta precedente a "leggibili ora". */
    private static function startFlashCycle(): void
    {
        $_SESSION['_flash_now']  = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash_now'][$key]);
    }

    /** Conserva i vecchi input per il re-render dei form dopo un errore. */
    public static function flashInput(array $data): void
    {
        unset($data['password'], $data['password_confirm'], $data['_token']);
        $_SESSION['_flash_next']['_old'] = $data;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_flash_now']['_old'][$key] ?? $default;
    }
}
