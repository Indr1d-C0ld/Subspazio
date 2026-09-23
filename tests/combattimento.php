<?php

declare(strict_types=1);

/**
 * Combattimento: le regole che l'audit del 23/09/2026 ha trovato rotte.
 *
 * Ogni prova e' costruita per FALLIRE contro il codice di prima. Dove possibile
 * lo scontro e' deterministico: numeri scelti in modo che la casualita' del
 * duello non possa cambiare l'esito.
 */

use App\Core\Database;
use App\Game\Combat;
use App\Game\PlayerService;

return static function (): void {
    $pianeti = [];
    $npc = [];

    // Settore fuori dalla Federazione, senza NPC: niente incontri che sporchino.
    $settore = null;
    foreach (Database::all('SELECT id FROM sectors WHERE is_fedspace = 0 AND is_stardock = 0 ORDER BY id LIMIT 80') as $r) {
        if ((int) Database::first('SELECT COUNT(*) n FROM npcs WHERE sector_id = ?', [(int) $r['id']])['n'] === 0) {
            $settore = (int) $r['id'];
            break;
        }
    }
    $fed = (int) Database::first('SELECT id FROM sectors WHERE is_fedspace = 1 AND is_stardock = 0 LIMIT 1')['id'];

    try {
        // --- il duello ------------------------------------------------------

        Esito::sezione('Combattimento — nessuno e\' invulnerabile');

        Esito::scenario('una nave con zero caccia ma scudi carichi');
        // Prima il duello girava solo finche' entrambi avevano caccia: zero
        // caccia e 500 scudi = nessun round, nessun danno, mai distrutta.
        $r = Combat::duel(1000, 0, 1.0, 0, 500, 1.0);
        Esito::verifica('il duello avviene', $r['rounds'] > 0, "{$r['rounds']} round");
        Esito::uguale('gli scudi vengono consumati', 0, $r['def_shd']);
        Esito::uguale('e l\'attaccante non perde nulla contro chi non spara', 0, $r['att_lost']);

        // --- assalto planetario ---------------------------------------------

        Esito::sezione('Combattimento — l\'assalto planetario');

        Esito::verifica('c\'e\' un settore adatto', $settore !== null);
        [$difensore] = Finti::comandante(0, [], $settore);
        $pianeta = static function (int $quasar) use (&$pianeti, $settore, $difensore): int {
            Database::run(
                "INSERT INTO planets (sector_id, name, type_key, owner_player_id, created_by, fighters, shields, quasar_level, last_prod_at)
                 VALUES (?, '__test_pianeta', 'M', ?, ?, 0, 0, ?, NOW())",
                [$settore, (int) $difensore['id'], (int) $difensore['id'], $quasar]
            );
            $id = Database::lastInsertId();
            $pianeti[] = $id;
            return $id;
        };

        Esito::scenario('si lanciano 500 caccia su 10.000: gli altri restano a bordo');
        [$att, $nave] = Finti::comandante(0, ['fighters' => 10000], $settore);
        $res = Combat::attackPlanet($att, PlayerService::ship((int) $nave['id']), $pianeta(0), 500);
        $dopo = (int) Database::first('SELECT fighters FROM ships WHERE id = ?', [(int) $nave['id']])['fighters'];
        Esito::verifica('l\'assalto riesce', !empty($res['ok']), (string) ($res['error'] ?? ''));
        // Contro un pianeta senza difese l'ala non perde nessuno: i caccia a
        // bordo devono essere tutti e 10.000. Prima erano 500.
        Esito::uguale('la nave ha ancora tutti i suoi caccia', 10000, $dopo);

        Esito::scenario('il Quasar spazza l\'ala lanciata, ma non la nave');
        [$att2, $nave2] = Finti::comandante(0, ['fighters' => 10000], $settore);
        Combat::attackPlanet($att2, PlayerService::ship((int) $nave2['id']), $pianeta(1), 500);
        $n2 = Database::first('SELECT fighters, destroyed FROM ships WHERE id = ?', [(int) $nave2['id']]);
        $pod = (string) (Database::first('SELECT s.type_key FROM players p JOIN ships s ON s.id = p.ship_id WHERE p.id = ?', [(int) $att2['id']])['type_key'] ?? '');
        // 2.200 danni contro 0 scudi: l'ala di 500 e' persa. Prima la nave
        // finiva nella capsula di salvataggio con 9.500 caccia mai lanciati.
        Esito::verifica('la nave non e\' distrutta', $pod !== 'escape_pod', "scafo: {$pod}");
        Esito::uguale('e conserva i caccia rimasti a bordo', 9500, (int) ($n2['fighters'] ?? -1));

        Esito::scenario('assaltare un pianeta fa cadere l\'occultamento');
        [$att3, $nave3] = Finti::comandante(0, ['fighters' => 1000], $settore);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1 WHERE id = ?', [(int) $nave3['id']]);
        Combat::attackPlanet($att3, PlayerService::ship((int) $nave3['id']), $pianeta(0), 100);
        Esito::uguale(
            'la nave non e\' piu\' occultata',
            0,
            (int) Database::first('SELECT cloaked FROM ships WHERE id = ?', [(int) $nave3['id']])['cloaked']
        );

        // --- NPC ------------------------------------------------------------

        Esito::sezione('Combattimento — la Federazione vale anche per gli NPC');

        Esito::scenario('un attacco a un NPC in spazio Federazione');
        [$att4, $nave4] = Finti::comandante(0, ['fighters' => 1000], $fed);
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, fighters, shields, combat_rating, credits, aggression)
             VALUES ('trader', '__test_npc', 'merchant_cruiser', ?, ?, 10, 0, 1.0, 500, 0)",
            [$fed, $fed]
        );
        $npc[] = Database::lastInsertId();
        $rn = Combat::attackNpc($att4, PlayerService::ship((int) $nave4['id']), (int) end($npc), 100);
        Esito::verifica('viene rifiutato', empty($rn['ok']), (string) ($rn['error'] ?? 'nessun errore'));
        Esito::verifica(
            'e l\'NPC e\' ancora li\'',
            Database::first('SELECT 1 x FROM npcs WHERE id = ?', [(int) end($npc)]) !== null
        );

        Esito::scenario('un attacco rifiutato non smaschera e non consuma nulla');
        [$att5, $nave5] = Finti::comandante(0, ['fighters' => 0], $settore);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1 WHERE id = ?', [(int) $nave5['id']]);
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, fighters, shields, combat_rating, credits, aggression)
             VALUES ('trader', '__test_npc', 'merchant_cruiser', ?, ?, 10, 0, 1.0, 500, 0)",
            [$settore, $settore]
        );
        $npc[] = Database::lastInsertId();
        $rr = Combat::attackNpc($att5, PlayerService::ship((int) $nave5['id']), (int) end($npc));
        Esito::verifica('senza caccia l\'attacco e\' rifiutato', empty($rr['ok']));
        Esito::uguale(
            'e l\'occultamento resta attivo',
            1,
            (int) Database::first('SELECT cloaked FROM ships WHERE id = ?', [(int) $nave5['id']])['cloaked']
        );

        // --- allineamento ---------------------------------------------------

        Esito::sezione('Combattimento — chi e\' fuorilegge lo decide una soglia sola');

        Esito::scenario('uccidere un comandante «Neutrale» a -50');
        [$vittima] = Finti::comandante(0, ['fighters' => 10], $settore);
        Database::run('UPDATE players SET alignment = -50 WHERE id = ?', [(int) $vittima['id']]);
        [$att6, $nave6] = Finti::comandante(0, ['fighters' => 5000], $settore);
        $prima = (int) Database::first('SELECT alignment FROM players WHERE id = ?', [(int) $att6['id']])['alignment'];
        $rk = Combat::attackShip($att6, PlayerService::ship((int) $nave6['id']), (int) $vittima['id']);
        $dopoA = (int) Database::first('SELECT alignment FROM players WHERE id = ?', [(int) $att6['id']])['alignment'];
        Esito::verifica('il bersaglio e\' distrutto', !empty($rk['ok']) && !empty($rk['destroyed_target'] ?? $rk['destroyed'] ?? true));
        // -50 e' «Neutrale» (la soglia dei fuorilegge e' -100): ucciderlo deve
        // costare allineamento. Prima la regola era `>= 0`, e lo premiava.
        Esito::verifica('ucciderlo costa allineamento', $dopoA < $prima, "{$prima} -> {$dopoA}");
    } finally {
        foreach ($pianeti as $id) {
            Database::run('DELETE FROM planets WHERE id = ?', [$id]);
        }
        foreach ($npc as $id) {
            Database::run('DELETE FROM npcs WHERE id = ?', [$id]);
        }
        Esito::uguale(
            'nessun pianeta o NPC di prova lasciato indietro',
            0,
            (int) Database::first("SELECT (SELECT COUNT(*) FROM planets WHERE name = '__test_pianeta') + (SELECT COUNT(*) FROM npcs WHERE name = '__test_npc') n")['n']
        );
    }
};
