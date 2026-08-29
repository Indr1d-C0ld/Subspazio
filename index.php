<?php

declare(strict_types=1);

/**
 * SubSpazio — front controller unico.
 * Tutte le richieste applicative passano da qui (via PATH_INFO o FallbackResource).
 */

define('SUBSPAZIO', true);
define('APP_START', microtime(true));

$projectRoot = __DIR__;

require $projectRoot . '/src/autoload.php';
require $projectRoot . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$GLOBALS['__project_root'] = $projectRoot;

// --- Configurazione ------------------------------------------------------------
try {
    Config::load($projectRoot);
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>SubSpazio — setup</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;background:#0b0f17;color:#c9d4e3;max-width:44rem;margin:4rem auto;padding:0 1.5rem}'
        . 'code{background:#1a2333;padding:.15em .4em;border-radius:4px}h1{color:#7fd1ff}</style>'
        . '<h1>Configurazione mancante</h1><p>Impossibile avviare l\'applicazione:</p>'
        . '<pre><code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</code></pre>'
        . '<p>Crea il file <code>/data/subspazio-config/config.php</code> partendo da '
        . '<code>config/config.example.php</code>, poi esegui le migrazioni con '
        . '<code>php bin/console.php migrate</code>.</p>';
    exit;
}

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

View::setPath($projectRoot . '/views');
Session::start();

// --- Richiesta ---------------------------------------------------------------
$forcedBase = Config::get('app.base_path');
$request = new Request(
    (bool) Config::get('app.pretty_urls', false),
    is_string($forcedBase) ? $forcedBase : null,
);
$GLOBALS['__base_path']  = $request->basePath();
$GLOBALS['__url_prefix'] = $request->urlPrefix();

// --- Routing ---------------------------------------------------------------
$router = new Router();
require $projectRoot . '/src/routes.php';

try {
    $response = $router->dispatch($request);
} catch (\Throwable $e) {
    logger(sprintf('%s: %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()), 'error');

    $isDb = str_contains(strtolower($e->getMessage()), 'database')
        || str_contains(strtolower($e->getMessage()), 'connessione al database');

    if ($isDb) {
        $body = View::render('errors/db', [
            'title'  => 'Servizio non disponibile',
            'debug'  => $debug,
            'detail' => $e->getMessage(),
        ]);
        $response = Response::html($body, 503);
    } else {
        $body = View::render('errors/generic', [
            'title'   => 'Errore interno',
            'status'  => 500,
            'message' => $debug
                ? $e::class . ': ' . $e->getMessage()
                : 'Si e\' verificato un errore imprevisto. L\'incidente e\' stato registrato.',
        ]);
        $response = Response::html($body, 500);
    }
}

$response->send();
