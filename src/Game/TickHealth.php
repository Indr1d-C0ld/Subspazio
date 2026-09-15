<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Config;
use App\Core\Database;
use App\Core\Mailer;

/**
 * Salute del clock: potatura del diario, rilevamento dei guasti che durano,
 * riepilogo per il pannello admin.
 *
 * Serve perche' un tick che fallisce non si vede: gira in cron, scrive su un
 * log che nessuno apre, e il gioco sembra solo un po' fermo. Da qui passa
 * l'unico allarme che raggiunge una persona.
 */
final class TickHealth
{
    /** Quante corse fallite di fila prima di considerarlo un guasto vero. */
    private const SOGLIA_DEFAULT = 5;

    /** Per quanti giorni tenere le corse riuscite. I fallimenti restano. */
    private const GIORNI_DEFAULT = 14;

    // --- esecuzione isolata dei lavori ------------------------------------

    /**
     * Esegue i lavori in ordine, isolati l'uno dall'altro: uno che solleva
     * un'eccezione viene annotato e si passa al successivo.
     *
     * Il rollback di una transazione lasciata aperta dal lavoro fallito non e'
     * un dettaglio: senza, il lavoro dopo girerebbe dentro una transazione
     * altrui gia' compromessa e l'isolamento sarebbe solo apparente.
     *
     * Un lavoro che ritorna null non viene annotato (non aveva niente da fare).
     *
     * @param array<string, callable():mixed> $jobs
     * @param null|callable(string,\Throwable):void $onErrore richiamo per il log
     * @return array{tasks:array<string,mixed>, falliti:array<string,string>}
     */
    public static function esegui(array $jobs, ?callable $onErrore = null): array
    {
        $tasks = [];
        $falliti = [];

        foreach ($jobs as $nome => $job) {
            try {
                $out = $job();
                if ($out !== null) {
                    $tasks[$nome] = $out;
                }
            } catch (\Throwable $e) {
                try {
                    if (Database::pdo()->inTransaction()) {
                        Database::pdo()->rollBack();
                    }
                } catch (\Throwable) {
                    // connessione perduta: i lavori rimanenti falliranno e saranno annotati
                }

                $falliti[$nome] = $e->getMessage();
                $tasks[$nome] = ['error' => substr($e->getMessage(), 0, 200)];

                if ($onErrore !== null) {
                    $onErrore($nome, $e);
                }
            }
        }

        return ['tasks' => $tasks, 'falliti' => $falliti];
    }

    // --- potatura ---------------------------------------------------------

    /**
     * Elimina le corse riuscite piu' vecchie della finestra di conservazione.
     * I fallimenti non si toccano: sono la parte diagnostica, e sono pochi.
     *
     * Throttlata a una volta l'ora e limitata a 5.000 righe per giro, cosi'
     * la prima potatura di un archivio grande non blocca la tabella.
     *
     * @return array{removed:int}|null null se non c'era niente da fare
     */
    public static function gc(): ?array
    {
        $ultimo = GameConfig::str('tick.gc_last_run', '');
        if ($ultimo !== '' && (time() - strtotime($ultimo)) < 3600) {
            return null;
        }
        GameConfig::set('tick.gc_last_run', date('Y-m-d H:i:s'));

        $giorni = max(1, GameConfig::int('tick.keep_days', self::GIORNI_DEFAULT));
        $n = Database::run(
            'DELETE FROM tick_runs
             WHERE ok = 1 AND started_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY id LIMIT 5000',
            [$giorni]
        )->rowCount();

        return $n > 0 ? ['removed' => $n] : null;
    }

    // --- rilevamento guasti -----------------------------------------------

    /** Quante corse consecutive, a partire dall'ultima, sono andate male. */
    public static function fallimentiConsecutivi(): int
    {
        $n = 0;
        foreach (Database::all('SELECT ok FROM tick_runs ORDER BY id DESC LIMIT 60') as $r) {
            if ((int) $r['ok'] === 1) {
                break;
            }
            $n++;
        }
        return $n;
    }

    /**
     * Avvisa quando il guasto dura, e una sola volta per episodio: un cron al
     * minuto che manda una mail al minuto e' rumore, e il rumore si ignora.
     * Quando il clock torna a posto lo annota e riarma l'allarme.
     *
     * @return array{stato:string, consecutivi:int, avvisato?:bool}
     */
    public static function segnalaSeNecessario(): array
    {
        $soglia = max(1, GameConfig::int('tick.alert_after_failures', self::SOGLIA_DEFAULT));
        $consecutivi = self::fallimentiConsecutivi();
        $allarmeAttivo = GameConfig::str('tick.alert_active', '0') === '1';

        if ($consecutivi >= $soglia && !$allarmeAttivo) {
            GameConfig::set('tick.alert_active', '1');
            $inviato = self::avvisa(
                'Clock in avaria',
                self::corpoAvaria($consecutivi)
            );
            return ['stato' => 'allarme', 'consecutivi' => $consecutivi, 'avvisato' => $inviato];
        }

        if ($consecutivi === 0 && $allarmeAttivo) {
            GameConfig::set('tick.alert_active', '0');
            $inviato = self::avvisa(
                'Clock tornato regolare',
                "Il clock di SubSpazio ha ripreso a completare le esecuzioni senza errori.\n"
            );
            return ['stato' => 'rientrato', 'consecutivi' => 0, 'avvisato' => $inviato];
        }

        return ['stato' => $consecutivi > 0 ? 'degradato' : 'regolare', 'consecutivi' => $consecutivi];
    }

    private static function corpoAvaria(int $consecutivi): string
    {
        $righe = [
            "Il clock di SubSpazio ha registrato {$consecutivi} esecuzioni consecutive con errori.",
            '',
            'Ultimi guasti annotati:',
        ];
        foreach (Database::all(
            'SELECT started_at, note FROM tick_runs WHERE ok = 0 ORDER BY id DESC LIMIT 5'
        ) as $r) {
            $righe[] = sprintf('  %s — %s', $r['started_at'], $r['note'] ?? '(nessuna nota)');
        }
        $righe[] = '';
        $righe[] = 'I lavori girano isolati: quelli sani continuano a funzionare.';
        $righe[] = 'Dettaglio per singolo lavoro in storage/logs/tick.log.';

        return implode("\n", $righe) . "\n";
    }

    /** Ritorna true se una mail e' stata effettivamente spedita. */
    private static function avvisa(string $oggetto, string $corpo): bool
    {
        logger("[tick] {$oggetto}: " . str_replace("\n", ' ', $corpo), 'error');
        fwrite(STDERR, "[tick] {$oggetto}\n");

        $admin = trim((string) Config::get('notify.admin_email', ''));
        if ($admin === '') {
            return false;
        }
        $nome = (string) Config::get('app.name', 'SubSpazio');
        try {
            $res = Mailer::send($admin, "[{$nome}] {$oggetto}", $corpo);
            return !empty($res['ok']);
        } catch (\Throwable) {
            return false;
        }
    }

    // --- riepilogo per il pannello admin ----------------------------------

    /**
     * Fotografia della salute del clock, per il pannello di controllo.
     *
     * @return array{
     *   stato:string, consecutivi:int, ultimo_at:?string, ultimo_ok:?bool,
     *   ultima_nota:?string, falliti_24h:int, corse_24h:int,
     *   durata_media_ms:int, durata_max_ms:int, in_ritardo:bool, righe:int
     * }
     */
    public static function stato(): array
    {
        $vuoto = [
            'stato' => 'sconosciuto', 'consecutivi' => 0, 'ultimo_at' => null, 'ultimo_ok' => null,
            'ultima_nota' => null, 'falliti_24h' => 0, 'corse_24h' => 0,
            'durata_media_ms' => 0, 'durata_max_ms' => 0, 'in_ritardo' => false, 'righe' => 0,
        ];

        try {
            $ultimo = Database::first('SELECT started_at, ok, note FROM tick_runs ORDER BY id DESC LIMIT 1');
            if ($ultimo === null) {
                return $vuoto;
            }
            $g = Database::first(
                'SELECT COUNT(*) n, SUM(ok = 0) ko, COALESCE(AVG(duration_ms),0) media, COALESCE(MAX(duration_ms),0) massimo
                 FROM tick_runs WHERE started_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'
            ) ?? [];
            $righe = (int) (Database::first('SELECT COUNT(*) n FROM tick_runs')['n'] ?? 0);

            $consecutivi = self::fallimentiConsecutivi();
            // il cron gira al minuto: oltre i 5 minuti di silenzio qualcosa non va
            $inRitardo = (time() - strtotime((string) $ultimo['started_at'])) > 300;

            $stato = match (true) {
                $inRitardo                => 'fermo',
                $consecutivi >= max(1, GameConfig::int('tick.alert_after_failures', self::SOGLIA_DEFAULT)) => 'avaria',
                $consecutivi > 0          => 'degradato',
                default                   => 'regolare',
            };

            return [
                'stato'           => $stato,
                'consecutivi'     => $consecutivi,
                'ultimo_at'       => (string) $ultimo['started_at'],
                'ultimo_ok'       => (int) $ultimo['ok'] === 1,
                'ultima_nota'     => $ultimo['note'] !== null ? (string) $ultimo['note'] : null,
                'falliti_24h'     => (int) ($g['ko'] ?? 0),
                'corse_24h'       => (int) ($g['n'] ?? 0),
                'durata_media_ms' => (int) round((float) ($g['media'] ?? 0)),
                'durata_max_ms'   => (int) ($g['massimo'] ?? 0),
                'in_ritardo'      => $inRitardo,
                'righe'           => $righe,
            ];
        } catch (\Throwable) {
            return $vuoto;
        }
    }
}
