<?php

declare(strict_types=1);

/**
 * Non-regressione: un comandante con fondi capienti deve poter comprare,
 * vendere e commerciare come prima. Serve a evitare che un irrigidimento dei
 * controlli di capienza finisca per respingere anche le operazioni legittime.
 */

use App\Core\Database;
use App\Game\BlackMarket;
use App\Game\Economy;
use App\Game\Shipyard;

return static function (): void {
    Esito::sezione('Cantiere');

    [$p, $s] = Finti::comandante(5_000_000);
    $pid = (int) $p['id'];
    $sid = (int) $s['id'];

    Esito::scenario('acquisto di 5 sonde');
    $prima = Finti::crediti($pid);
    $r = Shipyard::buyHardware($p, $s, 'probe', 5);
    Esito::verifica('acquisto riuscito', !empty($r['ok']), $r['error'] ?? '');
    Esito::uguale('sonde consegnate', 5, Finti::stiva($sid, 'probes'));
    Esito::uguale('addebito corretto', $prima - (int) ($r['cost'] ?? 0), Finti::crediti($pid));

    Esito::scenario('potenziamento: +10 caccia');
    [$p, $s] = Finti::ricarica($pid);
    $prima = Finti::crediti($pid);
    $r = Shipyard::upgrade($p, $s, 'fighters', 10);
    Esito::verifica('potenziamento riuscito', !empty($r['ok']), $r['error'] ?? '');
    Esito::uguale('addebito corretto', $prima - (int) ($r['cost'] ?? 0), Finti::crediti($pid));

    Esito::sezione('Mercato nero');

    Database::run('UPDATE ships SET hold_ore = 40 WHERE id = ?', [$sid]);
    [$p, $s] = Finti::ricarica($pid);

    Esito::scenario('vendita di 20 unità di minerale');
    $prima = Finti::crediti($pid);
    $r = BlackMarket::sell($p, $s, 'ore', 20);
    Esito::verifica('vendita riuscita', !empty($r['ok']), $r['error'] ?? '');
    Esito::uguale('stiva scalata di 20', 20, Finti::stiva($sid, 'hold_ore'));
    Esito::uguale('incasso accreditato', $prima + (int) ($r['total'] ?? 0), Finti::crediti($pid));

    Esito::scenario('acquisto di 3 mine Limpet');
    [$p, $s] = Finti::ricarica($pid);
    $prima = Finti::crediti($pid);
    $r = BlackMarket::buy($p, $s, 'limpet', 3);
    Esito::verifica('acquisto riuscito', !empty($r['ok']), $r['error'] ?? '');
    Esito::uguale('mine consegnate', 3, Finti::stiva($sid, 'mines_limpet'));
    Esito::uguale('addebito corretto', $prima - (int) ($r['cost'] ?? 0), Finti::crediti($pid));

    Esito::sezione('Porto');

    $vende = Database::first(
        "SELECT p.*, s.id sid FROM ports p JOIN sectors s ON s.id = p.sector_id
         WHERE p.destroyed = 0 AND p.ore_mode = 'sell' AND p.ore_stock > 30 LIMIT 1"
    );
    if ($vende === null) {
        Esito::verifica('nessun porto che vende minerale nel campione: prova saltata', true);
    } else {
        // Stessa accortezza del porto in vendita: si fotografa e si ripristina.
        $vId = (int) $vende['id'];
        // credits_max compreso: la rigenerazione lo ricalcola durante lo
        // scambio, e rimettere solo `credits` lasciava il porto sopra il
        // proprio tetto (visto: 125 cr di eccedenza sullo StarDock).
        $prima_v = Database::first('SELECT credits, credits_max, ore_stock FROM ports WHERE id = ?', [$vId]);
        try {
            [$pc, $sc] = Finti::comandante(1_000_000, [], (int) $vende['sid']);
            $pcId = (int) $pc['id'];
            $scId = (int) $sc['id'];

            Esito::scenario('acquisto di 10 unità al porto');
            $prima = Finti::crediti($pcId);
            $r = Economy::settle($pc, $sc, (int) $vende['sid'], 'ore', 'buy', 10, null);
            Esito::verifica('acquisto riuscito', !empty($r['ok']), $r['error'] ?? '');
            Esito::uguale('merce in stiva', 10, Finti::stiva($scId, 'hold_ore'));
            Esito::verifica('crediti scalati', Finti::crediti($pcId) < $prima);
        } finally {
            Database::run('UPDATE ports SET credits = ?, credits_max = ?, ore_stock = ? WHERE id = ?',
                [$prima_v['credits'], $prima_v['credits_max'], $prima_v['ore_stock'], $vId]);
        }
    }

    $compra = Database::first(
        "SELECT p.*, s.id sid FROM ports p JOIN sectors s ON s.id = p.sector_id
         WHERE p.destroyed = 0 AND p.ore_mode = 'buy' AND p.credits > 5000 LIMIT 1"
    );
    if ($compra === null) {
        Esito::verifica('nessun porto che compra minerale nel campione: prova saltata', true);
    } else {
        // Il porto e' stato di gioco VERO, non sintetico: la vendita gli
        // sposta crediti e magazzino. Se ne prende una fotografia e la si
        // rimette a posto alla fine, altrimenti ripetere i test prosciuga un
        // porto reale — ed e' successo: dopo una quindicina di giri
        // ravvicinati non aveva piu' credito per comprare, e la prova
        // falliva per esaurimento invece che per un difetto.
        $portoId = (int) $compra['id'];
        $prima_porto = Database::first(
            'SELECT credits, credits_max, ore_stock, org_stock, equ_stock FROM ports WHERE id = ?',
            [$portoId]
        );

        try {
            [$pv, $sv] = Finti::comandante(1000, ['hold_ore' => 30], (int) $compra['sid']);
            $pvId = (int) $pv['id'];
            $svId = (int) $sv['id'];

            // Quanto il porto puo' davvero assorbire adesso: la sua capienza
            // dipende dal credito che ha in cassa, e cambia nel tempo.
            $quanto = min(30, Economy::maxQty($compra, $pv, $sv, 'ore', 'sell'));
            Esito::verifica('il porto può assorbire almeno qualche unità', $quanto > 0, "max {$quanto}");

            if ($quanto > 0) {
                Esito::scenario("vendita di {$quanto} unità al porto");
                $prima = Finti::crediti($pvId);
                $r = Economy::settle($pv, $sv, (int) $compra['sid'], 'ore', 'sell', $quanto, null);
                Esito::verifica('vendita riuscita', !empty($r['ok']), $r['error'] ?? '');
                Esito::uguale('stiva scalata di quanto venduto', 30 - $quanto, Finti::stiva($svId, 'hold_ore'));
                Esito::verifica('incasso accreditato', Finti::crediti($pvId) > $prima);
            }

            Esito::scenario('vendita di carico inesistente');
            [$pv2, $sv2] = Finti::ricarica($pvId);
            $r = Economy::settle($pv2, $sv2, (int) $compra['sid'], 'ore', 'sell', 999, null);
            Esito::verifica('respinta', empty($r['ok']), $r['error'] ?? 'PASSATA');
        } finally {
            Database::run(
                'UPDATE ports SET credits = ?, credits_max = ?, ore_stock = ?, org_stock = ?, equ_stock = ? WHERE id = ?',
                [$prima_porto['credits'], $prima_porto['credits_max'], $prima_porto['ore_stock'],
                 $prima_porto['org_stock'], $prima_porto['equ_stock'], $portoId]
            );
        }
        $dopo_porto = Database::first('SELECT credits, ore_stock FROM ports WHERE id = ?', [$portoId]);
        Esito::uguale('il porto è stato rimesso come prima (crediti)', $prima_porto['credits'], $dopo_porto['credits']);
        Esito::uguale('il porto è stato rimesso come prima (magazzino)', $prima_porto['ore_stock'], $dopo_porto['ore_stock']);
    }

    Esito::sezione('Il tick continua a girare');

    Esito::scenario('i task toccati dalla correzione');
    $ok = true;
    foreach ([
        'Contracts::expireDue'      => static fn () => App\Game\Contracts::expireDue(),
        'Planets::tickDue'          => static fn () => App\Game\Planets::tickDue(),
        'Industry::craftJobsTick'   => static fn () => App\Game\Industry::craftJobsTick(),
    ] as $nome => $fn) {
        try {
            $fn();
            Esito::verifica("{$nome} senza errori", true);
        } catch (\Throwable $e) {
            Esito::verifica("{$nome} senza errori", false, $e->getMessage());
            $ok = false;
        }
    }
    unset($ok);
};
