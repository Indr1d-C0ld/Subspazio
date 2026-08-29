<?php

declare(strict_types=1);

/** Scorciatoia: equivalente a `php bin/console.php migrate`. */

$projectRoot = require __DIR__ . '/_bootstrap.php';

use App\Cli\Migrator;

try {
    foreach ((new Migrator($projectRoot . '/db/migrations'))->migrate() as $line) {
        fwrite(STDOUT, $line . "\n");
    }
    fwrite(STDOUT, "Fatto.\n");
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRORE: ' . $e->getMessage() . "\n");
    exit(1);
}
