<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use App\Core\Session;

/**
 * Autenticazione: registrazione, login, stato utente.
 *
 * Il modello di registrazione e' "autovalidazione": ogni account nasce
 * 'pending' e diventa 'active' quando l'interessato conferma il proprio
 * indirizzo aprendo il collegamento ricevuto. L'amministratore non deve
 * vagliare nulla, ma conserva la possibilita' di attivare a mano dal
 * pannello nel caso un'e-mail non arrivi mai.
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
                'SELECT id, username, email, display_name, status, role, created_at, approved_at,
                        email_verified_at, last_login_at, session_epoch
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

    public static function isVerified(): bool
    {
        $u = self::user();
        return $u !== null && !empty($u['email_verified_at']);
    }

    // --- Gettoni monouso --------------------------------------------------

    /**
     * Genera un gettone monouso, ne salva soltanto l'hash e restituisce il
     * valore in chiaro (che esiste quindi solo dentro l'e-mail spedita).
     *
     * Un solo gettone vivo per tipo: chiederne uno nuovo invalida il
     * precedente, cosi' un collegamento vecchio finito in mani altrui non
     * serve piu' a nulla.
     */
    public static function issueToken(int $userId, string $kind, ?string $ip = null): string
    {
        $token = bin2hex(random_bytes(32));
        $ore = $kind === 'reset_password'
            ? max(1, \App\Game\GameConfig::int('auth.reset_ttl_hours', 2))
            : max(1, \App\Game\GameConfig::int('auth.verify_ttl_hours', 48));

        Database::run(
            'UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND kind = ? AND used_at IS NULL',
            [$userId, $kind]
        );
        Database::run(
            'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at, created_ip)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)',
            [$userId, $kind, hash('sha256', $token), $ore, $ip !== null ? @inet_pton($ip) ?: null : null]
        );
        return $token;
    }

    /**
     * Legge un gettone senza consumarlo. Separata da consumeToken() perche' il
     * recupero password ha bisogno di validare il collegamento per mostrare il
     * modulo, e di bruciarlo solo quando la nuova password viene salvata.
     *
     * @return array{ok:bool, error?:string, row?:array<string,mixed>}
     */
    public static function readToken(string $token, string $kind): array
    {
        $token = trim($token);
        if ($token === '' || !ctype_xdigit($token)) {
            return ['ok' => false, 'error' => 'Collegamento non valido.'];
        }
        $row = Database::first(
            'SELECT t.id, t.user_id, t.used_at, t.expires_at, u.status, u.username, u.email
             FROM user_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.kind = ?',
            [hash('sha256', $token), $kind]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'Collegamento non valido.'];
        }
        if ($row['used_at'] !== null) {
            return ['ok' => false, 'error' => 'Questo collegamento e\' gia\' stato utilizzato.', 'row' => $row];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'Il collegamento e\' scaduto.', 'row' => $row];
        }
        return ['ok' => true, 'row' => $row];
    }

    /**
     * Consuma il gettone di verifica e attiva l'account.
     *
     * @return array{ok:bool, error?:string, user?:array<string,mixed>}
     */
    public static function verifyEmail(string $token, ?string $ip = null): array
    {
        $letto = self::readToken($token, 'verify_email');

        if (!$letto['ok']) {
            // Gia' usato ma l'account e' attivo: e' un doppio clic sullo stesso
            // collegamento, non un errore da mostrare in faccia a qualcuno che
            // ha appena fatto la cosa giusta.
            if (isset($letto['row']) && (string) $letto['row']['status'] === 'active' && $letto['row']['used_at'] !== null) {
                return ['ok' => true, 'user' => $letto['row'], 'gia_attivo' => true];
            }
            return $letto;
        }
        $row = $letto['row'];

        Database::run('UPDATE user_tokens SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        Database::run(
            "UPDATE users SET status = 'active', email_verified_at = NOW() WHERE id = ? AND status = 'pending'",
            [(int) $row['user_id']]
        );
        return ['ok' => true, 'user' => $row];
    }

    /**
     * Imposta una nuova password e chiude le sessioni aperte di quell'utente.
     *
     * L'incremento di session_epoch e' la parte che conta: se la password e'
     * stata cambiata perche' qualcuno se l'era presa, lasciargli la sessione
     * aperta vanificherebbe il cambio.
     */
    public static function setPassword(int $userId, string $password): void
    {
        Database::run(
            'UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?',
            [password_hash($password, self::algo(), self::algoOptions()), $userId]
        );
    }

    /** Brucia un gettone gia' validato con readToken(). */
    public static function consumeToken(int $tokenId): void
    {
        Database::run('UPDATE user_tokens SET used_at = NOW() WHERE id = ?', [$tokenId]);
    }

    /** Potatura dei gettoni scaduti o consumati da un pezzo. */
    public static function gcTokens(int $giorni = 7): int
    {
        return Database::run(
            'DELETE FROM user_tokens
              WHERE (used_at IS NOT NULL OR expires_at < NOW())
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 500',
            [max(1, $giorni)]
        )->rowCount();
    }

    /**
     * Giorni dopo i quali un'iscrizione mai confermata decade.
     *
     * Si conta dall'ULTIMO collegamento spedito, non dall'iscrizione: chi ha
     * chiesto un rinvio ha diritto al suo tempo intero, altrimenti la pagina
     * che dice "puoi chiederne un altro" gli offrirebbe un collegamento che
     * scade insieme al suo account.
     */
    public static function pendingTtlDays(): int
    {
        return max(1, \App\Game\GameConfig::int('auth.pending_ttl_days', 7));
    }

    /**
     * Cancella le iscrizioni mai confermate e scadute.
     *
     * L'e-mail di verifica promette che senza conferma l'account «sparisce da
     * solo»: finche' nessuno lo cancellava, era falso, e con una conseguenza
     * concreta. Chi sbaglia a scrivere l'indirizzo non puo' farsi rispedire il
     * collegamento — partirebbe verso l'indirizzo sbagliato — e restava con il
     * proprio nome utente occupato per sempre.
     *
     * Solo `pending` mai verificati: un account attivato a mano da un
     * amministratore resta, anche se l'indirizzo non e' mai stato confermato.
     * Gli amministratori non si toccano, e chi ha gia' un comandante nemmeno —
     * non dovrebbe esistere, ma se esiste e' un caso da guardare, non da
     * cancellare in silenzio.
     */
    public static function gcPending(?int $giorni = null): int
    {
        return Database::run(
            "DELETE FROM users
              WHERE status = 'pending'
                AND email_verified_at IS NULL
                AND role <> 'admin'
                AND COALESCE(verify_sent_at, created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND NOT EXISTS (SELECT 1 FROM players p WHERE p.user_id = users.id)
              LIMIT 200",
            [$giorni ?? self::pendingTtlDays()]
        )->rowCount();
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

        if ($row === null) {
            // Si verifica comunque, contro un hash fittizio. Saltare del
            // tutto password_verify() rende la risposta molto piu' rapida per
            // un utente inesistente, e con Argon2id a 64 MB la differenza si
            // misura dall'esterno: diventa un modo per scoprire chi e'
            // iscritto senza indovinarne la password.
            password_verify($password, self::hashFittizio());
            return ['ok' => false, 'code' => 'bad_credentials'];
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
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
    public static function register(array $input, ?string $ip = null): array
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
        $minimo = self::minPasswordLength();
        if (strlen($password) < $minimo) {
            $errors['password'] = "La password deve avere almeno {$minimo} caratteri.";
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

        $userId = Database::lastInsertId();
        $token = self::issueToken($userId, 'verify_email', $ip);

        return ['ok' => true, 'errors' => [], 'user_id' => $userId, 'token' => $token];
    }

    /**
     * Lunghezza minima richiesta per una password, unica per tutto il
     * progetto: registrazione, comandi CLI e attributo minlength del form.
     *
     * Regolabile dal pannello (`security.password_min_length`). Il valore
     * consigliato resta 10: sotto si guadagna comodita' e si perde robustezza,
     * e la soglia vale per ogni account, non solo per chi la abbassa. C'e' un
     * pavimento a 6 perche' un refuso in configurazione non possa azzerare del
     * tutto il controllo.
     */
    public static function minPasswordLength(): int
    {
        try {
            $v = \App\Game\GameConfig::int('security.password_min_length', 10);
        } catch (\Throwable) {
            $v = 10;   // database non raggiungibile: si resta sul consigliato
        }
        return max(6, $v);
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

    /**
     * Hash di riferimento contro cui verificare quando l'utente non esiste,
     * cosi' i due rami del login costano lo stesso.
     *
     * E' una costante, non un password_hash() calcolato al volo: sotto PHP-FPM
     * ogni richiesta parte da zero, quindi calcolarlo avrebbe significato
     * pagare un hash *piu'* una verifica — circa il doppio del ramo con
     * l'utente vero, cioe' lo stesso oracolo di prima solo rovesciato
     * (misurato: 370 ms contro 210).
     *
     * Generato con i parametri di algoOptions(); se quelli cambiano va
     * rigenerato, altrimenti il pareggio si sfalsa:
     *   php -r 'echo password_hash("x", PASSWORD_ARGON2ID,
     *           ["memory_cost"=>65536,"time_cost"=>4,"threads"=>2]);'
     */
    private const HASH_FITTIZIO =
        '$argon2id$v=19$m=65536,t=4,p=2$SXBsRjBOR0lQVlJFMkFxYg$dURK+3+I7qNlLqw0F8lP9ZHkiYzr8sXbBYoJ4S/BUHE';

    private static function hashFittizio(): string
    {
        // Se l'ambiente non ha Argon2id, il confronto deve comunque costare
        // qualcosa di paragonabile ai veri hash, che li' sarebbero bcrypt.
        if (!defined('PASSWORD_ARGON2ID')) {
            static $bcrypt = null;
            return $bcrypt ??= password_hash('x', PASSWORD_DEFAULT, self::algoOptions());
        }
        return self::HASH_FITTIZIO;
    }
}
