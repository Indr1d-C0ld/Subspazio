<?php

declare(strict_types=1);

/**
 * Esegue i test di integrazione.
 *
 *   php tests/run.php              tutti
 *   php tests/run.php economica    solo i file il cui nome contiene "economica"
 *
 * ATTENZIONE: non esiste un database di prova separato. I test girano su
 * quello configurato in config.php, ma creano e distruggono solo righe
 * sintetiche col prefisso `__test_`; nessun dato reale viene letto o scritto.
 * Da non lanciare mentre e' in corso una sessione di gioco affollata: le
 * prove aprono transazioni su tabelle vive.
 */

$projectRoot = require dirname(__DIR__) . '/bin/_bootstrap.php';

require __DIR__ . '/_harness.php';

$filtro = $argv[1] ?? '';

$file = array_values(array_filter(
    glob(__DIR__ . '/*.php') ?: [],
    static fn (string $f) => !str_starts_with(basename($f), '_')
        && basename($f) !== 'run.php'
        && ($filtro === '' || str_contains(basename($f), $filtro))
));
sort($file);

if ($file === []) {
    fwrite(STDERR, "Nessun test corrisponde a \"{$filtro}\".\n");
    exit(1);
}

echo "\nSubSpazio — test di integrazione\n";
echo str_repeat('=', 62) . "\n";
echo 'Database: ' . (string) App\Core\Config::get('db.name', '?') . ' su ' . (string) App\Core\Config::get('db.host', '?') . "\n";

$orfani = Finti::spazzaOrfani();
if ($orfani > 0) {
    echo "Ripuliti {$orfani} resti di una corsa precedente interrotta.\n";
}

$inizio = microtime(true);
$errore = null;

try {
    foreach ($file as $f) {
        $nome = basename($f, '.php');
        echo "\n" . str_repeat('-', 62) . "\n";
        echo strtoupper(str_replace('_', ' ', $nome)) . "\n";

        $prova = require $f;
        if (!is_callable($prova)) {
            Esito::verifica("{$nome}: il file non restituisce una closure eseguibile", false);
            continue;
        }
        $prova();
    }
} catch (\Throwable $e) {
    $errore = $e;
} finally {
    Finti::pulisci();
}

$durata = (int) round((microtime(true) - $inizio) * 1000);
$residui = Finti::residui();

echo "\n" . str_repeat('=', 62) . "\n";

if ($errore !== null) {
    printf("INTERROTTO da %s: %s\n  in %s:%d\n", $errore::class, $errore->getMessage(), $errore->getFile(), $errore->getLine());
}
printf("Pulizia: %d righe sintetiche residue.\n", $residui);

$falliti = Esito::falliti();
printf(
    "%s — %d verifiche, %d fallite, %d ms\n\n",
    $falliti === 0 && $errore === null ? 'TUTTO OK' : 'PROBLEMI',
    Esito::totali(),
    $falliti,
    $durata
);

exit(($falliti === 0 && $errore === null && $residui === 0) ? 0 : 1);
