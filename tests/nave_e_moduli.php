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
    } finally {
        Database::run("DELETE bk FROM bank_accounts bk JOIN players p ON p.id = bk.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
    }
};
