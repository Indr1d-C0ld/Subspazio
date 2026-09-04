<?php

declare(strict_types=1);

/**
 * SubSpazio — clock interno. Da eseguire ogni minuto via cron:
 *
 *   * * * * * /usr/bin/php /data/html/subspazio/bin/tick.php >> /data/html/subspazio/storage/logs/cron.log 2>&1
 *
 * Fase 0: heartbeat, GC dei rate limit, gestione del reset turni giornaliero
 * (il refill per-giocatore arrivera' con la Fase 1).
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

$lockFile = $projectRoot . '/storage/tick.lock';
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "[tick] esecuzione precedente ancora in corso, salto.\n");
    exit(0);
}

$startedAt = microtime(true);
$tasks = [];
$runId = null;

try {
    Database::run(
        'INSERT INTO tick_runs (started_at) VALUES (?)',
        [date('Y-m-d H:i:s.v', (int) $startedAt)]
    );
    $runId = Database::lastInsertId();

    // 1) Garbage collection dei rate limit + eventi live scaduti + giornale di bordo.
    $tasks['rate_limits_gc'] = RateLimiter::gc();
    $tasks['live_gc'] = Live::gc();
    $tasks['shiplog_gc'] = \App\Game\ShipLog::gc();

    // 2) Reset turni giornaliero.
    $tasks['turn_reset'] = handleTurnReset();

    // 3) Drift del mercato regionale (throttlato internamente) + interessi IGB.
    $tasks['market_drift'] = Economy::driftRegions();
    $tasks['bank_accrue']  = Bank::accrueAll();

    // 4) Pianeti: crescita coloni, produzione, completamento Citadel.
    $tasks['planets'] = Planets::tickDue();

    // 5) NPC (movimento, ingaggio, respawn) + eventi globali + contratti scaduti.
    $tasks['npc'] = Npc::tick();
    $tasks['event'] = Events::tick();
    $tasks['features'] = \App\Game\SectorFeatures::tick();
    $tasks['factions'] = \App\Game\Faction::tick();
    $tasks['industry'] = \App\Game\Industry::tick();
    $tasks['contracts_expired'] = Contracts::expireDue();

    // 6) Ricalcolo classifiche (throttlato).
    $ratingEvery = GameConfig::int('rating.interval_min', 15);
    $ratingLast = GameConfig::str('rating.last_run', '');
    if ($ratingLast === '' || (time() - strtotime($ratingLast)) >= $ratingEvery * 60) {
        $tasks['rating'] = Leaderboard::recalcAll();
        GameConfig::set('rating.last_run', date('Y-m-d H:i:s'));
    }

    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    Database::run(
        'UPDATE tick_runs SET finished_at = ?, ok = 1, duration_ms = ?, tasks = ? WHERE id = ?',
        [date('Y-m-d H:i:s.v'), $durationMs, json_encode($tasks, JSON_UNESCAPED_UNICODE), $runId]
    );

    logTick("ok in {$durationMs}ms " . json_encode($tasks));
} catch (\Throwable $e) {
    if ($runId !== null) {
        try {
            Database::run(
                'UPDATE tick_runs SET finished_at = ?, ok = 0, note = ? WHERE id = ?',
                [date('Y-m-d H:i:s.v'), substr($e->getMessage(), 0, 255), $runId]
            );
        } catch (\Throwable) {
            // ignora
        }
    }
    logTick('ERRORE: ' . $e->getMessage());
    fwrite(STDERR, '[tick] ' . $e->getMessage() . "\n");
    flock($lock, LOCK_UN);
    exit(1);
}

flock($lock, LOCK_UN);
exit(0);

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
