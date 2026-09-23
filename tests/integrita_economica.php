<?php

declare(strict_types=1);

/**
 * Integrità economica — reperti 01 e 02 dell'audit del 2026-09-15.
 *
 * Il difetto: le funzioni di gioco ricevono $player e $ship come fotografie
 * scattate a inizio richiesta, controllavano la capienza su quelle e poi
 * scrivevano senza vincolo. Due richieste concorrenti superavano entrambe lo
 * stesso controllo: saldi negativi, merce non pagata, carico venduto piu'
 * volte. Prima della correzione questo file falliva con saldo -310.
 *
 * Le prove qui sotto passano alle funzioni la STESSA fotografia piu' volte:
 * e' esattamente cio' che vedrebbero due richieste in parallelo, e non
 * richiede di orchestrare processi concorrenti per riprodurre la corsa.
 */

use App\Core\Database;
use App\Game\BlackMarket;
use App\Game\GameConfig;
use App\Game\Wallet;

return static function (): void {
    // --- Reperto 01: spendere denaro che non si possiede -------------------

    Esito::sezione('Reperto 01 — spendere denaro che non si possiede');

    [$p, $s] = Finti::comandante(100);
    $prezzo = (int) round(
        GameConfig::int('hardware.limpet_price', 55) * GameConfig::float('blackmarket.hw_discount', 0.75)
    );
    $lotto = $prezzo * 2;
    Esito::scenario("100 cr in cassa, cinque acquisti concorrenti da {$lotto} cr l'uno");

    $consentiti = 0;
    for ($i = 0; $i < 5; $i++) {
        if (!empty(BlackMarket::buy($p, $s, 'limpet', 2)['ok'])) {
            $consentiti++;
        }
    }
    $saldo = Finti::crediti((int) $p['id']);
    $mine  = Finti::stiva((int) $s['id'], 'mines_limpet');

    Esito::verifica('il saldo non scende sotto zero', $saldo >= 0, "saldo {$saldo} cr");
    Esito::verifica('passa solo quel che la cassa può coprire', $consentiti === intdiv(100, $lotto), "{$consentiti} acquisti");
    Esito::uguale('la merce consegnata corrisponde a quanto pagato', $consentiti * 2, $mine);
    Esito::uguale('quanto speso corrisponde a quanto addebitato', 100 - $consentiti * $lotto, $saldo);

    // --- Reperto 02: scambi a metà ----------------------------------------

    Esito::sezione('Reperto 02 — scambi a metà');

    // Il mercato nero ritira solo la merce che il porto locale NON vende:
    // si vende dove il porto compra minerale.
    [$p2, $s2] = Finti::comandante(0, ['hold_ore' => 50], (int) Database::first("SELECT p.sector_id s FROM ports p JOIN sectors x ON x.id = p.sector_id WHERE p.ore_mode = 'buy' AND p.destroyed = 0 AND x.is_fedspace = 0 LIMIT 1")['s']);
    Esito::scenario('50 unità di minerale a bordo, rivendute tre volte di fila');

    $vendite = 0;
    $incasso = 0;
    for ($i = 0; $i < 3; $i++) {
        $r = BlackMarket::sell($p2, $s2, 'ore', 50);
        if (!empty($r['ok'])) {
            $vendite++;
            $incasso += (int) $r['total'];
        }
    }
    $stiva  = Finti::stiva((int) $s2['id'], 'hold_ore');
    $saldo2 = Finti::crediti((int) $p2['id']);

    Esito::uguale('il carico si vende una volta sola', 1, $vendite);
    Esito::uguale('la stiva finisce a zero, non in negativo', 0, $stiva);
    Esito::uguale('incassato esattamente il valore di un carico', $incasso, $saldo2);

    // --- Reperto 02: trasferimento tutto-o-niente (pedaggio) ---------------

    Esito::sezione('Reperto 02 — pedaggio: tutto o niente');

    [$pa] = Finti::comandante(300);
    [$pb] = Finti::comandante(0);
    $idA = (int) $pa['id'];
    $idB = (int) $pb['id'];

    Esito::scenario('pedaggio da 1000 cr con soli 300 cr in cassa');
    $scoperto = Wallet::transfer($idA, $idB, 1000);
    Esito::verifica('il trasferimento scoperto viene respinto', $scoperto === false);
    Esito::uguale('il saldo del pagatore non si muove', 300, Finti::crediti($idA));
    Esito::uguale('il saldo del destinatario non si muove', 0, Finti::crediti($idB));

    Esito::scenario('pedaggio da 200 cr, capiente');
    $capiente = Wallet::transfer($idA, $idB, 200);
    Esito::verifica('il trasferimento capiente passa', $capiente === true);
    Esito::uguale('addebitato al pagatore', 100, Finti::crediti($idA));
    Esito::uguale('accreditato al destinatario', 200, Finti::crediti($idB));
    Esito::uguale('la somma complessiva si conserva', 300, Finti::crediti($idA) + Finti::crediti($idB));

    // --- Confisca limitata al saldo reale (bottino di combattimento) -------

    Esito::sezione('Bottino — confisca limitata al saldo reale');

    [$pv] = Finti::comandante(150);
    Esito::scenario('bottino nominale da 1000 cr su una vittima che ne ha 150');
    $preso = Wallet::seize((int) $pv['id'], 1000);
    Esito::uguale('si preleva al massimo quanto disponibile', 150, $preso);
    Esito::uguale('la vittima non va in negativo', 0, Finti::crediti((int) $pv['id']));

    Esito::scenario('bottino su una vittima senza un credito');
    Esito::uguale('non si preleva nulla', 0, Wallet::seize((int) $pv['id'], 500));

    // --- Guardia sui nomi di colonna --------------------------------------

    Esito::sezione('Wallet — guardia sui nomi di colonna');

    Esito::scenario('tentativo di passare una colonna fuori elenco');
    $bloccato = false;
    try {
        Wallet::charge((int) $pv['id'], ['credits = 0, handle' => 1]);
    } catch (InvalidArgumentException) {
        $bloccato = true;
    }
    Esito::verifica('la colonna non in elenco viene rifiutata', $bloccato);

    $bloccato2 = false;
    try {
        Wallet::takeFromShip(1, 'holds_total', 1);
    } catch (InvalidArgumentException) {
        $bloccato2 = true;
    }
    Esito::verifica('anche sulle colonne della nave', $bloccato2);

    $negativo = false;
    try {
        Wallet::charge((int) $pv['id'], ['credits' => -1000]);
    } catch (InvalidArgumentException) {
        $negativo = true;
    }
    Esito::verifica('un addebito negativo non diventa un accredito', $negativo);
};
