<?php

declare(strict_types=1);

/**
 * SubSpazio — console amministrativa.
 *
 *   php bin/console.php migrate
 *   php bin/console.php make:admin <username> [email]
 *   php bin/console.php user:approve <username>
 *   php bin/console.php user:list
 *   php bin/console.php config:get [chiave]
 *   php bin/console.php config:set <chiave> <valore>
 */

$projectRoot = require __DIR__ . '/_bootstrap.php';

use App\Cli\Migrator;
use App\Core\Database;
use App\Game\Bank;
use App\Game\Economy;
use App\Game\GameConfig;
use App\Game\PortGenerator;
use App\Game\Universe;
use App\Game\UniverseGenerator;

$argv = $_SERVER['argv'];
$cmd  = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

function out(string $s = ''): void
{
    fwrite(STDOUT, $s . "\n");
}

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    if ($hidden && stripos(PHP_OS, 'WIN') === false) {
        shell_exec('stty -echo 2>/dev/null');
        $val = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
        return $val;
    }
    return trim((string) fgets(STDIN));
}

function hashPassword(string $pw): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
    }
    return password_hash($pw, PASSWORD_DEFAULT, ['cost' => 12]);
}

try {
    switch ($cmd) {
        case 'migrate':
            out('Migrazioni:');
            foreach ((new Migrator($projectRoot . '/db/migrations'))->migrate() as $line) {
                out($line);
            }
            out('Fatto.');
            break;

        case 'make:admin':
            $username = $args[0] ?? '';
            if ($username === '') {
                out('Uso: php bin/console.php make:admin <username> [email]');
                exit(1);
            }
            $existing = Database::first('SELECT id, username FROM users WHERE username = ?', [$username]);
            if ($existing !== null) {
                Database::run(
                    "UPDATE users SET role = 'admin', status = 'active', approved_at = NOW() WHERE id = ?",
                    [$existing['id']]
                );
                out("Utente '{$username}' promosso ad admin e attivato.");
                break;
            }
            $email = $args[1] ?? prompt('Email: ');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                out('Email non valida.');
                exit(1);
            }
            $pw1 = prompt('Password (min 10): ', true);
            $pw2 = prompt('Conferma password: ', true);
            if (strlen($pw1) < 10 || $pw1 !== $pw2) {
                out('Password troppo corta o non coincidente.');
                exit(1);
            }
            Database::run(
                "INSERT INTO users (username, email, password_hash, display_name, status, role, approved_at)
                 VALUES (?, ?, ?, ?, 'active', 'admin', NOW())",
                [$username, mb_strtolower($email), hashPassword($pw1), $username]
            );
            out("Admin '{$username}' creato (id " . Database::lastInsertId() . ').');
            break;

        case 'user:approve':
            $username = $args[0] ?? '';
            $n = Database::run(
                "UPDATE users SET status = 'active', approved_at = NOW()
                 WHERE username = ? AND status IN ('pending','suspended')",
                [$username]
            )->rowCount();
            out($n > 0 ? "Attivato: {$username}" : "Nessuna modifica per '{$username}'.");
            break;

        case 'user:list':
            $rows = Database::all(
                'SELECT id, username, email, status, role, created_at, last_login_at
                 FROM users ORDER BY id'
            );
            if ($rows === []) {
                out('(nessun utente)');
                break;
            }
            out(str_pad('ID', 5) . str_pad('UTENTE', 20) . str_pad('STATO', 12) . str_pad('RUOLO', 12) . 'EMAIL');
            foreach ($rows as $r) {
                out(
                    str_pad((string) $r['id'], 5)
                    . str_pad((string) $r['username'], 20)
                    . str_pad((string) $r['status'], 12)
                    . str_pad((string) $r['role'], 12)
                    . (string) $r['email']
                );
            }
            break;

        case 'config:get':
            $key = $args[0] ?? null;
            $rows = $key !== null
                ? Database::all('SELECT ckey, cvalue, ctype FROM game_config WHERE ckey = ?', [$key])
                : Database::all('SELECT ckey, cvalue, ctype FROM game_config ORDER BY ckey');
            foreach ($rows as $r) {
                out(str_pad((string) $r['ckey'], 28) . '= ' . $r['cvalue'] . '  (' . $r['ctype'] . ')');
            }
            if ($rows === []) {
                out('(nessun valore)');
            }
            break;

        case 'config:set':
            if (count($args) < 2) {
                out('Uso: php bin/console.php config:set <chiave> <valore>');
                exit(1);
            }
            [$key, $value] = [$args[0], $args[1]];
            Database::run(
                'INSERT INTO game_config (ckey, cvalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue)',
                [$key, $value]
            );
            out("Impostato {$key} = {$value}");
            break;

        case 'universe:generate':
            $force = in_array('--force', $args, true);
            $count = 0;
            foreach ($args as $a) {
                if (preg_match('/^--sectors=(\d+)$/', $a, $mm)) {
                    $count = (int) $mm[1];
                }
            }
            $cfg = [
                'sectors'         => $count > 0 ? $count : GameConfig::int('universe.sectors', 1000),
                'fedspace_max'    => GameConfig::int('universe.fedspace_max', 10),
                'stardock_sector' => GameConfig::int('universe.stardock_sector', 1),
                'warp_density'    => GameConfig::float('universe.warp_density', 3.2),
            ];
            out("Generazione universo: {$cfg['sectors']} settori, densita' {$cfg['warp_density']}"
                . ($force ? ' (--force)' : '') . ' ...');
            $stats = (new UniverseGenerator($cfg))->generate($force);
            foreach ($stats as $k => $v) {
                out('  ' . str_pad($k, 16) . ' = ' . (is_bool($v) ? ($v ? 'si' : 'no') : $v));
            }
            out('Genero i porti ...');
            $pstats = PortGenerator::generate(true);
            foreach ($pstats as $k => $v) {
                out('  ' . str_pad($k, 16) . ' = ' . (is_array($v) ? json_encode($v) : $v));
            }
            out('Fatto. game.status = active.');
            break;

        case 'ports:generate':
            $pstats = PortGenerator::generate(in_array('--force', $args, true));
            foreach ($pstats as $k => $v) {
                out('  ' . str_pad($k, 16) . ' = ' . (is_array($v) ? json_encode($v) : $v));
            }
            out('Fatto.');
            break;

        case 'economy:drift':
            $n = Economy::driftRegions(true);
            out("Drift applicato a {$n} righe di mercato.");
            break;

        case 'bank:accrue':
            out('Interessi maturati su ' . Bank::accrueAll() . ' conti.');
            break;

        case 'universe:stats':
            if (!Universe::exists()) {
                out('Nessun universo generato.');
                break;
            }
            $s = Database::first('SELECT COUNT(*) AS c FROM sectors')['c'];
            $w = Database::first('SELECT COUNT(*) AS c FROM warps')['c'];
            $ow = Database::first(
                'SELECT COUNT(*) AS c FROM warps a
                 WHERE NOT EXISTS (SELECT 1 FROM warps b WHERE b.from_sector = a.to_sector AND b.to_sector = a.from_sector)'
            )['c'];
            $fed = Database::first('SELECT COUNT(*) AS c FROM sectors WHERE is_fedspace = 1')['c'];
            $ports = Database::first('SELECT COUNT(*) AS c FROM sectors WHERE has_port = 1')['c'];
            $players = Database::first('SELECT COUNT(*) AS c FROM players')['c'];
            $trades = Database::first('SELECT COUNT(*) AS c FROM trade_log')['c'] ?? 0;
            out("settori     = {$s}");
            out("warp        = {$w}  (media " . round($w / max(1, (int) $s), 2) . " per settore)");
            out("a senso unico = {$ow}");
            out("fedspace    = {$fed}");
            out("porti       = {$ports}");
            out("giocatori   = {$players}");
            out("scambi log  = {$trades}");
            out('universo generato il ' . GameConfig::str('universe.generated_at', '(mai)'));
            out('porti generati il  ' . GameConfig::str('economy.generated_at', '(mai)'));
            if (Database::first('SELECT 1 x FROM commodity_market LIMIT 1')) {
                out('-- valori base di mercato per regione --');
                foreach (Database::all(
                    'SELECT r.name, m.commodity, ROUND(m.base_value,2) v, ROUND(m.anchor,2) a
                     FROM commodity_market m JOIN regions r ON r.id = m.region_id
                     ORDER BY r.id, m.commodity'
                ) as $row) {
                    out(sprintf('  %-22s %-11s %7s  (anchor %s)', $row['name'], $row['commodity'], $row['v'], $row['a']));
                }
            }
            break;

        case 'db:fresh':
            $dbName = (string) \App\Core\Config::get('db.name', '');
            $tables = array_column(Database::all(
                'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?',
                [$dbName]
            ), 't');
            if ($tables !== []) {
                $pdo = Database::pdo();
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($tables as $t) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $t) . '`');
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                out('Eliminate ' . count($tables) . ' tabelle da ' . $dbName . '.');
            } else {
                out('Nessuna tabella da eliminare.');
            }
            out('Migrazioni:');
            foreach ((new Migrator($projectRoot . '/db/migrations'))->migrate() as $line) {
                out($line);
            }
            out('Schema ricreato da zero.');
            break;

        case 'help':
        default:
            out('Comandi disponibili:');
            out('  migrate                         applica le migrazioni SQL');
            out('  db:fresh                        elimina tutte le tabelle e ri-migra');
            out('  universe:generate [--force] [--sectors=N]   genera universo + porti');
            out('  universe:stats                  statistiche di universo ed economia');
            out('  ports:generate [--force]        (ri)genera solo i porti');
            out('  economy:drift                   forza un passo di drift del mercato');
            out('  bank:accrue                     matura gli interessi bancari');
            out('  make:admin <username> [email]    crea o promuove un amministratore');
            out('  user:approve <username>          attiva un account');
            out('  user:list                       elenca gli utenti');
            out('  config:get [chiave]             legge la configurazione di gioco');
            out('  config:set <chiave> <valore>    scrive un valore di configurazione');
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRORE: ' . $e->getMessage() . "\n");
    exit(1);
}
