<?php

declare(strict_types=1);

/**
 * SubSpazio — clock interno. Da eseguire ogni minuto via cron:
 *
 *   * * * * * /usr/bin/php /data/html/subspazio/bin/tick.php >> /data/html/subspazio/storage/logs/cron.log 2>&1
 *
 * I lavori girano isolati l'uno dall'altro: uno che solleva un'eccezione
 * viene annotato e si passa al successivo, invece di far saltare tutto quel
 * che viene dopo. Prima non era cosi', e una tabella mancante bastava a
 * congelare in silenzio contratti, classifiche e notiziario finche' qualcuno
 * non leggeva i log (accaduto il 2026-08-29 e il 2026-09-10).
 */

$projectRoot = require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Core\RateLimiter;
use App\Game\Bank;
use App\Game\Contracts;
use App\Game\Economy;
use App\Game\Events;
use App\Game\GameConfig;
use App\Game\Leaderboard;
use App\Game\Live;
use App\Game\Npc;
use App\Game\Planets;
use App\Game\TurnManager;
use App\Game\TickHealth;

$lockFile = $projectRoot . '/storage/tick.lock';
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "[tick] esecuzione precedente ancora in corso, salto.\n");
    exit(0);
}

$startedAt = microtime(true);
$runId = null;

/**
 * I lavori, nell'ordine in cui vanno eseguiti. Ognuno ritorna il proprio
 * riepilogo, oppure null per dire "non c'era niente da fare, non annotarmi"
 * (i riepiloghi finiscono in tick_runs.tasks, che e' gia' la tabella piu'
 * pesante del database: meglio non gonfiarla con righe vuote).
 *
 * @var array<string, callable():mixed> $jobs
 */
$jobs = [
    // 1) Garbage collection: rate limit, eventi live scaduti, giornale di bordo.
    'rate_limits_gc' => static fn () => RateLimiter::gc(),
    'live_gc'        => static fn () => Live::gc(),
    'shiplog_gc'     => static fn () => \App\Game\ShipLog::gc(),
    'limpets_gc'     => static fn () => \App\Game\Limpet::gc(),
    'subsystems'     => static fn () => \App\Game\Subsystems::tick(),
    'encounters_gc'  => static fn () => \App\Game\Encounters::gc(),

    // 2) Reset turni giornaliero.
    'turn_reset'     => static fn () => handleTurnReset(),

    // 3) Drift del mercato regionale (throttlato internamente) + interessi IGB.
    'market_drift'   => static fn () => Economy::driftRegions(),
    'bank_accrue'    => static fn () => Bank::accrueAll(),

    // 4) Pianeti: crescita coloni, produzione, completamento Citadel.
    'planets'        => static fn () => Planets::tickDue(),

    // 5) NPC, eventi globali, feature di settore, fazioni, industria, contratti.
    'npc'                => static fn () => Npc::tick(),
    'event'              => static fn () => Events::tick(),
    'features'           => static fn () => \App\Game\SectorFeatures::tick(),
    'factions'           => static fn () => \App\Game\Faction::tick(),
    'industry'           => static fn () => \App\Game\Industry::tick(),
    'craft_jobs'         => static fn () => \App\Game\Industry::craftJobsTick(),
    'contracts_expired'  => static fn () => Contracts::expireDue(),

    // 5b) Notifica e-mail all'admin per le richieste di iscrizione + notiziario.
    'notify'         => static fn () => \App\Game\Notifier::tick(),
    'fednews'        => static fn () => \App\Game\FedNews::tick(),

    // 6) Ricalcolo classifiche, throttlato.
    'rating'         => static function () {
        $ogni  = GameConfig::int('rating.interval_min', 15);
        $ultimo = GameConfig::str('rating.last_run', '');
        if ($ultimo !== '' && (time() - strtotime($ultimo)) < $ogni * 60) {
            return null;
        }
        $out = Leaderboard::recalcAll();
        GameConfig::set('rating.last_run', date('Y-m-d H:i:s'));
        return $out;
    },

    // 7) Coda della posta: tenta i messaggi in attesa e pota i vecchi.
    'posta'          => static fn () => \App\Core\Posta::smista(),
    'tokens_gc'      => static function () {
        $n = \App\Auth\Auth::gcTokens(7);
        return $n > 0 ? ['removed' => $n] : null;
    },
    'crew_heal'      => static function () {
        $n = \App\Game\Crew::healDue();
        return $n > 0 ? ['healed' => $n] : null;
    },
    'iscrizioni_gc'  => static function () {
        $n = \App\Auth\Auth::gcPending();
        return $n > 0 ? ['removed' => $n] : null;
    },
    'posta_gc'       => static function () {
        $n = \App\Core\Posta::pota(GameConfig::int('mail.keep_days', 30));
        return $n > 0 ? ['removed' => $n] : null;
    },

    // 8) Potatura del diario del clock: senza, tick_runs cresce all'infinito.
    'tick_runs_gc'   => static fn () => TickHealth::gc(),
];

try {
    Database::run(
        'INSERT INTO tick_runs (started_at) VALUES (?)',
        [date('Y-m-d H:i:s.v', (int) $startedAt)]
    );
    $runId = Database::lastInsertId();
} catch (\Throwable $e) {
    // Se non riusciamo nemmeno ad aprire la corsa, il database non c'e':
    // inutile proseguire, i lavori fallirebbero tutti allo stesso modo.
    logTick('ERRORE di apertura: ' . $e->getMessage());
    fwrite(STDERR, '[tick] ' . $e->getMessage() . "\n");
    flock($lock, LOCK_UN);
    exit(1);
}

['tasks' => $tasks, 'falliti' => $falliti] = TickHealth::esegui(
    $jobs,
    static function (string $nome, \Throwable $e): void {
        logTick(sprintf('task %s FALLITO: %s @ %s:%d', $nome, $e->getMessage(), $e->getFile(), $e->getLine()));
    }
);

$durationMs = (int) round((microtime(true) - $startedAt) * 1000);
$ok = $falliti === [];
$note = $ok ? null : sprintf(
    '%d task su %d falliti: %s',
    count($falliti),
    count($jobs),
    implode(', ', array_keys($falliti))
);

try {
    Database::run(
        'UPDATE tick_runs SET finished_at = ?, ok = ?, duration_ms = ?, tasks = ?, note = ? WHERE id = ?',
        [date('Y-m-d H:i:s.v'), $ok ? 1 : 0, $durationMs, json_encode($tasks, JSON_UNESCAPED_UNICODE), $note !== null ? substr($note, 0, 255) : null, $runId]
    );
} catch (\Throwable $e) {
    logTick('ERRORE di chiusura: ' . $e->getMessage());
}

if ($ok) {
    logTick("ok in {$durationMs}ms " . json_encode($tasks, JSON_UNESCAPED_UNICODE));
} else {
    logTick("DEGRADATO in {$durationMs}ms — {$note}");
    fwrite(STDERR, "[tick] {$note}\n");
}

// Un guasto che dura e' peggio di un guasto isolato: avvisa una volta sola
// per episodio, non a ogni minuto.
try {
    TickHealth::segnalaSeNecessario();
} catch (\Throwable $e) {
    logTick('allarme non inviato: ' . $e->getMessage());
}

flock($lock, LOCK_UN);
exit($ok ? 0 : 1);

// ---------------------------------------------------------------------------

/**
 * Rollover del giorno di gioco: ricarica i turni di tutti i comandanti
 * che non hanno ancora "girato pagina" oggi (rispettando l'ora di reset).
 *
 * @return array{players:int, day:string}
 */
function handleTurnReset(): array
{
    $day = TurnManager::gameDay();
    $n = TurnManager::bulkReset();
    setConfigValue('turns.last_reset_date', $day);
    return ['players' => $n, 'day' => $day];
}

function configValue(string $key): ?string
{
    $row = Database::first('SELECT cvalue FROM game_config WHERE ckey = ?', [$key]);
    return $row['cvalue'] ?? null;
}

function setConfigValue(string $key, string $value): void
{
    Database::run(
        'INSERT INTO game_config (ckey, cvalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue)',
        [$key, $value]
    );
}

function logTick(string $msg): void
{
    $dir = (string) ($GLOBALS['__project_root'] ?? '.') . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/tick.log', sprintf("[%s] %s\n", date('c'), $msg), FILE_APPEND | LOCK_EX);
}
