<?php

declare(strict_types=1);

/**
 * Pianeti: crescita, produzione, trasferimenti, quote. Audit del 23/09/2026;
 * ogni prova fallisce sul codice di prima.
 */

use App\Core\Database;
use App\Game\GameConfig;
use App\Game\Planets;
use App\Game\PlayerService;
use App\Game\TurnManager;

return static function (): void {
    $pianeti = [];
    $fed = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 1 AND is_stardock = 0 LIMIT 1')['id'];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $fuori = null;
    foreach (Database::all('SELECT id FROM sectors WHERE is_fedspace = 0 ORDER BY id LIMIT 60') as $r) {
        if ((int) Database::first('SELECT COUNT(*) n FROM planets WHERE sector_id = ?', [(int) $r['id']])['n'] === 0) {
            $fuori = (int) $r['id'];
            break;
        }
    }
    $nuovo = static function (int $owner, array $campi = []) use (&$pianeti, $fuori): int {
        $c = array_merge(['col_idle' => 0, 'col_ore' => 0, 'stock_ore' => 0, 'fighters' => 0, 'citadel_level' => 0, 'quasar_level' => 0], $campi);
        Database::run(
            "INSERT INTO planets (sector_id, name, type_key, owner_player_id, created_by, col_idle, col_ore, stock_ore, fighters, citadel_level, quasar_level, last_prod_at)
             VALUES (?, '__test_pianeta', 'M', ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$fuori, $owner, $owner, $c['col_idle'], $c['col_ore'], $c['stock_ore'], $c['fighters'], $c['citadel_level'], $c['quasar_level']]
        );
        $id = Database::lastInsertId();
        $pianeti[] = $id;
        return $id;
    };
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);

    try {
        Esito::sezione('Pianeti — la colonia cresce anche se il clock passa ogni minuto');

        Esito::scenario('1.000 coloni su un pianeta M, un\'ora di passaggi al minuto');
        [$p] = Finti::comandante(0, [], $fuori);
        $id = $nuovo((int) $p['id'], ['col_idle' => 1000]);
        for ($i = 0; $i < 60; $i++) {
            Database::run('UPDATE planets SET last_prod_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE id = ?', [$id]);
            Planets::get($id);
        }
        $cresciuti = (int) Database::first('SELECT col_idle FROM planets WHERE id = ?', [$id])['col_idle'] - 1000;
        $rate = (float) Database::first("SELECT breed_rate FROM planet_types WHERE ckey = 'M'")['breed_rate'];
        $attesi = 1000 * $rate;
        // Prima: floor(1000 x 1,00065) = 1000 a ogni passaggio, orologio azzerato,
        // crescita zero per sempre.
        Esito::verifica('la colonia cresce come deve, in media', $cresciuti >= $attesi * 0.4 && $cresciuti <= $attesi * 2.0,
            sprintf('attesi ~%.0f, cresciuti %d', $attesi, $cresciuti));

        Esito::scenario('la merce scaricata oltre il tetto di produzione non sparisce');
        $cap = (int) Database::first("SELECT max_col FROM planet_types WHERE ckey = 'M'")['max_col'] * GameConfig::int('planet.stock_cap_mult', 20);
        $id2 = $nuovo((int) $p['id'], ['col_ore' => 100, 'stock_ore' => $cap + 5000]);
        Database::run('UPDATE planets SET last_prod_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = ?', [$id2]);
        Planets::get($id2);
        Esito::uguale('le scorte restano', $cap + 5000, (int) Database::first('SELECT stock_ore FROM planets WHERE id = ?', [$id2])['stock_ore']);

        Esito::sezione('Pianeti — trasferimenti');

        Esito::scenario('tre carichi simultanei dell\'intera scorta');
        [$p2, $s2] = Finti::comandante(0, [], $fuori);
        Database::run('UPDATE ships SET holds_total = 300 WHERE id = ?', [(int) $s2['id']]);
        $id3 = $nuovo((int) $p2['id'], ['stock_ore' => 100]);
        $t0 = microtime(true) + 1.2;
        $proc = [];
        for ($i = 0; $i < 3; $i++) {
            $proc[] = proc_open(['php', dirname(__DIR__) . '/tests/_corsa.php', 'carica_pianeta', (string) $t0, (string) $p2['id'], (string) $id3, '100'],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipe);
        }
        foreach ($proc as $ph) { proc_close($ph); }
        $scorta = (int) Database::first('SELECT stock_ore FROM planets WHERE id = ?', [$id3])['stock_ore'];
        $stiva = (int) Database::first('SELECT hold_ore FROM ships WHERE id = ?', [(int) $s2['id']])['hold_ore'];
        // Prima: il pianeta a -200 e la nave a 300.
        Esito::verifica('il pianeta non va sotto zero', $scorta >= 0, "scorta {$scorta}");
        Esito::uguale('e la nave riceve la merce una volta sola', 100, $stiva);

        Esito::scenario('il richiamo della guarnigione rispetta lo scafo');
        [$p3, $s3] = Finti::comandante(0, ['fighters' => 0], $fuori);
        $maxF = (int) Database::first("SELECT max_fighters FROM ship_types WHERE ckey = 'merchant_cruiser'")['max_fighters'];
        $id4 = $nuovo((int) $p3['id'], ['fighters' => $maxF + 10000]);
        $rg = Planets::garrison($rileggi((int) $p3['id']), PlayerService::ship((int) $s3['id']), $id4, $maxF + 10000, 'recall');
        Esito::verifica('richiamarne piu\' del massimo e\' rifiutato', empty($rg['ok']), (string) ($rg['error'] ?? ''));
        Esito::verifica('e la nave non supera il massimo',
            (int) Database::first('SELECT fighters FROM ships WHERE id = ?', [(int) $s3['id']])['fighters'] <= $maxF);

        Esito::sezione('Pianeti — le regole di fondo');

        Esito::scenario('un Genesis in spazio Federazione');
        [$p4, $s4] = Finti::comandante(0, ['genesis' => 1], $fed);
        $rgen = Planets::genesis($rileggi((int) $p4['id']), PlayerService::ship((int) $s4['id']));
        Esito::verifica('e\' rifiutato', empty($rgen['ok']), (string) ($rgen['error'] ?? ''));
        Esito::uguale('e il siluro resta a bordo', 1, (int) Database::first('SELECT genesis FROM ships WHERE id = ?', [(int) $s4['id']])['genesis']);

        Esito::scenario('il Quasar ha un livello massimo');
        [$p5, $s5] = Finti::comandante(100_000_000, [], $fuori);
        $maxQ = GameConfig::int('planet.quasar_max_level', 10);
        $id5 = $nuovo((int) $p5['id'], ['citadel_level' => 3, 'quasar_level' => $maxQ]);
        Database::run('UPDATE planets SET stock_equ = 100000 WHERE id = ?', [$id5]);
        $rq = Planets::buildQuasar($rileggi((int) $p5['id']), $id5);
        Esito::verifica('oltre il massimo e\' rifiutato', empty($rq['ok']), (string) ($rq['error'] ?? ''));

        Esito::scenario('la scheda di un pianeta altrui, da lontano');
        [$estraneo] = Finti::comandante(0, [], $sd);
        $p6 = Planets::get($id5);
        Esito::verifica('non si vede da un altro settore', !Planets::visibleTo($p6, $rileggi((int) $estraneo['id'])));
        Database::run('UPDATE players SET sector_id = ? WHERE id = ?', [$fuori, (int) $estraneo['id']]);
        Esito::verifica('si vede stando nel suo settore', Planets::visibleTo($p6, $rileggi((int) $estraneo['id'])));
        Esito::verifica('e il proprietario la vede da ovunque', Planets::visibleTo($p6, $rileggi((int) $p5['id'])));
        foreach (['src/Controllers/PlanetController.php', 'src/Controllers/GameApiController.php'] as $f) {
            Esito::verifica("{$f} applica la regola", str_contains((string) file_get_contents(dirname(__DIR__) . '/' . $f), 'Planets::visibleTo('));
        }

        Esito::sezione('Pianeti — la quota di coloni segue il giorno di gioco');

        Esito::scenario('un ritiro fatto un secondo prima del reset non conta per oggi');
        [$p7, $s7] = Finti::comandante(0, [], $sd);
        Database::run('UPDATE ships SET holds_total = 200 WHERE id = ?', [(int) $s7['id']]);
        $quota = GameConfig::int('planet.colonist_pickup_per_day', 5000);
        Database::run(
            "INSERT INTO trade_log (player_id, port_id, sector_id, commodity, action, qty, unit_price, total, fair_total, created_at)
             VALUES (?, 0, ?, 'organics', 'buy', ?, 0, 0, 0, DATE_SUB(?, INTERVAL 1 SECOND))",
            [(int) $p7['id'], $sd, $quota, TurnManager::gameDayStart()]
        );
        // Quella riga ha la data di calendario del giorno di gioco (le 02:59:59),
        // ma appartiene al giorno di gioco precedente: prima veniva contata oggi.
        $rp = Planets::pickupColonists($rileggi((int) $p7['id']), PlayerService::ship((int) $s7['id']), 10);
        Esito::verifica('la quota di oggi e\' intatta', !empty($rp['ok']), (string) ($rp['error'] ?? ''));
    } finally {
        foreach ($pianeti as $id) {
            Database::run('DELETE FROM planets WHERE id = ?', [$id]);
        }
        Database::run("DELETE t FROM trade_log t LEFT JOIN players p ON p.id = t.player_id WHERE p.id IS NULL OR p.handle LIKE '\\_\\_test\\_%'");
        Esito::uguale('nessun pianeta di prova lasciato indietro', 0,
            (int) Database::first("SELECT COUNT(*) n FROM planets WHERE name = '__test_pianeta'")['n']);
    }
};
