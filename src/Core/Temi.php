<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;

/**
 * Temi grafici. Ognuno e' un foglio in assets/css/temi/<chiave>.css che
 * ridefinisce le variabili di app.css (palette, caratteri, raggi) e, dove il
 * tema lo chiede, la forma di barre, pannelli e bottoni. «console» e' il
 * foglio di base, senza aggiunte.
 *
 * La scelta sta sull'account (users.tema) e in un cookie, cosi' vale anche
 * prima dell'accesso e la pagina esce gia' col tema giusto, senza lampi.
 */
final class Temi
{
    public const COOKIE = 'subspazio_tema';
    public const PREDEFINITO = 'console';

    /**
     * chiave => nome, ispirazione, colore della barra del browser e cinque
     * colori per l'anteprima (fondo, pannello, accento, secondo accento, testo).
     *
     * @var array<string, array{nome:string, ispirazione:string, barra:string, anteprima:list<string>}>
     */
    public const CATALOGO = [
        'console' => [
            'nome'        => 'Console',
            'ispirazione' => 'La plancia di SubSpazio: HUD ciano e viola su campo stellato.',
            'barra'       => '#070b12',
            'anteprima'   => ['#070b12', '#101a2c', '#6be2ff', '#b493ff', '#e2eaf6'],
        ],
        'lcars' => [
            'nome'        => 'LCARS',
            'ispirazione' => 'La plancia della Flotta Stellare in Star Trek: The Next Generation: gomiti arrotondati, bande color pesca, lilla e ambra su nero.',
            'barra'       => '#000000',
            'anteprima'   => ['#000000', '#000000', '#ff9966', '#cc99cc', '#ffcc99'],
        ],
        'muthur' => [
            'nome'        => 'Terminale MU/TH/UR',
            'ispirazione' => 'Il calcolatore di bordo della Nostromo in Alien (1979): fosfori verdi, righe di scansione, solo testo.',
            'barra'       => '#020703',
            'anteprima'   => ['#020703', '#041008', '#4dff88', '#c8ff5a', '#9dffbf'],
        ],
        'cockpit' => [
            'nome'        => 'Cockpit',
            'ispirazione' => 'L\'abitacolo di Elite Dangerous: HUD olografico arancione, angoli tagliati, etichette spaziate.',
            'barra'       => '#050303',
            'anteprima'   => ['#050303', '#120a05', '#ff8c1a', '#5fb7ff', '#ffd9b0'],
        ],
        'cintura' => [
            'nome'        => 'Cintura',
            'ispirazione' => 'Le interfacce di The Expanse: vetro traslucido, blu freddo, linee sottili e marcatori d\'angolo.',
            'barra'       => '#08111d',
            'anteprima'   => ['#08111d', '#0f1d2e', '#4cc3ff', '#9be7c4', '#e8f1fb'],
        ],
        'neon' => [
            'nome'        => 'Neon',
            'ispirazione' => 'La Los Angeles del 2019 di Blade Runner: insegne magenta e ciano nella notte piovosa.',
            'barra'       => '#0a0612',
            'anteprima'   => ['#0a0612', '#140c22', '#ff3ec8', '#26e8ff', '#f4e9ff'],
        ],
    ];

    public static function valido(string $chiave): bool
    {
        return isset(self::CATALOGO[$chiave]);
    }

    /** @var array{0:?int,1:string}|null utente e tema gia' risolti in questa richiesta */
    private static ?array $risolto = null;

    /** Il tema di chi sta guardando: account, poi cookie, poi quello di base. */
    public static function attuale(): string
    {
        $u = Auth::user();
        $uid = $u !== null ? (int) $u['id'] : null;
        if (self::$risolto !== null && self::$risolto[0] === $uid) {
            return self::$risolto[1];   // una query sola per pagina, non una per chiamata
        }
        $t = self::leggi($u);
        self::$risolto = [$uid, $t];
        return $t;
    }

    /** @param array<string,mixed>|null $u */
    private static function leggi(?array $u): string
    {
        if ($u !== null) {
            try {
                $t = (string) (Database::first('SELECT tema FROM users WHERE id = ?', [(int) $u['id']])['tema'] ?? '');
                if (self::valido($t)) {
                    return $t;
                }
            } catch (\Throwable) {
                // colonna non ancora migrata: si ripiega sul cookie
            }
        }
        $c = (string) ($_COOKIE[self::COOKIE] ?? '');
        return self::valido($c) ? $c : self::PREDEFINITO;
    }

    /** Salva la scelta: sull'account se c'e', sempre nel cookie. */
    public static function scegli(string $chiave): bool
    {
        if (!self::valido($chiave)) {
            return false;
        }
        $u = Auth::user();
        if ($u !== null) {
            try {
                Database::run('UPDATE users SET tema = ? WHERE id = ?', [$chiave, (int) $u['id']]);
            } catch (\Throwable) {
                // colonna non ancora migrata: resta il cookie
            }
        }
        self::$risolto = null;
        if (!headers_sent()) {
            setcookie(self::COOKIE, $chiave, [
                'expires'  => time() + 365 * 86400,
                'path'     => Session::cookiePath(),
                'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::COOKIE] = $chiave;
        return true;
    }

    /** Foglio del tema, null per quello di base. */
    public static function foglio(string $chiave): ?string
    {
        return $chiave === self::PREDEFINITO || !self::valido($chiave) ? null : 'css/temi/' . $chiave . '.css';
    }
}
