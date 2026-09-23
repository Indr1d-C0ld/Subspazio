<?php

declare(strict_types=1);

/**
 * Porti: la ricrescita delle scorte, e il «Max» di vendita.
 *
 * Si lavora su un porto vero, fotografato prima e rimesso com'era dopo — come
 * fanno le prove di integrita' economica. Audit del 23/09/2026.
 */

use App\Core\Database;
use App\Game\Economy;
use App\Game\PlayerService;

return static function (): void {
    // Un porto piccolo che VENDE almeno una merce e ne COMPRA almeno un'altra.
    $port = Database::first(
        "SELECT p.* FROM ports p JOIN sectors s ON s.id = p.sector_id
          WHERE p.destroyed = 0 AND s.is_stardock = 0 AND s.is_fedspace = 0
            AND 'sell' IN (p.ore_mode, p.org_mode, p.equ_mode) AND 'buy' IN (p.ore_mode, p.org_mode, p.equ_mode)
          ORDER BY p.ore_capacity + p.org_capacity + p.equ_capacity ASC LIMIT 1"
    );
    $foto = $port;
    $vende = null; $compra = null;
    foreach (Economy::COMMODITIES as $c) {
        $pf = Economy::prefix($c);
        if ($port["{$pf}_mode"] === 'sell') { $vende ??= $c; } else { $compra ??= $c; }
    }
    $pfV = Economy::prefix($vende);
    $pfC = Economy::prefix($compra);

    try {
        Esito::sezione('Porti — la ricrescita non si perde negli arrotondamenti');

        Esito::scenario('visite frequenti a un porto piccolo');
        // Scorta a zero, poi 300 visite a 10 secondi l'una dall'altra. La
        // ricrescita attesa e' 300 x 10 s x capacita'/72h. Prima ogni visita
        // arrotondava all'intero piu' vicino e azzerava l'orologio: sotto il
        // mezzo punto per visita, il porto non ricresceva mai.
        Database::run("UPDATE ports SET {$pfV}_stock = 0 WHERE id = ?", [$port['id']]);
        $cap = (int) $port["{$pfV}_capacity"];
        $attesa = 300 * 10 * $cap / (72 * 3600);
        for ($i = 0; $i < 300; $i++) {
            Database::run('UPDATE ports SET last_update = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE id = ?', [$port['id']]);
            Economy::regenerate(Database::first('SELECT * FROM ports WHERE id = ?', [$port['id']]));
        }
        $cresciuta = (int) Database::first("SELECT {$pfV}_stock s FROM ports WHERE id = ?", [$port['id']])['s'];
        Esito::verifica(
            'il porto ricresce come deve, in media',
            $cresciuta >= $attesa * 0.4 && $cresciuta <= $attesa * 2.0,
            sprintf('attese ~%.1f unita\', ricresciute %d', $attesa, $cresciuta)
        );

        Esito::sezione('Porti — la ricrescita non cancella uno scambio');

        Esito::scenario('uno scambio concluso fra la lettura e la scrittura');
        [$p, $s] = Finti::comandante(0, ["hold_{$compra}" === 'hold_ore' ? 'hold_ore' : ($compra === 'organics' ? 'hold_organics' : ($compra === 'equipment' ? 'hold_equipment' : 'hold_ore')) => 10], (int) $port['sector_id']);
        Database::run("UPDATE ports SET {$pfC}_stock = 0, credits = GREATEST(credits, 100000), last_update = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?", [$port['id']]);
        $vecchia = Database::first('SELECT p.*, s.region_id, s.name AS sector_name, s.is_stardock FROM ports p JOIN sectors s ON s.id = p.sector_id WHERE p.id = ?', [$port['id']]);
        $r = Economy::settle(Database::first('SELECT * FROM players WHERE id = ?', [(int) $p['id']]), PlayerService::ship((int) $s['id']),
            (int) $port['sector_id'], $compra, 'sell', 10, null, 0);
        Esito::verifica('la vendita riesce', !empty($r['ok']), (string) ($r['error'] ?? ''));
        $dopoVendita = (int) Database::first("SELECT {$pfC}_stock s FROM ports WHERE id = ?", [$port['id']])['s'];
        Economy::regenerate($vecchia);     // una visita che aveva letto il porto PRIMA della vendita
        $finale = (int) Database::first("SELECT {$pfC}_stock s FROM ports WHERE id = ?", [$port['id']])['s'];
        // Prima la visita riscriveva le scorte lette prima della vendita: le 10
        // unita' vendute sparivano dal porto e i crediti restavano al giocatore.
        Esito::verifica('la merce venduta resta nel porto', $finale >= $dopoVendita, "dopo la vendita {$dopoVendita}, dopo la visita {$finale}");

        Esito::scenario('una cassa sopra il massimo non viene tagliata');
        Database::run('UPDATE ports SET credits = credits_max + 5000, last_update = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?', [$port['id']]);
        Economy::regenerate(Database::first('SELECT * FROM ports WHERE id = ?', [$port['id']]));
        $c = Database::first('SELECT credits, credits_max FROM ports WHERE id = ?', [$port['id']]);
        // La cassa sale oltre il massimo solo per acquisti dei giocatori: prima la
        // ricrescita successiva la riportava al massimo, e quei crediti sparivano.
        Esito::uguale('i crediti in piu\' restano', (int) $c['credits_max'] + 5000, (int) $c['credits']);

        Esito::sezione('Porti — il «Max» di vendita e\' vendibile');

        Esito::scenario('un porto con poca cassa');
        [$p2, $s2] = Finti::comandante(0, [Economy::shipColumn($compra) => 250], (int) $port['sector_id']);
        Database::run("UPDATE ports SET {$pfC}_stock = 0, credits = 2000, last_update = NOW() WHERE id = ?", [$port['id']]);
        $pt = Economy::portAt((int) $port['sector_id']);
        $max = Economy::maxQty($pt, Database::first('SELECT * FROM players WHERE id = ?', [(int) $p2['id']]), PlayerService::ship((int) $s2['id']), $compra, 'sell');
        $tot = $max > 0 ? Economy::quote($pt, $compra, 'sell', $max)['total'] : 0;
        Esito::verifica('il totale del massimo sta nella cassa del porto', $tot <= (int) $pt['credits'], "max {$max}, totale {$tot}, cassa {$pt['credits']}");
        $r2 = Economy::settle(Database::first('SELECT * FROM players WHERE id = ?', [(int) $p2['id']]), PlayerService::ship((int) $s2['id']),
            (int) $port['sector_id'], $compra, 'sell', $max, null, 0);
        Esito::verifica('e vendendolo, la vendita riesce', $max > 0 && !empty($r2['ok']), (string) ($r2['error'] ?? ''));
    } finally {
        Database::run(
            'UPDATE ports SET ore_stock = ?, org_stock = ?, equ_stock = ?, credits = ?, credits_max = ?, fighters = ?, last_update = ? WHERE id = ?',
            [$foto['ore_stock'], $foto['org_stock'], $foto['equ_stock'], $foto['credits'], $foto['credits_max'], $foto['fighters'], $foto['last_update'], $foto['id']]
        );
        $r = Database::first('SELECT ore_stock, org_stock, equ_stock, credits FROM ports WHERE id = ?', [$foto['id']]);
        Esito::verifica(
            'il porto e\' tornato com\'era',
            (int) $r['ore_stock'] === (int) $foto['ore_stock'] && (int) $r['credits'] === (int) $foto['credits']
            && (int) $r['org_stock'] === (int) $foto['org_stock'] && (int) $r['equ_stock'] === (int) $foto['equ_stock']
        );
    }
};
