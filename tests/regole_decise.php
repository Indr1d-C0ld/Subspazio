<?php

declare(strict_types=1);

/**
 * Regole decise con l'autore il 23/09/2026, dopo l'audit: mercato nero,
 * uccisioni e taglie, chiusura di stagione. Queste non erano difetti evidenti
 * ma scelte di disegno — le prove fissano la scelta fatta.
 */

use App\Core\Database;
use App\Game\BlackMarket;
use App\Game\Combat;
use App\Game\Economy;
use App\Game\GameConfig;
use App\Game\PlayerService;

return static function (): void {
    $contratti = [];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $compraOre = (int) Database::first("SELECT p.sector_id s FROM ports p JOIN sectors x ON x.id = p.sector_id
                                         WHERE p.ore_mode = 'buy' AND p.destroyed = 0 AND x.is_fedspace = 0 LIMIT 1")['s'];
    $arena = null;
    foreach (Database::all('SELECT id FROM sectors WHERE is_fedspace = 0 AND is_stardock = 0 ORDER BY id LIMIT 80') as $r) {
        if ((int) Database::first('SELECT COUNT(*) n FROM npcs WHERE sector_id = ?', [(int) $r['id']])['n'] === 0) {
            $arena = (int) $r['id'];
            break;
        }
    }

    try {
        Esito::sezione('Mercato nero — premio sul prezzo locale, niente arbitraggio sul posto');

        Esito::scenario('allo StarDock, dove il porto vende tutto');
        [$p, $s] = Finti::comandante(0, ['hold_ore' => 10], $sd);
        $r = BlackMarket::sell($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), 'ore', 10);
        Esito::verifica('il minerale non si rivende al mercato nero', empty($r['ok']), (string) ($r['error'] ?? ''));

        Esito::scenario('dove il porto compra minerale');
        $port = Economy::portAt($compraOre);
        $atteso = Economy::fairUnit($port, 'ore') * GameConfig::float('blackmarket.sell_premium', 1.15);
        Esito::verifica('il prezzo e\' il premio sul prezzo equo di QUEL porto',
            abs((float) BlackMarket::buyPrice($compraOre, 'ore') - $atteso) < 0.01,
            sprintf('%.2f cr/u', $atteso));

        Esito::sezione('Uccisioni — capsule, taglie, difesa');

        Esito::scenario('abbattere una capsula di salvataggio');
        [$caps, $sc] = Finti::comandante(0, [], $arena);
        Database::run("UPDATE ships SET type_key = 'escape_pod', fighters = 0, shields = 0 WHERE id = ?", [(int) $sc['id']]);
        [$cacc, $sa] = Finti::comandante(0, ['fighters' => 1000], $arena);
        Combat::attackShip($rileggi((int) $cacc['id']), PlayerService::ship((int) $sa['id']), (int) $caps['id']);
        $c = $rileggi((int) $cacc['id']);
        Esito::uguale('non conta come uccisione', 0, (int) $c['kills']);
        Esito::uguale('e non da\' esperienza', 0, (int) $c['experience']);

        Esito::scenario('abbattere in nave un ricercato con una taglia');
        [$ric, $sr] = Finti::comandante(0, ['fighters' => 5], $arena);
        Database::run('UPDATE players SET bounty = 7000, alignment = -300 WHERE id = ?', [(int) $ric['id']]);
        [$cac2, $sa2] = Finti::comandante(0, ['fighters' => 5000], $arena);
        $prima = (int) $rileggi((int) $cac2['id'])['credits'];
        Combat::attackShip($rileggi((int) $cac2['id']), PlayerService::ship((int) $sa2['id']), (int) $ric['id']);
        Esito::verifica('il cacciatore incassa la taglia', (int) $rileggi((int) $cac2['id'])['credits'] - $prima >= 7000,
            ((int) $rileggi((int) $cac2['id'])['credits'] - $prima) . ' cr');
        Esito::uguale('e la taglia si azzera', 0, (int) $rileggi((int) $ric['id'])['bounty']);

        Esito::scenario('chi si difende e distrugge l\'attaccante');
        [$dif, $sd2] = Finti::comandante(0, ['fighters' => 8000, 'shields' => 500], $arena);
        [$agg, $sg] = Finti::comandante(0, ['fighters' => 20], $arena);
        Database::run("UPDATE ships SET shields = 0 WHERE id = ?", [(int) $sg['id']]);
        Database::run(
            "INSERT INTO contracts (kind, issuer_player_id, target_player_id, reward, status) VALUES ('bounty', ?, ?, 4000, 'open')",
            [(int) $cacc['id'], (int) $agg['id']]
        );
        $contratti[] = Database::lastInsertId();
        $primaDif = (int) $rileggi((int) $dif['id'])['credits'];
        Combat::attackShip($rileggi((int) $agg['id']), PlayerService::ship((int) $sg['id']), (int) $dif['id']);
        $d = $rileggi((int) $dif['id']);
        Esito::uguale('il difensore riceve l\'uccisione', 1, (int) $d['kills']);
        Esito::verifica('e incassa il contratto sulla testa dell\'attaccante', (int) $d['credits'] - $primaDif >= 4000,
            ((int) $d['credits'] - $primaDif) . ' cr');

        Esito::sezione('Stagione — la ricchezza riparte da zero (statica: la chiusura non si prova sul gioco vero)');
        $s = (string) file_get_contents(dirname(__DIR__) . '/src/Game/Season.php');
        foreach ([
            'materiali azzerati'           => 'components = 0, crystals = 0, salvage = 0',
            'tesori di corporazione'       => 'UPDATE corporations SET treasury = 0',
            'lavori d\'Officina'           => 'TRUNCATE TABLE craft_jobs',
            'reputazione'                  => 'TRUNCATE TABLE player_reputation',
            'moduli tornano in inventario' => 'DELETE FROM ship_modules',
        ] as $cosa => $frammento) {
            Esito::verifica($cosa, str_contains($s, $frammento));
        }
    } finally {
        foreach ($contratti as $id) {
            Database::run('DELETE FROM contracts WHERE id = ?', [$id]);
        }
    }
};
