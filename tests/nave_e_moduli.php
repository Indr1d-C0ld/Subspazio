<?php

declare(strict_types=1);

/**
 * Nave, moduli, cantiere, mercato nero: capienze e stati che si potevano
 * aggirare. Audit del 23/09/2026; ogni prova fallisce sul codice di prima.
 */

use App\Core\Database;
use App\Game\BlackMarket;
use App\Game\Loot;
use App\Game\Modules;
use App\Game\PlayerService;
use App\Game\Shipyard;

return static function (): void {
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $corsa = static function (int $n, string $azione, int ...$arg): int {
        $t0 = microtime(true) + 1.2;
        $proc = [];
        for ($i = 0; $i < $n; $i++) {
            $proc[] = proc_open(
                array_merge(['php', dirname(__DIR__) . '/tests/_corsa.php', $azione, (string) $t0], array_map('strval', $arg)),
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe
            );
        }
        $ok = 0;
        foreach ($proc as $ph) {
            if (is_resource($ph) && proc_close($ph) === 0) {
                $ok++;
            }
        }
        return $ok;
    };

    try {
        // --- moduli ---------------------------------------------------------

        Esito::sezione('Moduli — il guasto non si ripara smontando');

        Esito::scenario('un modulo guasto smontato e rimontato');
        [$p, $s] = Finti::comandante(0, [], $sd);
        Database::run("INSERT INTO player_items (player_id, item_key, source) VALUES (?, 'u_stiva', 'shop')", [(int) $p['id']]);
        $item = Database::lastInsertId();
        Modules::install($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), $item);
        $mod = (int) Database::first('SELECT id FROM ship_modules WHERE ship_id = ? ORDER BY id DESC LIMIT 1', [(int) $s['id']])['id'];
        Database::run('UPDATE ship_modules SET broken_at = NOW() WHERE id = ?', [$mod]);
        Modules::remove($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), $mod);
        $inv = Database::first('SELECT id, broken_at FROM player_items WHERE player_id = ? ORDER BY id DESC LIMIT 1', [(int) $p['id']]);
        Esito::verifica('in inventario resta guasto', !empty($inv['broken_at']));
        Modules::install($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), (int) $inv['id']);
        $rimontato = Database::first('SELECT broken_at FROM ship_modules WHERE ship_id = ? ORDER BY id DESC LIMIT 1', [(int) $s['id']]);
        // Prima tornava in linea: tre moduli guasti, 1.350 cr di riparazione
        // risparmiati con due clic ciascuno.
        Esito::verifica('e rimontato e\' ancora guasto', !empty($rimontato['broken_at']));

        Esito::scenario('i moduli promessi da anomalie e incontri arrivano davvero');
        [$p2] = Finti::comandante(0, [], $sd);
        $da = Loot::grant((int) $p2['id'], 'anomaly', false, 'civ');
        $db = Loot::grant((int) $p2['id'], 'encounter', false, 'civ');
        // Prima l'inserimento falliva in silenzio: la provenienza non era
        // prevista dalla colonna, e il giocatore riceveva i crediti ma non il modulo.
        Esito::verifica('da un\'anomalia', $da !== null);
        Esito::verifica('da un incontro', $db !== null);
        Esito::uguale(
            'e sono in inventario',
            2,
            (int) Database::first("SELECT COUNT(*) n FROM player_items WHERE player_id = ? AND source IN ('anomaly','encounter')", [(int) $p2['id']])['n']
        );

        // --- cantiere -------------------------------------------------------

        Esito::sezione('Cantiere — i tetti valgono sulla nave vera');

        Esito::scenario('un modulo stiva non si mangia le stive acquistabili');
        [$p3, $s3] = Finti::comandante(10_000_000, [], $sd);
        Database::run('UPDATE ships SET holds_total = 71 WHERE id = ?', [(int) $s3['id']]);   // 71 + 4 del modulo = 75, il massimo
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key) VALUES (?, 'utility', 'u_stiva')", [(int) $s3['id']]);
        $r = Shipyard::upgrade($rileggi((int) $p3['id']), PlayerService::ship((int) $s3['id']), 'holds', 4);
        // L'effettiva e' 75 (= massimo): prima il cantiere diceva «gia' al massimo»
        // e le 4 stive comprabili restavano introvabili.
        Esito::verifica('le 4 stive mancanti si comprano', !empty($r['ok']), (string) ($r['error'] ?? ''));
        Esito::uguale('e la riga grezza arriva al massimo dello scafo', 75, (int) Database::first('SELECT holds_total FROM ships WHERE id = ?', [(int) $s3['id']])['holds_total']);

        Esito::scenario('il soccorso non e\' per chi ha i soldi in banca');
        [$p4, $s4] = Finti::comandante(0, [], $sd);
        Database::run("UPDATE ships SET type_key = 'escape_pod' WHERE id = ?", [(int) $s4['id']]);
        Database::run('INSERT INTO bank_accounts (player_id, balance) VALUES (?, 5000000)', [(int) $p4['id']]);
        $rs = Shipyard::rescueShip($rileggi((int) $p4['id']), PlayerService::ship((int) $s4['id']));
        Esito::verifica('viene rifiutato', empty($rs['ok']), (string) ($rs['error'] ?? ''));

        Esito::scenario('tre acquisti simultanei dello stesso dispositivo');
        [$p5, $s5] = Finti::comandante(1_000_000, [], $sd);
        $prima = (int) $rileggi((int) $p5['id'])['credits'];
        $riusciti = $corsa(3, 'compra_hw', (int) $p5['id'], 0, 1);
        $prezzo = App\Game\GameConfig::int('hardware.cloak_price', 35000);
        Esito::uguale('uno solo va a buon fine', 1, $riusciti);
        Esito::uguale('e si paga una volta', $prezzo, $prima - (int) $rileggi((int) $p5['id'])['credits']);

        Esito::scenario('tre acquisti simultanei che insieme sforerebbero il tetto');
        [$p6, $s6] = Finti::comandante(10_000_000, [], $sd);
        Database::run('UPDATE ships SET genesis = 9 WHERE id = ?', [(int) $s6['id']]);   // tetto 10
        $corsa(3, 'compra_hw', (int) $p6['id'], 1, 1);
        Esito::uguale('il tetto regge', 10, (int) Database::first('SELECT genesis FROM ships WHERE id = ?', [(int) $s6['id']])['genesis']);

        // --- mercato nero ---------------------------------------------------

        Esito::sezione('Mercato nero — il tetto dell\'hardware vale anche li\'');

        Esito::scenario('una nave gia\' a 50 mine Armid ne compra altre');
        [$p7, $s7] = Finti::comandante(1_000_000, ['mines_armid' => 50], $sd);
        $rb = BlackMarket::buy($rileggi((int) $p7['id']), PlayerService::ship((int) $s7['id']), 'armid', 10);
        // Prima: min(10, 0) = 0, poi max(1, 0) = 1 — e la nave saliva a 51,
        // poi 52, senza fine.
        Esito::verifica('viene rifiutato', empty($rb['ok']), (string) ($rb['error'] ?? ''));
        Esito::uguale('e le mine restano 50', 50, (int) Database::first('SELECT mines_armid FROM ships WHERE id = ?', [(int) $s7['id']])['mines_armid']);

        Esito::sezione('Cambio nave — stive, caccia e scudi comprati non si perdono');

        Esito::scenario('Scout Marauder con 25 stive piene di organici compra una Merchant Cruiser (20 di base)');
        [$p8, $s8] = Finti::comandante(1_000_000, ['hold_organics' => 25, 'fighters' => 2500, 'shields' => 400], $sd);
        Database::run("UPDATE ships SET type_key = 'scout_marauder', holds_total = 25 WHERE id = ?", [(int) $s8['id']]);
        $rn = Shipyard::buyShip($rileggi((int) $p8['id']), PlayerService::ship((int) $s8['id']), 'merchant_cruiser');
        $n8 = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $s8['id']]);
        // Prima: «Svuota le stive: la nuova nave ha 20 stive, ne usi 25».
        Esito::verifica('l\'acquisto riesce', !empty($rn['ok']), (string) ($rn['error'] ?? ''));
        Esito::uguale('con le 25 stive', 25, (int) $n8['holds_total']);
        Esito::uguale('e il carico a bordo', 25, (int) $n8['hold_organics']);
        Esito::uguale('i 2.500 caccia passano (dotazione 750)', 2500, (int) $n8['fighters']);
        Esito::uguale('e i 400 scudi (dotazione 200)', 400, (int) $n8['shields']);

        Esito::scenario('una Merchant Cruiser da 10.000 caccia torna a una Scout (tetto 2.500)');
        Database::run('UPDATE ships SET fighters = 10000, hold_organics = 0 WHERE id = ?', [(int) $s8['id']]);
        $prima = (int) $rileggi((int) $p8['id'])['credits'];
        $rs = Shipyard::buyShip($rileggi((int) $p8['id']), PlayerService::ship((int) $s8['id']), 'scout_marauder');
        $n8 = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $s8['id']]);
        $prezzoCaccia = (int) ceil(\App\Game\GameConfig::float('hardware.fighter_price', 12));
        Esito::uguale('restano i caccia che la Scout puo\' portare', 2500, (int) $n8['fighters']);
        // 7.500 oltre il tetto, tutti comprati (la Cruiser ne da' 750 di base)
        Esito::uguale('gli altri 7.500 sono rimborsati a listino', 7500 * $prezzoCaccia, (int) ($rs['refund'] ?? -1));
        Esito::uguale('e accreditati (lo scafo costa zero: la permuta lo copre)', $prima + 7500 * $prezzoCaccia,
            (int) $rileggi((int) $p8['id'])['credits']);

        Esito::scenario('la dotazione compresa in uno scafo non si rivende cambiando nave');
        $ered = Shipyard::eredita(['type_key' => 'interdictor', 'holds_total' => 10, 'fighters' => 100000, 'shields' => 1000],
            Database::first("SELECT * FROM ship_types WHERE ckey = 'scout_marauder'"));
        Esito::uguale('un Interdictor con i soli caccia di serie non incassa nulla', 0, $ered['rimborso']);

        Esito::sezione('Officina — un modulo potenziato resta nella sua famiglia');

        [$p9] = Finti::comandante(1_000_000, [], $sd);
        Database::run('UPDATE players SET salvage = 100000 WHERE id = ?', [(int) $p9['id']]);
        Database::run("INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, 'u_stiva', '{\"cargo_bonus\":4}', 'shop')", [(int) $p9['id']]);
        $pi = Database::lastInsertId();
        $ru = Modules::upgrade($rileggi((int) $p9['id']), $pi);
        $dopo = Database::first('SELECT it.family, it.rarity FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key WHERE pi.id = ?', [$pi]);
        // Prima: l'unico modulo Militare della categoria, il Braccio recuperatore.
        Esito::verifica('la Stiva ausiliaria diventa un modulo di stiva', !empty($ru['ok']) && $dopo['family'] === 'stive',
            ($ru['name'] ?? $ru['error'] ?? '') . ' / ' . $dopo['family']);

        Esito::sezione('Stive dei moduli — si riempiono davvero');

        [$q9, $qs9] = Finti::comandante(1_000_000, ['hold_ore' => 20], $sd);
        Database::run('UPDATE ships SET holds_total = 20 WHERE id = ?', [(int) $qs9['id']]);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'utility', 'u_stiva', '{\"cargo_bonus\":4}')", [(int) $qs9['id']]);
        $grezza = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $qs9['id']]);
        Esito::uguale('20 stive dello scafo + 4 del modulo', 24, \App\Game\Economy::capacita($grezza));
        Esito::uguale('anche dalla nave effettiva', 24, \App\Game\Economy::capacita(PlayerService::ship((int) $qs9['id'])));
        $porto = Database::first("SELECT p.sector_id FROM ports p JOIN sectors s ON s.id = p.sector_id WHERE p.ore_mode = 'sell' AND p.destroyed = 0 AND p.ore_stock > 10 LIMIT 1");
        $riga = \App\Game\Economy::portAt((int) $porto['sector_id']);
        // Prima: il porto rileggeva la nave grezza (20 stive, tutte piene) e non vendeva nulla.
        Esito::uguale('al porto si comprano le 4 unita\' che ci stanno', 4,
            min(4, \App\Game\Economy::maxQty($riga, $rileggi((int) $q9['id']), $grezza, 'ore', 'buy')));
        Database::run('UPDATE ships SET hold_ore = 24 WHERE id = ?', [(int) $qs9['id']]);
        $mid = (int) Database::first('SELECT id FROM ship_modules WHERE ship_id = ?', [(int) $qs9['id']])['id'];
        $rr = Modules::remove($rileggi((int) $q9['id']), PlayerService::ship((int) $qs9['id']), $mid);
        Esito::verifica('il modulo non si toglie con le sue stive piene', empty($rr['ok']), (string) ($rr['error'] ?? ''));

        Esito::sezione('Catalogo — famiglie complete, affissi, effetti nuovi');

        $fam = Database::all('SELECT family, COUNT(*) n, COUNT(DISTINCT rarity) r FROM item_types GROUP BY family');
        // Prima: 23 modelli, uno per rarita' e categoria.
        Esito::verifica('almeno 80 modelli in almeno 15 famiglie',
            (int) Database::first('SELECT COUNT(*) n FROM item_types')['n'] >= 80 && count($fam) >= 15);
        Esito::verifica('in ogni famiglia una rarita\' per modello', array_filter($fam, static fn ($f) => (int) $f['n'] !== (int) $f['r']) === []);

        $xeno = Loot::tiraAffissi('xeno');
        Esito::uguale('un modulo Xeno trovato ha due affissi', 2, count($xeno));
        $rr = Loot::conAffissi(['combat_pct' => 12], [['a' => 'assalto', 'k' => 'combat_pct', 'v' => 3], ['a' => 'fortuna', 'k' => 'drop_luck_pct', 'v' => 3]]);
        Esito::uguale('gli affissi si sommano agli effetti', [15, 3], [$rr['combat_pct'], $rr['drop_luck_pct']]);
        Esito::uguale('e danno un nome', 'Railgun a massa dell\'Assalto e della Fortuna', Loot::nomeConAffissi('Railgun a massa', $rr));

        Esito::scenario('un Railgun dell\'Assalto potenziato a Sperimentale');
        [$pa] = Finti::comandante(1_000_000, [], $sd);
        Database::run('UPDATE players SET salvage = 100000 WHERE id = ?', [(int) $pa['id']]);
        Database::run("INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, 'w_railgun', ?, 'npc')",
            [(int) $pa['id'], json_encode(Loot::conAffissi(['combat_pct' => 12], [['a' => 'assalto', 'k' => 'combat_pct', 'v' => 3]]))]);
        $railgun = Database::lastInsertId();
        $ru = Modules::upgrade($rileggi((int) $pa['id']), $railgun);
        $rup = json_decode((string) Database::first('SELECT rolled FROM player_items WHERE player_id = ?', [(int) $pa['id']])['rolled'], true);
        // 20 della Lancia al plasma + 3 x 2,4/1,6 = 4,5 -> 5
        Esito::verifica('resta Lancia al plasma dell\'Assalto, con l\'affisso riscalato',
            !empty($ru['ok']) && $ru['name'] === 'Lancia al plasma dell\'Assalto' && (int) $rup['combat_pct'] === 25,
            ($ru['name'] ?? $ru['error'] ?? '') . ' ' . json_encode($rup));

        Esito::scenario('corazza, disturbo, schermatura, elusione');
        Esito::uguale('corazza 22 + disturbo 24 contro un NPC: -46%', 0.54,
            round(\App\Game\Combat::colpiSubiti(['mod_effects' => ['armor_pct' => 22, 'ecm_pct' => 24]], true), 4));
        Esito::uguale('con affissi in piu\' non si scende sotto il tetto del 60%', 0.4,
            round(\App\Game\Combat::colpiSubiti(['mod_effects' => ['armor_pct' => 40, 'ecm_pct' => 35]], true), 4));
        Esito::uguale('contro un comandante conta solo la corazza', 0.78,
            round(\App\Game\Combat::colpiSubiti(['mod_effects' => ['armor_pct' => 22, 'ecm_pct' => 24]], false), 4));
        Esito::uguale('il Velo Precursore assorbe il 90% dei danni ambientali', 0.9,
            \App\Game\Combat::resistenza(['mod_effects' => ['hazard_resist_pct' => 90]]));
        Esito::verifica('senza manovre evasive non si elude mai', !\App\Game\Combat::elude(['mod_effects' => []]));

        Esito::scenario('un hangar alza il tetto dei caccia, e il Cantiere lo riempie');
        [$ph, $sh] = Finti::comandante(1_000_000, ['fighters' => 10000], $sd);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'weapon', 'w_matrice', '{\"max_fighters_pct\":60}')", [(int) $sh['id']]);
        $eff = PlayerService::ship((int) $sh['id']);
        Esito::uguale('Merchant Cruiser: 10.000 + 60% = 16.000', 16000, (int) $eff['max_fighters']);
        // Prima: «Gia' al massimo per questo scafo».
        $up = Shipyard::upgrade($rileggi((int) $ph['id']), $eff, 'fighters', 500);
        Esito::verifica('se ne comprano oltre i 10.000 dello scafo', !empty($up['ok']), (string) ($up['error'] ?? ''));

        Esito::scenario('la Fabbrica Precursore produce caccia ogni minuto');
        [, $sf] = Finti::comandante(0, ['fighters' => 0], $sd);
        Database::run("INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, 'utility', 'u_fabbrica', '{\"fighter_regen\":700}')", [(int) $sf['id']]);
        Modules::tickFabbriche();
        $f = (int) Database::first('SELECT fighters FROM ships WHERE id = ?', [(int) $sf['id']])['fighters'];
        Esito::verifica('700 all\'ora sono 11 o 12 al minuto', $f === 11 || $f === 12, (string) $f);
    } finally {
        Database::run("DELETE bk FROM bank_accounts bk JOIN players p ON p.id = bk.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
    }
};
