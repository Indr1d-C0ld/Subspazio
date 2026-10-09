<?php

declare(strict_types=1);

/**
 * Quarto audit (09/10/2026): una prova per ogni correzione, ciascuna vista
 * fallire sul codice di prima.
 *
 * Le gare si provano senza affidarsi al caso: la prova apre una transazione,
 * blocca la riga e la cambia come farebbe la prima richiesta, poi lancia la
 * seconda in un processo separato (tests/_corsa.php) e conferma solo quando
 * quella e' ferma ad aspettare. Sul codice di prima la seconda leggeva il
 * valore vecchio e pagava (o scriveva) lo stesso.
 *
 * Tiene il lock del clock: il tick vero non deve muovere o far combattere gli
 * NPC di prova.
 */

use App\Core\Database;
use App\Game\Cloak;
use App\Game\Combat;
use App\Game\Crew;
use App\Game\Fasce;
use App\Game\GameConfig;
use App\Game\Legge;
use App\Game\Loot;
use App\Game\Modules;
use App\Game\Npc;
use App\Game\Planets;
use App\Game\PlayerService;
use App\Game\Stats;

return static function (): void {
    $npcIds = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $fed = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 1 AND is_stardock = 0 ORDER BY id LIMIT 1')['id'];
    $settoreDi = static fn (int $b): int => (int) Database::first(
        'SELECT id FROM sectors WHERE is_fedspace = 0 AND band = ? ORDER BY id LIMIT 1', [$b])['id'];
    $s1 = $settoreDi(1);
    $s3 = $settoreDi(3);
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $nave = static fn (int $sid): array => Database::first('SELECT * FROM ships WHERE id = ?', [$sid]);
    $npc = static function (string $kind, int $sector, int $fighters, int $band = 1, int $credits = 5000) use (&$npcIds): array {
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, band, fighters, shields, combat_rating, credits, aggression, last_move_at)
             VALUES (?, '__test_npc', 'missile_frigate', ?, ?, ?, 0, 1.0, ?, ?, NOW())",
            [$kind, $sector, $band, $fighters, $credits, $kind === 'trader' ? 0 : 100]
        );
        $npcIds[] = Database::lastInsertId();
        return Database::first('SELECT * FROM npcs WHERE id = ?', [end($npcIds)]);
    };
    // La seconda richiesta, in un processo suo, mentre la prima tiene la riga.
    $seconda = static function (string $azione, array $argomenti, callable $prima): int {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $prima();
            $ph = proc_open(
                array_merge(['php', __DIR__ . '/_corsa.php', $azione, '0'], array_map('strval', $argomenti)),
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipe
            );
            usleep(1_500_000);   // il tempo di arrivare al punto in cui aspetta
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return is_resource($ph) ? proc_close($ph) : -1;
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
        Esito::sezione('La seconda richiesta trova la riga cambiata');

        Esito::scenario('due attacchi insieme allo stesso NPC');
        [$a, $as] = Finti::comandante(0, ['fighters' => 1000], $s1);
        $preda = $npc('pirate', $s1, 10, 1, 5000);
        $esito = $seconda('attacca_npc', [(int) $a['id'], (int) $preda['id']], static function () use ($preda): void {
            // il primo attaccante l'ha appena abbattuto
            Database::first('SELECT id FROM npcs WHERE id = ? FOR UPDATE', [(int) $preda['id']]);
            Database::run('DELETE FROM npcs WHERE id = ?', [(int) $preda['id']]);
        });
        // Prima, in quest'ordine, il secondo cadeva con un errore interno
        // (MariaDB: «Record has changed since last read», uscita 255). Se
        // invece leggeva l'NPC prima del commit del primo e apriva la sua
        // transazione dopo, la riga non c'era piu' nemmeno per lui e incassava
        // 5.000 cr, bottino e modulo garantito di un'elite gia' abbattuta.
        Esito::uguale('il secondo e\' respinto con un messaggio, non con un errore', 1, $esito);
        Esito::uguale('e non incassa nulla', 0, (int) $rileggi((int) $a['id'])['credits']);

        Esito::scenario('doppio «Potenzia» sullo stesso modulo');
        [$u] = Finti::comandante(10_000_000, [], $sd);
        Database::run('UPDATE players SET salvage = 100000 WHERE id = ?', [(int) $u['id']]);
        Database::run("INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, 'w_rastrelliera', '{\"max_fighters_pct\":10}', 'shop')", [(int) $u['id']]);
        $item = Database::lastInsertId();
        $esito = $seconda('potenzia', [(int) $u['id'], $item], static function () use ($item): void {
            Database::first('SELECT id FROM player_items WHERE id = ? FOR UPDATE', [$item]);
            Database::run("UPDATE player_items SET item_key = 'w_hangar' WHERE id = ?", [$item]);
        });
        Esito::verifica('il secondo e\' respinto', $esito !== 0, "uscita {$esito}");
        // Prima: pagava 800 cr e le Leghe per un modulo salito una volta sola.
        Esito::uguale('e non paga', 10_000_000, (int) $rileggi((int) $u['id'])['credits']);

        Esito::scenario('doppio acquisto di una nave');
        [$c, $cs] = Finti::comandante(500_000, [], $sd);
        $esito = $seconda('compra_nave', [(int) $c['id'], 0], static function () use ($cs): void {
            Database::first('SELECT id FROM ships WHERE id = ? FOR UPDATE', [(int) $cs['id']]);
            Database::run("UPDATE ships SET type_key = 'cargo_transport' WHERE id = ?", [(int) $cs['id']]);
        });
        Esito::verifica('il secondo e\' respinto', $esito !== 0, "uscita {$esito}");
        // Prima: pagava di nuovo, con la permuta di uno scafo che non aveva piu'.
        Esito::uguale('e non paga', 500_000, (int) $rileggi((int) $c['id'])['credits']);

        Esito::scenario('un consumabile usato da due richieste insieme');
        [$w] = Finti::comandante(0, [], $s1);
        Crew::addPending((int) $w['id'], 'free_warp', 1, date('Y-m-d H:i:s', time() + 3600));
        $esito = $seconda('effetto_pendente', [(int) $w['id']], static function () use ($w): void {
            Database::first("SELECT id FROM crew_pending WHERE player_id = ? AND effect = 'free_warp' FOR UPDATE", [(int) $w['id']]);
            Database::run("DELETE FROM crew_pending WHERE player_id = ? AND effect = 'free_warp'", [(int) $w['id']]);
        });
        // Prima: un Acceleratore, N salti gratis.
        Esito::verifica('vale una volta sola', $esito !== 0, "uscita {$esito}");

        Esito::scenario('l\'arresto mentre il ricercato paga l\'ammenda');
        [$r] = Finti::comandante(0, [], $s1);
        Database::run('UPDATE players SET notorieta = 150, notorieta_at = NOW() WHERE id = ?', [(int) $r['id']]);
        $seconda('arresto', [(int) $r['id']], static function () use ($r): void {
            Database::first('SELECT id FROM players WHERE id = ? FOR UPDATE', [(int) $r['id']]);
            Database::run('UPDATE players SET notorieta = 0, notorieta_at = NOW() WHERE id = ?', [(int) $r['id']]);
        });
        // Prima: riscriveva 75 punti letti prima dell'ammenda, e chi aveva
        // pagato si ritrovava a un passo da Ricercato.
        Esito::uguale('l\'ammenda pagata resta pagata', 0.0, Legge::puntiDi((int) $r['id']));

        Esito::sezione('Combattimento');

        Esito::scenario('il clock ingaggia con un NPC letto un minuto fa');
        [$k, $ks] = Finti::comandante(0, ['fighters' => 2000], $s3);
        $vero = $npc('pirate', $s3, 50, 3);
        $vecchio = ['fighters' => 1_000_000] + $vero;
        Combat::npcEngagePlayer($vecchio, $k, PlayerService::ship((int) $ks['id']), true);
        // Prima: si combatteva col milione di caccia della lettura vecchia.
        Esito::verifica('si combatte con l\'NPC com\'e\' adesso', $nave((int) $ks['id'])['type_key'] !== 'escape_pod');
        Esito::verifica('e i suoi 50 caccia non reggono', Database::first('SELECT id FROM npcs WHERE id = ?', [(int) $vero['id']]) === null);
        $fantasma = $npc('pirate', $s3, 100_000, 3);
        Database::run('DELETE FROM npcs WHERE id = ?', [(int) $fantasma['id']]);
        $prima = (int) $nave((int) $ks['id'])['fighters'];
        Combat::npcEngagePlayer($fantasma, $rileggi((int) $k['id']), PlayerService::ship((int) $ks['id']), true);
        Esito::uguale('un NPC gia\' abbattuto non combatte da fantasma', $prima, (int) $nave((int) $ks['id'])['fighters']);

        Esito::scenario('chi perde contro la scorta di un mercantile vicino a Sol');
        [$m, $ms] = Finti::comandante(0, ['fighters' => 10], $s1);
        Database::run('UPDATE ships SET shields = 0 WHERE id = ?', [(int) $ms['id']]);
        $merc = $npc('trader', $s1, 100_000, 1);
        $navem = PlayerService::ship((int) $ms['id']);
        $navem['mod_effects']['interdict_pct'] = 100;   // niente fuga
        $out = Combat::attackNpc($rileggi((int) $m['id']), $navem, (int) $merc['id']);
        // Prima: lo spogliava come un predone e gli regalava la tregua dai predoni.
        Esito::verifica('viene respinto, non razziato', !empty($out['ok']) && $out['raided'] === null && empty($out['destroyed_self']));
        Esito::verifica('e nessuna tregua dai predoni', $rileggi((int) $m['id'])['tregua_npc_until'] === null);

        Esito::scenario('una squadra d\'intercettazione e chi non e\' piu\' ricercato');
        [$q] = Finti::comandante(0, [], $s1);
        $squadra = ['kind' => 'patrol', 'name' => 'Squadra', 'target_player_id' => (int) $q['id'], 'scade_at' => null, 'elite' => 0];
        // Prima: lo abbatteva e lo arrestava anche dopo l'ammenda.
        Esito::verifica('lo lascia stare', Combat::npcLasciaStare($squadra, $q));
        Esito::verifica('la pattuglia accorsa a un soccorso invece no',
            !Combat::npcLasciaStare(['scade_at' => date('Y-m-d H:i:s', time() + 600)] + $squadra, $q));

        Esito::scenario('una taglia doppia si conta una volta');
        [$v, $vs] = Finti::comandante(50_000, ['fighters' => 5], $s1);
        Database::run('UPDATE players SET bounty = 100, notorieta = 200, notorieta_at = NOW() WHERE id = ?', [(int) $v['id']]);
        [$h, $hs] = Finti::comandante(0, ['fighters' => 5000], $s1);
        Combat::attackShip($rileggi((int) $h['id']), PlayerService::ship((int) $hs['id']), (int) $v['id']);
        Esito::uguale('«taglie riscosse» sale di uno', 1, (int) (Stats::di((int) $h['id'])['taglie_riscosse'] ?? 0));

        Esito::sezione('Moduli');

        Esito::scenario('smontare un hangar con l\'hangar pieno');
        [$g, $gs] = Finti::comandante(0, ['fighters' => 10_500], $sd);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'weapon', 'w_rastrelliera', '{\"max_fighters_pct\":10}')", [(int) $gs['id']]);
        $mod = Database::lastInsertId();
        $out = Modules::remove($rileggi((int) $g['id']), PlayerService::ship((int) $gs['id']), $mod);
        // Prima: si smontava, e 10.500 caccia restavano su una nave da 10.000.
        Esito::verifica('rifiutato: 500 caccia oltre il tetto', empty($out['ok']) && str_contains((string) ($out['error'] ?? ''), '500'),
            (string) ($out['error'] ?? 'smontato'));
        Esito::uguale('e il modulo resta montato', 1, (int) Database::first('SELECT COUNT(*) n FROM ship_modules WHERE id = ?', [$mod])['n']);

        Esito::scenario('un affisso portato dal civile al Precursore');
        [$p] = Finti::comandante(50_000_000, [], $sd);
        Database::run('UPDATE players SET salvage = 1000000 WHERE id = ?', [(int) $p['id']]);
        $rolled = json_encode(Loot::conAffissi(['max_fighters_pct' => 10], [['a' => 'corazza', 'k' => 'armor_pct', 'v' => 3]]));
        Database::run("INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, 'w_rastrelliera', ?, 'shop')", [(int) $p['id'], $rolled]);
        $it = Database::lastInsertId();
        for ($i = 0; $i < 4; $i++) {
            Modules::upgrade($rileggi((int) $p['id']), $it);
        }
        $af = (array) (json_decode((string) Database::first('SELECT rolled FROM player_items WHERE id = ?', [$it])['rolled'], true)['_affissi'] ?? []);
        // 3 x 4,6 = 13,8. Arrotondando a ogni gradino diventava 15.
        Esito::uguale('vale quanto il conto esatto (3 x 4,6 = 14)', 14, (int) ($af[0]['v'] ?? 0));

        Esito::sezione('Occultamento');

        [$o, $os] = Finti::comandante(0, [], $s1);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1, cloak_carica = 7, cloak_carica_at = DATE_SUB(NOW(), INTERVAL 9 MINUTE) WHERE id = ?', [(int) $os['id']]);
        Cloak::consuma((int) $os['id']);
        $ricarica = (int) Database::first('SELECT TIMESTAMPDIFF(MINUTE, cloak_carica_at, NOW()) m FROM ships WHERE id = ?', [(int) $os['id']])['m'];
        // Prima: l'orologio ripartiva da zero, e chi saltava ogni nove minuti non ricaricava mai.
        Esito::verifica('un salto non butta i nove minuti di ricarica gia\' fatti', $ricarica >= 8, "{$ricarica} min");

        Database::run('UPDATE ships SET dev_cloak = 0, cloaked = 1 WHERE id = ?', [(int) $os['id']]);
        $out = Cloak::toggle($rileggi((int) $o['id']), PlayerService::ship((int) $os['id']), false);
        // Prima: «nessun dispositivo», e la nave restava occultata.
        Esito::verifica('senza dispositivo si puo\' comunque spegnere', !empty($out['ok']) && (int) $nave((int) $os['id'])['cloaked'] === 0);

        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 0, cloak_carica = 8, sector_id = ? WHERE id = ?', [$fed, (int) $os['id']]);
        $out = Cloak::toggle($rileggi((int) $o['id']), PlayerService::ship((int) $os['id']), true);   // il comandante letto fuori
        Esito::verifica('non si accende con la nave gia\' in Fedspace', empty($out['ok']) && (int) $nave((int) $os['id'])['cloaked'] === 0);

        Database::run('UPDATE ships SET cloaked = 1, sector_id = ? WHERE id = ?', [$s1, (int) $os['id']]);
        Database::run('UPDATE players SET credits = 1000 WHERE id = ?', [(int) $o['id']]);
        Database::run(
            "INSERT INTO planets (sector_id, name, type_key, owner_player_id, created_by, citadel_level, last_prod_at)
             VALUES (?, '__test_pianeta', 'M', ?, ?, 1, NOW())",
            [$s1, (int) $o['id'], (int) $o['id']]
        );
        $pianeta = Database::lastInsertId();
        Planets::treasury($rileggi((int) $o['id']), $pianeta, 500, 'deposit');
        Esito::verifica('versare in tesoreria fa cadere l\'occultamento', (int) $nave((int) $os['id'])['cloaked'] === 0);

        Esito::sezione('NPC e fasce');

        $adiacenti = array_map(static fn (int $b): int => $settoreDi($b), [0 => 1, 1 => 3, 2 => 4, 3 => 5]);
        $verso = Npc::destinazioniAmmesse(['kind' => 'ferrengi', 'aggression' => 100, 'band' => 5], $adiacenti);
        // Prima: un Ferrengi dell'Orlo scendeva in fascia IV.
        Esito::uguale('un Ferrengi dell\'Orlo resta nell\'Orlo', [$settoreDi(5)], $verso);
        $ronda = Npc::destinazioniAmmesse(['kind' => 'patrol', 'aggression' => 0, 'band' => 1], [$fed, $s1]);
        Esito::verifica('una ronda non entra in Fedspace', !in_array($fed, $ronda, true) && in_array($s1, $ronda, true));

        Esito::scenario('un\'ondata d\'incursione e\' di passaggio');
        $capo = $npc('ferrengi', $settoreDi(5), 60_000, 5);
        Npc::gregari((int) $capo['id'], 2);
        foreach (Database::all('SELECT id FROM npcs WHERE flotta_id = ?', [(int) $capo['id']]) as $gg) {
            $npcIds[] = (int) $gg['id'];
        }
        if (method_exists(Npc::class, 'diPassaggio')) {
            Npc::diPassaggio((int) $capo['id']);
        }
        Esito::uguale('capo e gregari se ne andranno', 3, (int) Database::first(
            'SELECT COUNT(*) n FROM npcs WHERE (id = ? OR flotta_id = ?) AND scade_at IS NOT NULL', [(int) $capo['id'], (int) $capo['id']])['n']);

        Esito::scenario('un refuso nelle soglie delle fasce');
        GameConfig::str('fasce.soglie', '');
        $cache = new ReflectionProperty(GameConfig::class, 'cache');
        $c0 = $cache->getValue();
        $cache->setValue(null, ['fasce.soglie' => '0.36,0.53,O.69,0.84'] + (array) $c0);
        $soglie = Fasce::soglie();
        $cache->setValue(null, $c0);
        // Prima: «O.69» valeva 0, la fascia I restava vuota e i predoni smettevano di nascere.
        Esito::uguale('tornano quelle di default', [0.36, 0.53, 0.69, 0.84], $soglie);

        Esito::sezione('Traguardi, contatori, stream, clock');

        Esito::scenario('«tutti e dieci i progetti» si contano insieme');
        [$t] = Finti::comandante(0, [], $s1);
        \App\Game\Reperti::tiraProgetto((int) $t['id'], 5, true);
        \App\Game\Reperti::tiraProgetto((int) $t['id'], 5, true);
        Esito::uguale('due progetti posseduti', 2, (int) (Stats::di((int) $t['id'])['progetti_insieme'] ?? 0));
        Esito::uguale('e il traguardo legge quelli', 'progetti_insieme',
            (string) Database::first("SELECT contatore FROM achievements WHERE ckey = 'progetti_oro'")['contatore']);

        Esito::scenario('uno stallo del database dentro un salto');
        $stallo = new PDOException('Deadlock found');
        $stallo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $rilanciato = false;
        // dentro la transazione del salto: fuori non c'e' nulla da salvare e
        // l'errore si ingoia (quinto audit)
        Database::pdo()->beginTransaction();
        try {
            (new ReflectionMethod(Stats::class, 'seAnnullata'))->invoke(null, $stallo);
        } catch (PDOException) {
            $rilanciato = true;
        } catch (ReflectionException) {
        } finally {
            Database::pdo()->rollBack();
        }
        // Prima: il contatore ingoiava l'errore e il salto proseguiva su una
        // transazione gia' annullata, con i turni mai scalati.
        Esito::verifica('il contatore lo lascia passare', $rilanciato);

        Esito::scenario('quattro pagine aperte, tre stream');
        $aperti = [];
        if (method_exists(\App\Game\Live::class, 'apriStream')) {
            for ($i = 0; $i < 4; $i++) {
                $aperti[] = \App\Game\Live::apriStream((int) $t['id']);
            }
        }
        Esito::verifica('la pagina piu\' vecchia chiude il suo', $aperti !== [] && \App\Game\Live::streamSuperato((int) $t['id'], $aperti[0]));
        Esito::verifica('le tre piu\' nuove restano', $aperti !== [] && !\App\Game\Live::streamSuperato((int) $t['id'], $aperti[1]));

        Esito::scenario('codice pubblicato prima delle migrazioni');
        $dir = sys_get_temp_dir() . '/__test_migrazioni_' . getmypid();
        @mkdir($dir);
        file_put_contents($dir . '/9999_finta.sql', 'SELECT 1');
        $attese = method_exists(\App\Cli\Migrator::class, 'pending') ? (new \App\Cli\Migrator($dir))->pending() : [];
        @unlink($dir . '/9999_finta.sql');
        @rmdir($dir);
        // Prima il clock partiva lo stesso, e i lavori fallivano (30/09, 07/10).
        Esito::uguale('il clock sa che ne manca una', ['9999_finta'], $attese);
    } finally {
        Database::run("DELETE FROM planets WHERE name = '__test_pianeta'");
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
