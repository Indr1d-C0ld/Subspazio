<?php

declare(strict_types=1);

/**
 * L'occultamento ha un prezzo (08/10/2026).
 *
 * Prima: comprato una volta, acceso gratis e per sempre, +1 turno a salto;
 * invisibile a NPC, caccia e pattuglie, si commerciava nell'Orlo senza rischio.
 * Ora: le interazioni lo fanno cadere, gli agganci possono scoprirlo, e il
 * dispositivo ha una riserva che si consuma a ogni salto.
 */

use App\Core\Database;
use App\Game\Cloak;
use App\Game\Economy;
use App\Game\Navigation;
use App\Game\PlayerService;

return static function (): void {
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $occulta = static function (int $shipId, int $carica = 8): void {
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1, cloak_carica = ?, cloak_carica_at = NOW() WHERE id = ?', [$carica, $shipId]);
    };

    Esito::sezione('Riserva — ogni salto occultato consuma una carica');

    // un settore della Cintura con almeno un vicino fuori dalla Federazione
    $da = Database::first(
        'SELECT w.from_sector a, w.to_sector b FROM warps w JOIN sectors s ON s.id = w.from_sector JOIN sectors t ON t.id = w.to_sector
          WHERE s.band = 1 AND t.band BETWEEN 1 AND 2 LIMIT 1'
    );
    [$p, $s] = Finti::comandante(0, [], (int) $da['a']);
    $occulta((int) $s['id'], 2);
    Navigation::move($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), (int) $da['b']);
    $nave = Database::first('SELECT cloaked, cloak_carica, cloak_carica_at FROM ships WHERE id = ?', [(int) $s['id']]);
    Esito::verifica('due cariche, un salto: ne resta una e la nave resta occultata', (int) $nave['cloaked'] === 1 && Cloak::carica($nave) === 1,
        json_encode($nave));
    Database::run('UPDATE ships SET cloak_carica = 0, cloak_carica_at = NOW() WHERE id = ?', [(int) $s['id']]);
    Navigation::move($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), (int) $da['a']);
    // Prima: occultata per sempre.
    Esito::uguale('a riserva vuota la nave riappare', 0, (int) Database::first('SELECT cloaked FROM ships WHERE id = ?', [(int) $s['id']])['cloaked']);
    Esito::verifica('e non si riaccende', empty(Cloak::toggle($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), true)['ok']));
    Esito::uguale('la riserva torna col tempo: 25 minuti sono due cariche', 2,
        Cloak::carica(['cloak_carica' => 0, 'cloak_carica_at' => date('Y-m-d H:i:s', time() - 25 * 60)]));
    Esito::uguale('fino al massimo', Cloak::caricaMax(),
        Cloak::carica(['cloak_carica' => 0, 'cloak_carica_at' => date('Y-m-d H:i:s', time() - 86400)]));

    Esito::sezione('Rilevamento — piu\' probabile lontano da Sol, contro pattuglie ed elite');

    Esito::verifica('5% nella Cintura, 40% nell\'Orlo', Cloak::rilevamentoPct([], 1, false) === 5.0 && Cloak::rilevamentoPct([], 5, false) === 40.0);
    Esito::uguale('una pattuglia nell\'Orlo: 60%', 60.0, Cloak::rilevamentoPct([], 5, true));
    Esito::uguale('il Velo di Precursore ne toglie 30', 30.0, Cloak::rilevamentoPct(['mod_effects' => ['cloak_stealth_pct' => 30]], 5, true));

    Esito::sezione('Interazioni — commerciare scopre la nave');

    $porto = Database::first("SELECT p.sector_id FROM ports p JOIN sectors s ON s.id = p.sector_id WHERE s.is_fedspace = 0 AND p.ore_mode = 'sell' AND p.ore_stock > 20 AND p.destroyed = 0 LIMIT 1");
    [$c, $cs] = Finti::comandante(100000, [], (int) $porto['sector_id']);
    $occulta((int) $cs['id']);
    $r = Economy::settle($rileggi((int) $c['id']), PlayerService::ship((int) $cs['id']), (int) $porto['sector_id'], 'ore', 'buy', 5, null);
    // Prima: si commerciava occultati, anche nell'Orlo col premio del 20%.
    Esito::verifica('dopo uno scambio la nave e\' visibile', !empty($r['ok'])
        && (int) Database::first('SELECT cloaked FROM ships WHERE id = ?', [(int) $cs['id']])['cloaked'] === 0, (string) ($r['error'] ?? ''));
    $fonte = (string) file_get_contents(dirname(__DIR__) . '/src/Game/SectorFeatures.php')
        . file_get_contents(dirname(__DIR__) . '/src/Game/Planets.php') . file_get_contents(dirname(__DIR__) . '/src/Game/BlackMarket.php');
    foreach (['recupero da un relitto', 'estrazione mineraria', 'scansione attiva', 'attracco a un pianeta', 'affare al mercato nero'] as $motivo) {
        Esito::verifica("cade anche per: {$motivo} (statica)", str_contains($fonte, "'{$motivo}'"));
    }
};
