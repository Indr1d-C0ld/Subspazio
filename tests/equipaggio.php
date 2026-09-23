<?php

declare(strict_types=1);

/**
 * Equipaggio, missioni, incontri. Audit del 23/09/2026; ogni prova fallisce
 * sul codice di prima.
 */

use App\Core\Database;
use App\Game\AwayMissions;
use App\Game\Crew;
use App\Game\Encounters;

return static function (): void {
    $incontri = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $ufficiale = static function (int $pid, int $assegnato, string $stato = 'active', ?string $pronto = null, int $livello = 3): int {
        Database::run(
            "INSERT INTO officers (player_id, name, role, level, skills, assigned, status, ready_at)
             VALUES (?, '__test_uff', 'tactical', ?, '{\"tactics\":5}', ?, ?, ?)",
            [$pid, $livello, $assegnato, $stato, $pronto]
        );
        return Database::lastInsertId();
    };
    $assegnati = static fn (int $pid): int => (int) Database::first('SELECT COUNT(*) n FROM officers WHERE player_id = ? AND assigned = 1', [$pid])['n'];

    try {
        Esito::sezione('Equipaggio — i posti dello scafo contano');

        Esito::scenario('otto ufficiali su uno scafo da tre posti');
        [$p] = Finti::comandante(0, [], $sd);   // merchant_cruiser: 3 posti
        for ($i = 0; $i < 8; $i++) {
            $ufficiale((int) $p['id'], 1, 'active', null, $i + 1);
        }
        $b = Crew::passiveBonuses((int) $p['id']);
        // Prima tutti e otto i bonus valevano, qualunque fosse lo scafo.
        Esito::verifica('i bonus contano solo tre ufficiali', (int) $b['count'] <= 3, "contati {$b['count']}");
        Crew::adattaAlloScafo((int) $p['id']);
        Esito::uguale('gli altri vanno in panchina', 3, $assegnati((int) $p['id']));
        Esito::uguale(
            'e restano i piu\' esperti',
            [8, 7, 6],
            array_map('intval', array_column(Database::all('SELECT level FROM officers WHERE player_id = ? AND assigned = 1 ORDER BY level DESC', [(int) $p['id']]), 'level'))
        );

        Esito::scenario('la nave distrutta: la capsula non ha posti');
        Database::run("UPDATE ships SET type_key = 'escape_pod' WHERE id = (SELECT ship_id FROM players WHERE id = ?)", [(int) $p['id']]);
        Crew::adattaAlloScafo((int) $p['id']);
        Esito::uguale('nessuno resta assegnato', 0, $assegnati((int) $p['id']));

        Esito::sezione('Equipaggio — i feriti guariscono all\'ora indicata');

        [$p2] = Finti::comandante(0, [], $sd);
        $guarito = $ufficiale((int) $p2['id'], 1, 'injured', date('Y-m-d H:i:s', time() - 60));
        $ancora  = $ufficiale((int) $p2['id'], 1, 'injured', date('Y-m-d H:i:s', time() + 3600));
        Crew::healDue();
        Esito::uguale('chi ha passato l\'ora di guarigione torna in servizio', 'active',
            (string) Database::first('SELECT status FROM officers WHERE id = ?', [$guarito])['status']);
        Esito::uguale('chi no resta ferito', 'injured',
            (string) Database::first('SELECT status FROM officers WHERE id = ?', [$ancora])['status']);

        Esito::sezione('Missioni — si parte con chi e\' a bordo');

        [$p3] = Finti::comandante(0, [], $sd);
        $panchina = $ufficiale((int) $p3['id'], 0);
        Database::run(
            "INSERT INTO away_missions (player_id, kind, title, skills, turn_cost, rewards, status, expires_at)
             VALUES (?, 'salvage', '__test_missione', '{\"tactics\":1}', 1, '{\"credits\":1000}', 'open', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [(int) $p3['id']]
        );
        $mis = Database::lastInsertId();
        $rm = AwayMissions::run(Database::first('SELECT * FROM players WHERE id = ?', [(int) $p3['id']]), $mis, [$panchina]);
        // La pagina offriva solo ufficiali a bordo, ma una POST a mano accettava
        // anche quelli in panchina — anche da una capsula senza posti.
        Esito::verifica('un ufficiale in panchina non puo\' partire', empty($rm['ok']), (string) ($rm['error'] ?? ''));

        Esito::sezione('Incontri — una scelta a pagamento si paga');

        Esito::scenario('«compra» con la cassa vuota');
        Database::run(
            "INSERT INTO encounters (ckey, title, body, choices, enabled) VALUES ('__test_inc', 'Prova', 'Prova', ?, 0)",
            [json_encode([['key' => 'compra', 'label' => 'Compra', 'outcomes' => [['weight' => 1, 'text' => 'Affare fatto.', 'effects' => ['credits' => -800, 'crystals' => 5]]]]])]
        );
        $incontri[] = Database::lastInsertId();
        [$p4] = Finti::comandante(0, [], $sd);
        Database::run("INSERT INTO player_encounters (player_id, encounter_id, sector_id, status) VALUES (?, ?, ?, 'pending')",
            [(int) $p4['id'], (int) end($incontri), $sd]);
        $ri = Encounters::resolve((int) $p4['id'], 'compra');
        // Prima il costo veniva scalato con GREATEST(0, ...): con zero crediti la
        // merce arrivava gratis e il riepilogo diceva comunque «-800 cr».
        Esito::verifica('viene rifiutata', empty($ri['ok']), (string) ($ri['error'] ?? ''));
        Esito::uguale('e non arriva niente', 0, (int) Database::first('SELECT crystals FROM players WHERE id = ?', [(int) $p4['id']])['crystals']);
        Esito::uguale('l\'incontro resta in sospeso, per un\'altra scelta', 'pending',
            (string) Database::first('SELECT status FROM player_encounters WHERE player_id = ?', [(int) $p4['id']])['status']);
    } finally {
        Database::run("DELETE FROM player_encounters WHERE encounter_id IN (SELECT id FROM encounters WHERE ckey = '__test_inc')");
        Database::run("DELETE FROM encounters WHERE ckey = '__test_inc'");
        Database::run("DELETE FROM away_missions WHERE title = '__test_missione'");
        Database::run("DELETE FROM officers WHERE name = '__test_uff'");
        Esito::uguale('nessun ufficiale o incontro di prova lasciato indietro', 0,
            (int) Database::first("SELECT (SELECT COUNT(*) FROM officers WHERE name = '__test_uff') + (SELECT COUNT(*) FROM encounters WHERE ckey = '__test_inc') n")['n']);
    }
};
