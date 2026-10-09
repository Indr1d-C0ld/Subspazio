<?php

declare(strict_types=1);

/**
 * Quinto audit (09/10/2026, pomeriggio): le correzioni del quarto audit e il
 * codice nuovo della giornata riletti a mente fresca. Una prova per ogni
 * correzione lato server, vista fallire sul codice di prima (le correzioni dei
 * temi sono state verificate nel browser: vedi il messaggio del commit).
 *
 * Tiene il lock del clock: il tick vero non deve toccare gli NPC di prova.
 */

use App\Core\Database;
use App\Game\Cloak;
use App\Game\Combat;
use App\Game\Crew;
use App\Game\Modules;
use App\Game\PlayerService;

return static function (): void {
    $npcIds = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $s1 = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 0 AND band = 1 ORDER BY id LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $nave = static fn (int $sid): array => Database::first('SELECT * FROM ships WHERE id = ?', [$sid]);
    $reputazione = static fn (int $pid): array => array_column(
        Database::all('SELECT faction, value FROM player_reputation WHERE player_id = ? ORDER BY faction', [$pid]), 'value', 'faction');

    $lock = fopen((string) ($GLOBALS['__project_root'] ?? dirname(__DIR__)) . '/storage/tick.lock', 'c');
    $preso = false;
    for ($t = 0; $lock !== false && $t < 100 && !$preso; $t++) {
        $preso = flock($lock, LOCK_EX | LOCK_NB);
        if (!$preso) {
            usleep(100_000);
        }
    }
    Esito::verifica('preso il lock del clock, il cron non interferisce', $preso);

    try {
        Esito::sezione('La nave di soccorso non rende nulla, nemmeno quando attacca');

        // Il secondo account attacca il principale con la nave di soccorso e si
        // fa abbattere dalle sue difese: prima il principale prendeva
        // uccisione, esperienza e reputazione a ogni giro.
        [$alt, $altS] = Finti::comandante(0, ['fighters' => 20], $s1);
        Database::run("UPDATE ships SET type_key = 'scout_marauder', soccorso = 1, shields = 0 WHERE id = ?", [(int) $altS['id']]);
        [$main, $mainS] = Finti::comandante(0, ['fighters' => 8000, 'shields' => 1000], $s1);
        $repPrima = $reputazione((int) $main['id']);
        $out = Combat::attackShip($rileggi((int) $alt['id']), PlayerService::ship((int) $altS['id']), (int) $main['id']);
        $m = $rileggi((int) $main['id']);
        Esito::verifica('l\'aggressore e\' abbattuto dalle difese', !empty($out['destroyed_self']));
        Esito::uguale('chi si difende non conta un\'uccisione', 0, (int) $m['kills']);
        Esito::uguale('ne\' prende esperienza', 0, (int) $m['experience']);

        // E abbattendo lui una nave di soccorso, niente reputazione da coltivare
        // (l'omicidio, pero', la Federazione lo conta: sesto audit).
        [$alt2, $alt2S] = Finti::comandante(0, ['fighters' => 5], $s1);
        Database::run("UPDATE ships SET type_key = 'scout_marauder', soccorso = 1, shields = 0 WHERE id = ?", [(int) $alt2S['id']]);
        Combat::attackShip($rileggi((int) $main['id']), PlayerService::ship((int) $mainS['id']), (int) $alt2['id']);
        $repDopo = $reputazione((int) $main['id']);
        unset($repPrima['fed'], $repDopo['fed']);
        Esito::uguale('ne\' reputazione con le fazioni da guadagnare', $repPrima, $repDopo);

        Esito::sezione('Un attacco respinto all\'ultimo non brucia nulla');

        [$a, $as] = Finti::comandante(0, ['fighters' => 1000], $s1);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1, cloak_carica = 8 WHERE id = ?', [(int) $as['id']]);
        Crew::addPending((int) $a['id'], 'attack_bonus_pct', 25, date('Y-m-d H:i:s', time() + 3600));
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, band, fighters, shields, combat_rating, credits, aggression, last_move_at)
             VALUES ('pirate', '__test_npc', 'missile_frigate', ?, 1, 10, 0, 1.0, 100, 100, NOW())",
            [$s1]
        );
        $npcIds[] = $preda = Database::lastInsertId();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::first('SELECT id FROM npcs WHERE id = ? FOR UPDATE', [$preda]);
        Database::run('DELETE FROM npcs WHERE id = ?', [$preda]);   // l'ha appena abbattuto un altro
        $ph = proc_open(['php', __DIR__ . '/_corsa.php', 'attacca_npc', '0', (string) $a['id'], (string) $preda],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe);
        usleep(1_500_000);
        $pdo->commit();
        $esito = is_resource($ph) ? proc_close($ph) : -1;
        Esito::uguale('il secondo attacco e\' respinto', 1, $esito);
        // Prima: Nucleo in sovraccarico speso e occultamento caduto, per un
        // attacco che non c'e' stato.
        Esito::uguale('il Nucleo in sovraccarico resta da usare', 1, (int) Database::first(
            "SELECT COUNT(*) n FROM crew_pending WHERE player_id = ? AND effect = 'attack_bonus_pct'", [(int) $a['id']])['n']);
        Esito::uguale('e la nave resta occultata', 1, (int) $nave((int) $as['id'])['cloaked']);

        Esito::sezione('Moduli e occultamento');

        [$g, $gs] = Finti::comandante(0, ['fighters' => 10_500, 'shields' => 5000], $sd);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled, broken_at) VALUES (?, 'weapon', 'w_rastrelliera', '{\"max_fighters_pct\":10}', NOW())", [(int) $gs['id']]);
        $guasto = Database::lastInsertId();
        $out = Modules::remove($rileggi((int) $g['id']), PlayerService::ship((int) $gs['id']), $guasto);
        // Un hangar guasto non conta nel tetto: toglierlo non lo abbassa.
        // Prima: rifiutato, «schierane prima 500».
        Esito::verifica('smontare un hangar guasto si puo\'', !empty($out['ok']), (string) ($out['error'] ?? ''));
        Esito::uguale('e gli scudi non si toccano (il tetto non e\' cambiato)', 5000, (int) $nave((int) $gs['id'])['shields']);

        [$o, $os] = Finti::comandante(0, [], $s1);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1, cloak_carica = 8 WHERE id = ?', [(int) $os['id']]);
        $vecchia = ['cloaked' => 0] + PlayerService::ship((int) $os['id']);   // letta prima del primo «accendi»
        $out = Cloak::toggle($rileggi((int) $o['id']), $vecchia, true);
        Esito::verifica('un secondo «accendi» non e\' un errore', !empty($out['ok']), (string) ($out['error'] ?? ''));

        Esito::sezione('Stalli del database');

        $stallo = new PDOException('Deadlock found');
        $stallo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $cambiata = new PDOException('Record has changed since last read');
        $cambiata->errorInfo = ['HY000', 1020, 'Record has changed since last read'];
        $rilancia = static function (PDOException $e): bool {
            try {
                Database::rilanciaSeAnnullata($e);
                return false;
            } catch (PDOException) {
                return true;
            } catch (\Error) {
                return false;   // il codice di prima non ha questo attrezzo
            }
        };
        $pdo->beginTransaction();
        $dentroStallo = $rilancia($stallo);
        $dentroCambiata = $rilancia($cambiata);
        $pdo->rollBack();
        Esito::verifica('dentro una transazione, uno stallo si rilancia', $dentroStallo);
        Esito::verifica('e anche una riga cambiata (snapshot isolation)', $dentroCambiata);
        // Fuori da una transazione l'azione e' gia' confermata: un contatore
        // perso non deve diventare un errore 500 dopo uno scambio riuscito.
        Esito::verifica('fuori da una transazione si ingoia', !$rilancia($stallo));

        Esito::sezione('Stagione e tempo reale');

        // MAI chiamare Season::close() da una prova: e' l'azzeramento vero del
        // gioco. Il 09/10 questa stessa prova, lanciata su una copia del codice
        // di prima che il parametro non lo conosceva, ha chiuso la stagione in
        // corso. Si verifica solo la regola, che non tocca nulla.
        $numero = (int) \App\Game\Season::current()['number'];
        $ammessa = static function (?int $n): string {
            try {
                return (string) (\App\Game\Season::chiusuraAmmessa($n) ?? 'ammessa');
            } catch (\Error) {
                return 'regola assente';
            }
        };
        // Prima: un doppio invio del modulo chiudeva anche la stagione nuova.
        Esito::verifica('chiudere una stagione gia\' chiusa e\' respinto', str_contains($ammessa($numero - 1), 'gia\' stata chiusa'), $ammessa($numero - 1));
        Esito::uguale('quella in corso si puo\' chiudere', 'ammessa', $ammessa($numero));
        Esito::uguale('e senza numero vale la regola di sempre', 'ammessa', $ammessa(null));

        [$t] = Finti::comandante(0, [], $s1);
        $aperti = [];
        for ($i = 0; $i < 4; $i++) {
            $aperti[] = \App\Game\Live::apriStream((int) $t['id']);
        }
        // Le tre piu' recenti sono di processi morti senza chiudersi.
        Database::run('UPDATE live_streams SET visto_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE id IN (?, ?, ?)', [$aperti[1], $aperti[2], $aperti[3]]);
        Esito::verifica('stream morti non chiudono uno vivo', !\App\Game\Live::streamSuperato((int) $t['id'], $aperti[0]));

        Esito::sezione('Notiziario — i comunicati della Federazione');

        // La configurazione cambia solo in memoria: il comunicato vero resta.
        \App\Game\GameConfig::str('fednews.comunicato', '');
        $cache = new ReflectionProperty(\App\Game\GameConfig::class, 'cache');
        $c0 = $cache->getValue();
        $titoli = static function (string $testo, string $fino) use ($cache, $c0): array {
            $cache->setValue(null, ['fednews.comunicato' => $testo, 'fednews.comunicato_fino' => $fino] + (array) $c0);
            try {
                return (new ReflectionMethod(\App\Game\FedNews::class, 'compose'))->invoke(null);
            } finally {
                $cache->setValue(null, $c0);
            }
        };
        $domani = date('Y-m-d H:i', time() + 86400);
        $t = $titoli('__test_ primo comunicato | __test_ secondo', $domani);
        // Prima il notiziario non sapeva dire nulla che i numeri non dicessero.
        Esito::uguale('i comunicati aprono il bollettino, nell\'ordine', ['__test_ primo comunicato', '__test_ secondo'], array_slice($t, 0, 2));
        Esito::verifica('scaduti, spariscono', !in_array('__test_ primo comunicato', $titoli('__test_ primo comunicato', date('Y-m-d H:i', time() - 60)), true));
        Esito::verifica('senza scadenza, restano', in_array('__test_ fisso', $titoli('__test_ fisso', ''), true));
        Esito::verifica('una scadenza illeggibile vale come scaduta', !in_array('__test_ x', $titoli('__test_ x', 'domani'), true));
    } finally {
        if ($npcIds !== []) {
            Database::run('DELETE FROM npcs WHERE id IN (' . implode(',', array_fill(0, count($npcIds), '?')) . ')', $npcIds);
        }
        Database::run("DELETE FROM npcs WHERE name = '__test_npc' OR target_player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
        Database::run("DELETE le FROM live_events le JOIN players p ON le.scope = 'player' AND p.id = le.scope_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        Database::run("DELETE a FROM alerts a JOIN players p ON p.id = a.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        if ($preso) {
            flock($lock, LOCK_UN);
        }
    }
};
