<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Config;
use App\Core\Database;
use App\Core\Mailer;

/**
 * Notifica all'amministratore le nuove richieste di iscrizione.
 *
 * Chiamato dal tick, non dal percorso della registrazione: un fallimento
 * SMTP non deve mai toccare il form pubblico. users.reg_notified_at fa da
 * coda implicita (NULL = da segnalare).
 *
 * Modi (config notify.new_registration_mode):
 *   immediate : una e-mail per richiesta, al primo tick utile
 *   digest    : una e-mail cumulativa, inviata quando la richiesta piu'
 *               vecchia non segnalata supera notify.digest_delay_min minuti
 */
final class Notifier
{
    /** @return array<string,mixed> */
    public static function tick(): array
    {
        if (!(bool) Config::get('notify.new_registration', false)) {
            return ['skipped' => 'disabilitato'];
        }
        $admin = trim((string) Config::get('notify.admin_email', ''));
        if ($admin === '') {
            return ['skipped' => 'notify.admin_email mancante'];
        }
        if ((string) Config::get('mail.transport', 'log') === 'none') {
            return ['skipped' => 'mail.transport = none'];
        }

        try {
            $pending = Database::all(
                "SELECT id, username, email, created_at
                 FROM users
                 WHERE status = 'pending' AND reg_notified_at IS NULL
                 ORDER BY created_at ASC
                 LIMIT 50"
            );
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
        if ($pending === []) {
            return ['pending' => 0];
        }

        $mode = (string) Config::get('notify.new_registration_mode', 'digest');
        $appName = (string) Config::get('app.name', 'SubSpazio');
        $adminUrl = self::adminUrl();

        if ($mode === 'immediate') {
            $sent = 0; $fail = 0; $lastErr = null;
            foreach ($pending as $u) {
                $subject = "{$appName} — nuova richiesta di accesso: {$u['username']}";
                $body = self::body($appName, $adminUrl, [$u]);
                $res = Mailer::send($admin, $subject, $body);
                if (!empty($res['ok'])) {
                    Database::run('UPDATE users SET reg_notified_at = NOW() WHERE id = ?', [(int) $u['id']]);
                    $sent++;
                } else {
                    $fail++;
                    $lastErr = $res['error'] ?? 'errore sconosciuto';
                }
            }
            return array_filter(['sent' => $sent, 'failed' => $fail, 'error' => $lastErr], static fn ($v) => $v !== null && $v !== 0)
                ?: ['sent' => 0];
        }

        // digest: aspetta che la piu' vecchia superi la soglia, poi invia in blocco
        $delayMin = max(0, (int) Config::get('notify.digest_delay_min', 10));
        $oldestTs = strtotime((string) $pending[0]['created_at']);
        if (time() - $oldestTs < $delayMin * 60) {
            return ['pending' => count($pending), 'holding' => true];
        }

        $n = count($pending);
        $subject = "{$appName} — {$n} " . ($n === 1 ? 'nuova richiesta di accesso' : 'nuove richieste di accesso');
        $body = self::body($appName, $adminUrl, $pending);
        $res = Mailer::send($admin, $subject, $body);
        if (empty($res['ok'])) {
            return ['pending' => $n, 'error' => $res['error'] ?? 'invio fallito'];
        }
        $ids = array_map(static fn ($u) => (int) $u['id'], $pending);
        Database::run(
            'UPDATE users SET reg_notified_at = NOW() WHERE id IN (' . implode(',', $ids) . ')'
        );
        return ['sent' => 1, 'covered' => $n];
    }

    /** @param list<array<string,mixed>> $users */
    private static function body(string $appName, ?string $adminUrl, array $users): string
    {
        $lines = [];
        $lines[] = count($users) === 1
            ? "È arrivata una nuova richiesta di accesso a {$appName}:"
            : count($users) . " nuove richieste di accesso a {$appName}:";
        $lines[] = '';
        foreach ($users as $u) {
            $lines[] = "  • {$u['username']}  <{$u['email']}>";
            $lines[] = "    richiesta il " . date('d/m/Y H:i', strtotime((string) $u['created_at']));
        }
        $lines[] = '';
        $lines[] = $adminUrl !== null
            ? "Approva o rifiuta dal pannello: {$adminUrl}"
            : 'Approva o rifiuta dal pannello admin (/admin), oppure: php bin/console.php user:approve <username>';
        $lines[] = '';
        $lines[] = "— {$appName}, notifica automatica";
        return implode("\n", $lines);
    }

    private static function adminUrl(): ?string
    {
        $base = trim((string) Config::get('app.public_url', ''));
        if ($base === '') {
            return null;
        }
        return rtrim($base, '/') . '/admin';
    }
}
