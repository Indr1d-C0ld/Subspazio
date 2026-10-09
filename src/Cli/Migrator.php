<?php

declare(strict_types=1);

namespace App\Cli;

use App\Core\Database;

/**
 * Migratore SQL minimale: applica in ordine i file db/migrations/*.sql
 * non ancora registrati in schema_migrations.
 *
 * Nota: in MariaDB il DDL fa commit implicito, quindi non c'e' vera
 * atomicita' per-file. Le migrazioni vanno scritte idempotenti
 * (CREATE TABLE IF NOT EXISTS, INSERT ... ON DUPLICATE KEY UPDATE)
 * cosi' che un ri-lancio dopo un errore parziale sia sicuro.
 */
final class Migrator
{
    public function __construct(private string $migrationsDir)
    {
    }

    /** @return list<string> messaggi di log */
    /**
     * Migrazioni presenti su disco e non ancora applicate (versioni, in
     * ordine). Il clock non gira finche' ce ne sono: il codice e' gia' quello
     * nuovo e lo schema no.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $applied = array_flip(array_column(Database::all('SELECT version FROM schema_migrations'), 'version'));
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $file) {
            if (!isset($applied[basename($file, '.sql')])) {
                $out[] = basename($file, '.sql');
            }
        }
        return $out;
    }

    public function migrate(): array
    {
        $log = [];
        $pdo = Database::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(64) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $applied = array_column(
            Database::all('SELECT version FROM schema_migrations'),
            'version'
        );

        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $pending = 0;
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }
            $pending++;

            $statements = $this->splitStatements((string) file_get_contents($file));

            foreach ($statements as $i => $sql) {
                try {
                    $pdo->exec($sql);
                } catch (\Throwable $e) {
                    $snippet = preg_replace('/\s+/', ' ', substr($sql, 0, 120));
                    $log[] = "  FALLITA    {$version} (statement " . ($i + 1) . "): " . $e->getMessage();
                    throw new \RuntimeException(
                        "Migrazione {$version} fallita allo statement " . ($i + 1)
                        . " [{$snippet}...]: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            Database::run('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
            $log[] = "  applicata  {$version}  (" . count($statements) . ' statement)';
        }

        if ($pending === 0) {
            $log[] = '  nessuna migrazione da applicare (schema aggiornato)';
        }

        return $log;
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        // Solo i ritorni a capo veri. Con \R (senza /u) PCRE riconosce anche il
        // byte 0x85 come «a capo», e spezzava i caratteri UTF-8 che lo
        // contengono: «∅» e «★» arrivavano al database mutilati.
        $lines = preg_split('/\r\n|\n|\r/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        $out = [];
        foreach (explode(';', $sql) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }
        return $out;
    }
}
