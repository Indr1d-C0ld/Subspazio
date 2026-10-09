<?php

declare(strict_types=1);

/**
 * Sesto audit (09/10/2026, sera): le correzioni del quinto audit e i
 * comunicati riletti a mente fresca, piu' le prove dal campo. Una prova per
 * ogni correzione lato server.
 *
 * Nessuna prova chiama operazioni che azzerano il gioco: la chiusura di
 * stagione si prova con la sua regola (chiusuraAmmessa), dentro una
 * transazione annullata, e il suo ordine con una lettura del sorgente.
 *
 * Tiene il lock del clock: il tick vero non deve toccare gli NPC di prova.
 */

use App\Core\Database;
use App\Game\Combat;
use App\Game\FedNews;
use App\Game\GameConfig;
use App\Game\Modules;
use App\Game\PlayerService;
use App\Game\Season;
use App\Game\TurnManager;

return static function (): void {
    $npcIds = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $s1 = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 0 AND band = 1 ORDER BY id LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $nave = static fn (int $sid): array => Database::first('SELECT * FROM ships WHERE id = ?', [$sid]);
    $reputazione = static fn (int $pid): array => array_map('intval', array_column(
        Database::all('SELECT faction, value FROM player_reputation WHERE player_id = ? ORDER BY faction', [$pid]), 'value', 'faction'));
    $pdo = Database::pdo();

    $lock = fopen((string) ($GLOBALS['__project_root'] ?? dirname(__DIR__)) . '/storage/tick.lock', 'c');
    $preso = false;
    for ($t = 0; $lock !== false && $t < 100 && !$preso; $t++) {
        $preso = flock($lock, LOCK_EX | LOCK_NB);
        if (!$preso) {
            usleep(100_000);
        }
    }
    Esito::verifica('preso il lock del clock, il cron non interferisce', $preso);

    // La configurazione cambia solo in memoria: il comunicato vero resta.
    GameConfig::str('fednews.comunicato', '');
    $cache = new ReflectionProperty(GameConfig::class, 'cache');
    $c0 = $cache->getValue();
    $conComunicati = static function (string $testo, string $fino, callable $fai) use ($cache, $c0): mixed {
        $cache->setValue(null, ['fednews.comunicato' => $testo, 'fednews.comunicato_fino' => $fino] + (array) $c0);
        try {
            return $fai();
        } finally {
            $cache->setValue(null, $c0);
        }
    };
    $componi = static fn (): array => (new ReflectionMethod(FedNews::class, 'compose'))->invoke(null);
    $domani = date('Y-m-d H:i', time() + 86400);

    try {
        Esito::sezione('Notiziario — entra intero nella radio');

        // Il bollettino del 9 ottobre, coi comunicati, superava i 500
        // caratteri della colonna: alle 21:30 l'invio in radio sarebbe fallito
        // (STRICT_TRANS_TABLES), un giorno dopo l'altro.
        $lungo = str_repeat('__test_ comunicato lungo, come quelli veri. ', 10);
        $titoli = $conComunicati(trim($lungo) . ' | ' . trim($lungo), $domani, $componi);
        $corpo = "NOTIZIARIO DELLA FEDERAZIONE\n\n" . implode("\n", array_map(static fn ($h) => '• ' . $h, $titoli));
        $pdo->beginTransaction();
        try {
            Database::run("INSERT INTO messages (channel, from_name, body) VALUES ('fedcomm', '__test_', ?)", [$corpo]);
            $salvato = (string) Database::first('SELECT body FROM messages WHERE id = ?', [Database::lastInsertId()])['body'];
            Esito::verifica('un bollettino di ' . mb_strlen($corpo) . ' caratteri va in onda intero', $salvato === $corpo);
        } catch (PDOException $e) {
            Esito::verifica('un bollettino di ' . mb_strlen($corpo) . ' caratteri va in onda intero', false, $e->getMessage());
        } finally {
            $pdo->rollBack();
        }

        Esito::sezione('Notiziario — i comunicati non coprono le notizie');

        $quattro = '__test_ c1 | __test_ c2 | __test_ c3 | __test_ c4';
        $titoli = $conComunicati($quattro, $domani, $componi);
        $nostri = array_values(array_filter($titoli, static fn ($t) => str_starts_with($t, '__test_')));
        // La plancia mostra quattro titoli: quattro comunicati li prendevano tutti.
        Esito::uguale('al piu\' due comunicati in testa', ['__test_ c1', '__test_ c2'], $nostri);
        Esito::verifica('e le notizie del gioco restano fra i primi quattro titoli',
            array_filter(array_slice($titoli, 0, 4), static fn ($t) => !str_starts_with($t, '__test_')) !== []);

        // Il bollettino resta in plancia fino a due giorni: un comunicato
        // scaduto nel frattempo ci restava con lui.
        $pdo->beginTransaction();
        try {
            Database::run("INSERT INTO fednews (anchor, headlines, body) VALUES ('__test_', ?, '')",
                [json_encode(['__test_ scaduto', '__test_ notizia'], JSON_UNESCAPED_UNICODE)]);
            $inPlancia = $conComunicati('__test_ scaduto', date('Y-m-d H:i', time() - 60), static fn () => FedNews::latest()['headlines'] ?? []);
            Esito::uguale('un comunicato scaduto sparisce subito dalla plancia', ['__test_ notizia'], $inPlancia);
            $inPlancia = $conComunicati('__test_ scaduto', $domani, static fn () => FedNews::latest()['headlines'] ?? []);
            Esito::uguale('finche\' vale, resta', ['__test_ scaduto', '__test_ notizia'], $inPlancia);
        } finally {
            $pdo->rollBack();
        }

        Esito::sezione('Nave di soccorso — niente premio, ma l\'omicidio conta');

        // Il quinto audit saltava tutta la reputazione per capsule e navi di
        // soccorso, compresa la penalita' federale per l'omicidio di un onesto.
        [$main, $mainS] = Finti::comandante(0, ['fighters' => 8000, 'shields' => 1000], $s1);
        [$alt, $altS] = Finti::comandante(0, ['fighters' => 5], $s1);
        Database::run("UPDATE ships SET type_key = 'scout_marauder', soccorso = 1, shields = 0 WHERE id = ?", [(int) $altS['id']]);
        $prima = $reputazione((int) $main['id']);
        $out = Combat::attackShip($rileggi((int) $main['id']), PlayerService::ship((int) $mainS['id']), (int) $alt['id']);
        $dopo = $reputazione((int) $main['id']);
        Esito::verifica('la nave di soccorso e\' abbattuta', !empty($out['killed']) || !empty($out['ok']));
        Esito::uguale('la Federazione conta l\'omicidio', ($prima['fed'] ?? 0) - GameConfig::int('faction.kill_gain', 6), $dopo['fed'] ?? 0);
        Esito::uguale('ma l\'Egemonia non premia nessuno', $prima['hegemony'] ?? 0, $dopo['hegemony'] ?? 0);

        Esito::sezione('Mercantile gia\' sparito — l\'attacco respinto non costa nulla');

        // Fuga e chiamata di soccorso partivano prima del controllo sotto
        // lucchetto: un mercantile gia' abbattuto da un altro poteva «fuggire»,
        // con turni, occultamento, un crimine e una pattuglia a carico di chi
        // arrivava tardi. La fuga e' a caso (35%): sei tentativi.
        [$a, $as] = Finti::comandante(0, ['fighters' => 1000], $s1);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1, cloak_carica = 8 WHERE id = ?', [(int) $as['id']]);
        TurnManager::sync($rileggi((int) $a['id']));
        $turni = (int) $rileggi((int) $a['id'])['turns'];
        $riusciti = 0;
        for ($i = 0; $i < 6; $i++) {
            Database::run(
                "INSERT INTO npcs (kind, name, ship_type, sector_id, band, fighters, shields, combat_rating, credits, aggression, last_move_at)
                 VALUES ('trader', '__test_npc', 'merchant_freighter', ?, 1, 10, 0, 1.0, 100, 0, NOW())",
                [$s1]
            );
            $npcIds[] = $preda = Database::lastInsertId();
            $pdo->beginTransaction();
            Database::first('SELECT id FROM npcs WHERE id = ? FOR UPDATE', [$preda]);
            Database::run('DELETE FROM npcs WHERE id = ?', [$preda]);   // l'ha appena abbattuto un altro
            $ph = proc_open(['php', __DIR__ . '/_corsa.php', 'attacca_npc', '0', (string) $a['id'], (string) $preda],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe);
            usleep(1_500_000);
            $pdo->commit();
            $riusciti += proc_close($ph) === 0 ? 1 : 0;
        }
        Esito::uguale('sei attacchi respinti su sei', 0, $riusciti);
        Esito::uguale('nessun turno speso', $turni, (int) $rileggi((int) $a['id'])['turns']);
        Esito::uguale('la nave resta occultata', 1, (int) $nave((int) $as['id'])['cloaked']);
        Esito::uguale('nessun crimine', 0, (int) Database::first('SELECT COUNT(*) n FROM crimini WHERE player_id = ?', [(int) $a['id']])['n']);
        Esito::uguale('nessuna pattuglia alle calcagna', 0,
            (int) Database::first("SELECT COUNT(*) n FROM npcs WHERE kind = 'patrol' AND target_player_id = ?", [(int) $a['id']])['n']);

        Esito::sezione('Abilita\' degli ufficiali — una ricarica, un uso');

        // Due richieste insieme trovavano entrambe la ricarica libera (letta
        // fuori dalla transazione): l'Ingegnere riparava due volte.
        [$e, $es] = Finti::comandante(0, ['fighters' => 100], $sd);
        Database::run(
            "INSERT INTO officers (player_id, name, role, skills, status, assigned, ability_tier, level)
             VALUES (?, '__test_ingegnere', 'engineer', '{\"engineering\":5}', 'active', 1, 1, 1)",
            [(int) $e['id']]
        );
        $uff = Database::lastInsertId();
        $pdo->beginTransaction();
        Database::first('SELECT id FROM players WHERE id = ? FOR UPDATE', [(int) $e['id']]);
        $ph = proc_open(['php', __DIR__ . '/_corsa.php', 'abilita', '0', (string) $e['id'], (string) $uff],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe);
        usleep(1_500_000);
        // l'altra richiesta, quella arrivata prima, ha appena usato l'abilita'
        Database::run('UPDATE officers SET ready_at = DATE_ADD(NOW(), INTERVAL 90 MINUTE) WHERE id = ?', [$uff]);
        $pdo->commit();
        Esito::verifica('la seconda richiesta trova l\'abilita\' in ricarica', proc_close($ph) !== 0);
        Esito::uguale('e la nave non e\' riparata due volte', 100, (int) $nave((int) $es['id'])['fighters']);

        Esito::sezione('Stive — una stiva guasta non blocca l\'officina');

        // Stive piene con un modulo di stiva guasto (che non conta): prima
        // non si smontava nessun modulo, nemmeno quello guasto.
        [$h, $hs] = Finti::comandante(0, ['hold_ore' => 53], $sd);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled, broken_at) VALUES (?, 'utility', 'u_stiva', NULL, NOW())", [(int) $hs['id']]);
        $stivaGuasta = Database::lastInsertId();
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'weapon', 'w_autocannoni', NULL)", [(int) $hs['id']]);
        $cannone = Database::lastInsertId();
        $out = Modules::remove($rileggi((int) $h['id']), PlayerService::ship((int) $hs['id']), $cannone);
        Esito::verifica('si smonta un modulo che le stive non le tocca', !empty($out['ok']), (string) ($out['error'] ?? ''));
        $out = Modules::remove($rileggi((int) $h['id']), PlayerService::ship((int) $hs['id']), $stivaGuasta);
        Esito::verifica('e anche la stiva guasta', !empty($out['ok']), (string) ($out['error'] ?? ''));
        // ...ma la regola resta per chi le stive le abbassa davvero
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'utility', 'u_dimensionale', NULL)", [(int) $hs['id']]);
        $stivaBuona = Database::lastInsertId();
        Database::run('UPDATE ships SET hold_ore = 60 WHERE id = ?', [(int) $hs['id']]);
        $out = Modules::remove($rileggi((int) $h['id']), PlayerService::ship((int) $hs['id']), $stivaBuona);
        Esito::verifica('una stiva funzionante con il carico dentro no', empty($out['ok']), (string) ($out['error'] ?? ''));

        Esito::sezione('Stagione — la verifica non scrive');

        // chiusuraAmmessa passava da current(), che apre una stagione se non
        // ce n'e' una attiva: la verifica «innocua» delle prove poteva
        // scrivere. Qui la stagione attiva sparisce solo dentro una
        // transazione annullata.
        $pdo->beginTransaction();
        try {
            Database::run("UPDATE seasons SET status = 'ended' WHERE status = 'active'");
            $n = (int) Database::first('SELECT COUNT(*) n FROM seasons')['n'];
            $esito = Season::chiusuraAmmessa(1);
            Esito::uguale('senza stagione attiva non apre stagioni', $n, (int) Database::first('SELECT COUNT(*) n FROM seasons')['n']);
            Esito::verifica('e la chiusura di una stagione qualunque e\' respinta', $esito !== null);
        } finally {
            $pdo->rollBack();
        }

        // L'annuncio di fine stagione andava in onda e la radio veniva svuotata
        // subito dopo. Si legge il sorgente: la chiusura non si prova.
        $src = (string) file_get_contents(dirname(__DIR__) . '/src/Game/Season.php');
        $svuota = strpos($src, "'messages', 'msg_state'");
        $annuncio = strpos($src, 'Radio::system("FINE STAGIONE');
        Esito::verifica('l\'annuncio di fine stagione va in onda dopo l\'azzeramento della radio',
            $svuota !== false && $annuncio !== false && $annuncio > $svuota);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($npcIds !== []) {
            Database::run('DELETE FROM npcs WHERE id IN (' . implode(',', array_fill(0, count($npcIds), '?')) . ')', $npcIds);
        }
        Database::run("DELETE FROM npcs WHERE name = '__test_npc' OR target_player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
        Database::run("DELETE FROM crimini WHERE player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
        Database::run("DELETE le FROM live_events le JOIN players p ON le.scope = 'player' AND p.id = le.scope_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        Database::run("DELETE a FROM alerts a JOIN players p ON p.id = a.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        if ($preso) {
            flock($lock, LOCK_UN);
        }
    }
};
