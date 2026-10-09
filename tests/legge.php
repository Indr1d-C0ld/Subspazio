<?php

declare(strict_types=1);

/**
 * La legge della Federazione (07/10/2026): notorieta', pattuglie, taglia.
 *
 * Prima assaltare mercantili non costava niente: in una settimana un
 * comandante ne ha abbattuti 22 e incassato 823.688 cr con la taglia a zero.
 * Tiene il lock del clock: il tick vero non deve muovere ne' far ingaggiare le
 * pattuglie di prova a meta' strada.
 */

use App\Core\Database;
use App\Game\Combat;
use App\Game\Faction;
use App\Game\Legge;
use App\Game\PlayerService;

return static function (): void {
    $npcIds = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $s1 = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 0 AND band = 1 ORDER BY id LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $npc = static function (string $kind, int $sector, int $fighters, ?int $target = null) use (&$npcIds): array {
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, band, target_player_id, fighters, shields, combat_rating, credits, aggression, last_move_at)
             VALUES (?, '__test_npc', 'missile_frigate', ?, 1, ?, ?, 0, 1.0, 5000, ?, NOW())",
            [$kind, $sector, $target, $fighters, $kind === 'pirate' ? 100 : 0]
        );
        $npcIds[] = Database::lastInsertId();
        return Database::first('SELECT * FROM npcs WHERE id = ?', [end($npcIds)]);
    };
    $notorieta = static function (int $pid, float $punti, int $oreFa = 0): void {
        Database::run('UPDATE players SET notorieta = ?, notorieta_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE id = ?', [$punti, $oreFa, $pid]);
    };

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
        Esito::sezione('Notorieta\' — sale coi crimini, piu\' in fretta per i recidivi, e cala col tempo');

        [$a] = Finti::comandante(0, [], $s1);
        $pa = (int) $a['id'];
        $r1 = Legge::crimine($pa, 'mercantile', $s1);
        Esito::uguale('un\'aggressione a un mercantile vale 15 punti', 15.0, (float) $r1['aggiunti']);
        $r2 = Legge::crimine($pa, 'mercantile', $s1);
        Esito::uguale('la seconda, da recidivo, il 25% in piu\'', 18.75, (float) $r2['aggiunti']);
        Esito::verifica('e il comandante diventa Sospetto', $r2['grado'] === 1 && $r2['salito']);

        $notorieta($pa, 200, 24);
        Esito::verifica('dopo un giorno la notorieta\' e\' dimezzata', abs(Legge::puntiDi($pa) - 100) < 0.5,
            (string) round(Legge::puntiDi($pa), 2));

        Esito::scenario('assalto a un mercantile dalla plancia');
        [$b, $bs] = Finti::comandante(0, ['fighters' => 20000, 'shields' => 1000], $s1);
        $pb = (int) $b['id'];
        $m = $npc('trader', $s1, 10);
        // un proiettore d'interdizione al massimo: il mercantile non puo' fuggire
        $inchiodata = PlayerService::ship((int) $bs['id']);
        $inchiodata['mod_effects']['interdict_pct'] = 100;
        $rb = Combat::attackNpc($rileggi($pb), $inchiodata, (int) $m['id']);
        $tipi = array_column(Database::all('SELECT kind FROM crimini WHERE player_id = ? ORDER BY id', [$pb]), 'kind');
        // Prima: nessuna conseguenza oltre all'allineamento.
        Esito::verifica('aggressione e uccisione finiscono nella fedina', !empty($rb['killed']) && $tipi === ['mercantile', 'uccisione'],
            implode(',', $tipi));

        Esito::sezione('Pattuglie — fermano solo i ricercati, e arrestano');

        $pat = $npc('patrol', $s1, 50000);
        [$onesto] = Finti::comandante(0, [], $s1);
        Esito::verifica('un comandante onesto passa', Combat::npcLasciaStare($pat, $rileggi((int) $onesto['id'])));
        $notorieta($pa, 150);
        Esito::verifica('un ricercato no', Combat::npcLasciaStare($pat, $rileggi($pa)) === false);

        [$c, $cs] = Finti::comandante(10000, ['fighters' => 10, 'shields' => 0], $s1);
        $pc = (int) $c['id'];
        $notorieta($pc, 150);
        $out = Combat::npcEngagePlayer($pat, $rileggi($pc), PlayerService::ship((int) $cs['id']), true);
        [, $cs2] = Finti::ricarica($pc);
        // nella Cintura un predone razzia; una pattuglia arresta
        Esito::verifica('anche nella Cintura di Sol la pattuglia abbatte, non razzia', !empty($out['destroyed']) && $cs2['type_key'] === 'escape_pod',
            $cs2['type_key']);
        Esito::verifica('e torna sotto la soglia di ricercato (arresto)', abs(Legge::puntiDi($pc) - 75) < 0.5, (string) round(Legge::puntiDi($pc), 2));

        Esito::sezione('Squadre d\'intercettazione — tarate sulla nave del ricercato');

        [$d, $ds] = Finti::comandante(0, ['fighters' => 40000, 'shields' => 5000], $s1);
        $pd = (int) $d['id'];
        $notorieta($pd, 300);   // Pericoloso
        Legge::tick();
        $squadre = Database::all("SELECT * FROM npcs WHERE kind = 'patrol' AND target_player_id = ?", [$pd]);
        foreach ($squadre as $q) {
            $npcIds[] = (int) $q['id'];
        }
        Esito::uguale('un Pericoloso ha due squadre addosso', 2, count($squadre));
        Esito::verifica('ognuna con almeno i suoi caccia', $squadre !== [] && min(array_map(static fn ($q) => (int) $q['fighters'], $squadre)) >= 40000);
        Esito::verifica('lo StarDock gli chiude i servizi', Faction::stardockBlocked($pd) !== null);

        $mv = new ReflectionMethod(\App\Game\Npc::class, 'move');
        if ($squadre !== []) {
            $q = $squadre[0];
            Database::run('UPDATE npcs SET last_move_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?', [(int) $q['id']]);
            $prima = count(\App\Game\Universe::shortestPath((int) $q['sector_id'], $s1) ?? []);
            $mv->invoke(null);
            $dopo = (int) Database::first('SELECT sector_id FROM npcs WHERE id = ?', [(int) $q['id']])['sector_id'];
            Esito::verifica('e si avvicina di un salto', count(\App\Game\Universe::shortestPath($dopo, $s1) ?? []) === $prima - 1);
        }

        Esito::scenario('l\'ammenda chiude il fascicolo');
        $costo = Legge::costoAmmenda($pd);
        Database::run('UPDATE players SET credits = ? WHERE id = ?', [$costo, $pd]);
        $ra = Legge::ammenda($rileggi($pd));
        Esito::verifica('pagata', !empty($ra['ok']) && (int) $rileggi($pd)['credits'] === 0, (string) ($ra['error'] ?? ''));
        Esito::uguale('notorieta\' a zero', 0.0, Legge::puntiDi($pd));
        Esito::uguale('e le squadre richiamate', 0,
            (int) Database::first("SELECT COUNT(*) n FROM npcs WHERE kind = 'patrol' AND target_player_id = ?", [$pd])['n']);

        Esito::sezione('Mercantili — scortati, poveri di contanti, pronti a fuggire e a chiamare aiuto');

        $okScorta = true;
        for ($i = 0; $i < 4; $i++) {
            $id = \App\Game\Npc::spawnOne('trader', 5);
            $npcIds[] = (int) $id;
            $t = Database::first('SELECT n.*, s.band sb FROM npcs n JOIN sectors s ON s.id = n.sector_id WHERE n.id = ?', [$id]);
            [$ea, $ez] = \App\Game\Fasce::scortaMercanti((int) $t['sb']);
            [, $cz] = \App\Game\Fasce::creditiNpc((int) $t['sb']);
            $okScorta = $okScorta && (int) $t['scorta'] >= $ea && (int) $t['scorta'] <= $ez
                && (int) $t['fighters'] >= (int) $t['scorta'] && (int) $t['credits'] <= $cz * 1.2 * 0.3 + 1;
        }
        // Prima: in media 322 caccia e circa 58.000 cr di contanti.
        Esito::verifica('nell\'Orlo un mercantile ha 20.000-60.000 caccia di scorta e pochi contanti', $okScorta);

        Esito::scenario('una nave che non puo\' affondare il colpo attacca finche\' il mercantile scappa');
        [$g, $gs] = Finti::comandante(0, ['fighters' => 1, 'shields' => 1_000_000_000], $s1);
        $pg = (int) $g['id'];
        $preda = $npc('trader', $s1, 100);
        $prima = Combat::attackNpc($rileggi($pg), PlayerService::ship((int) $gs['id']), (int) $preda['id'], 1);
        $soccorso = Database::first("SELECT * FROM npcs WHERE kind = 'patrol' AND target_player_id = ? AND scade_at IS NOT NULL", [$pg]);
        if ($soccorso !== null) {
            $npcIds[] = (int) $soccorso['id'];
        }
        // nella Cintura di Sol la richiesta di soccorso trova sempre qualcuno
        Esito::verifica('la richiesta di soccorso fa partire una pattuglia', !empty($prima['soccorso']) && $soccorso !== null);
        Esito::verifica('che insegue l\'aggressore anche se non e\' ricercato',
            $soccorso !== null && !Legge::ricercato($rileggi($pg)) && !Combat::npcLasciaStare($soccorso, $rileggi($pg)));
        $fuggito = !empty($prima['fled']) ? $prima : null;
        for ($i = 0; $i < 30 && $fuggito === null; $i++) {
            $ri = Combat::attackNpc($rileggi($pg), PlayerService::ship((int) $gs['id']), (int) $preda['id'], 1);
            if (!empty($ri['fled'])) {
                $fuggito = $ri;
            }
        }
        $dove = (int) Database::first('SELECT sector_id FROM npcs WHERE id = ?', [(int) $preda['id']])['sector_id'];
        Esito::verifica('prima o poi scappa', $fuggito !== null);
        Esito::verifica('in un settore adiacente', $fuggito !== null && $dove === (int) $fuggito['fled_to']
            && in_array($dove, \App\Game\Universe::warpsFrom($s1), true));

        Esito::scenario('con un proiettore d\'interdizione al massimo non scappa mai');
        [$h, $hs] = Finti::comandante(0, ['fighters' => 1, 'shields' => 1_000_000_000], $s1);
        $ferma = $npc('trader', $s1, 100);
        $nave = PlayerService::ship((int) $hs['id']);
        $nave['mod_effects']['interdict_pct'] = 100;
        $mai = true;
        for ($i = 0; $i < 20; $i++) {
            $mai = $mai && empty(Combat::attackNpc($rileggi((int) $h['id']), $nave, (int) $ferma['id'], 1)['fled']);
        }
        Esito::verifica('venti assalti, nessuna fuga', $mai);

        Esito::sezione('Taglia — la paga il ricercato');

        [$e] = Finti::comandante(20000, [], $s1);
        $notorieta((int) $e['id'], 200);
        \App\Game\Bank::account((int) $e['id']);
        Database::run('UPDATE bank_accounts SET balance = 50000, last_interest_at = NOW() WHERE player_id = ?', [(int) $e['id']]);
        $attesa = Legge::taglia($rileggi((int) $e['id']));
        [$f] = Finti::comandante(0, [], $s1);
        Esito::uguale('300 cr per punto', 60000, $attesa);
        Esito::uguale('chi lo abbatte la incassa', $attesa, Legge::riscuoti((int) $e['id'], (int) $f['id']));
        Esito::uguale('presa prima dai crediti a bordo', 0, (int) $rileggi((int) $e['id'])['credits']);
        // Prima la versava la Federazione: crediti creati dal nulla.
        Esito::uguale('e il resto dalla banca', 10000,
            (int) Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [(int) $e['id']])['balance']);
        // Prima della correzione restava a 100 punti, ancora ricercato: un
        // complice poteva abbatterlo di nuovo e incassare ancora.
        Esito::verifica('e il ricercato torna sotto la soglia', !Legge::ricercato($rileggi((int) $e['id'])));
        Esito::uguale('quindi un secondo abbattimento non paga', 0, Legge::riscuoti((int) $e['id'], (int) $f['id']));

        Esito::scenario('il secondo account con la nave di soccorso');
        // Il giro segnalato dall'audit del 09/10: il secondo account ritira la
        // nave di soccorso gratuita, attacca quattro volte il principale con
        // un caccia e diventa ricercato; il principale lo abbatte e incassava
        // 41.250 cr, esperienza e un tiro di bottino. Poi si ricominciava.
        [$alt, $altS] = Finti::comandante(0, ['fighters' => 250], $s1);
        Database::run("UPDATE ships SET type_key = 'scout_marauder', soccorso = 1 WHERE id = ?", [(int) $altS['id']]);
        [$main, $mainS] = Finti::comandante(0, ['fighters' => 5000, 'shields' => 1000], $s1);
        for ($i = 0; $i < 4; $i++) {
            Combat::attackShip($rileggi((int) $alt['id']), PlayerService::ship((int) $altS['id']), (int) $main['id'], 1);
        }
        Esito::verifica('quattro attacchi da un caccia: ricercato', Legge::ricercato($rileggi((int) $alt['id'])),
            (string) round(Legge::puntiDi((int) $alt['id']), 1));
        $oggetti = (int) Database::first('SELECT COUNT(*) n FROM player_items WHERE player_id = ?', [(int) $main['id']])['n'];
        $out = Combat::attackShip($rileggi((int) $main['id']), PlayerService::ship((int) $mainS['id']), (int) $alt['id']);
        $m = $rileggi((int) $main['id']);
        Esito::verifica('il principale lo abbatte', !empty($out['ok']) && PlayerService::ship((int) $altS['id'])['type_key'] === 'escape_pod');
        Esito::uguale('ma da un ricercato al verde non incassa nulla', 0, (int) $m['credits']);
        Esito::uguale('una nave di soccorso non da\' esperienza', 0, (int) $m['experience']);
        Esito::uguale('ne\' conta come uccisione', 0, (int) $m['kills']);
        Esito::uguale('ne\' lascia bottino', $oggetti,
            (int) Database::first('SELECT COUNT(*) n FROM player_items WHERE player_id = ?', [(int) $main['id']])['n']);
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
