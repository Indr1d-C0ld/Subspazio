<?php

declare(strict_types=1);

/**
 * Fasce di rischio attorno a Sol (30/09/2026).
 *
 * Prima gli NPC nascevano e vagavano ovunque fuori dalla Federazione: nei
 * trenta giorni precedenti, 22 ingaggi NPC contro giocatori e 22 navi
 * distrutte, molte a un salto da Sol, da Ferrengi dieci volte piu' forti della
 * nave iniziale. Ora la forza delle minacce, e le ricompense, crescono con la
 * distanza, e vicino a Sol chi perde viene razziato invece che distrutto.
 *
 * Le prove usano comandanti e NPC sintetici e tengono il lock del clock, cosi'
 * il tick vero non ingaggia i comandanti di prova a meta' strada.
 */

use App\Core\Database;
use App\Game\Combat;
use App\Game\Economy;
use App\Game\Fasce;
use App\Game\Navigation;
use App\Game\Npc;
use App\Game\Universe;

return static function (): void {
    $npcIds = [];
    $featIds = [];
    $settoreDi = static fn (int $band): int => (int) Database::first(
        'SELECT id FROM sectors WHERE is_fedspace = 0 AND band = ? ORDER BY id LIMIT 1', [$band]
    )['id'];
    $npcFinto = static function (int $sector, int $band, int $fighters, string $kind = 'pirate', string $name = '__test_predone') use (&$npcIds): array {
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, band, fighters, shields, combat_rating, credits, aggression, last_move_at)
             VALUES (?, ?, 'scout_marauder', ?, ?, ?, 0, 1.0, 0, 100, NOW())",
            [$kind, $name, $sector, $band, $fighters]
        );
        $id = Database::lastInsertId();
        $npcIds[] = $id;
        return Database::first('SELECT * FROM npcs WHERE id = ?', [$id]);
    };

    $lock = fopen((string) ($GLOBALS['__project_root'] ?? dirname(__DIR__)) . '/storage/tick.lock', 'c');
    $preso = false;
    for ($t = 0; $lock !== false && $t < 100 && !$preso; $t++) {
        $preso = flock($lock, LOCK_EX | LOCK_NB);
        if (!$preso) {
            usleep(100_000);   // il tick dura decine di ms: dieci secondi bastano
        }
    }
    Esito::verifica('preso il lock del clock, il cron non interferisce', $preso);

    try {
        Esito::sezione('Geografia — cinque anelli attorno a Sol');

        Esito::uguale('lo StarDock e\' in spazio federale (fascia 0)', 0,
            Fasce::diSettore((int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id']));
        Esito::uguale('ogni settore federale e\' in fascia 0', 0,
            (int) Database::first('SELECT COUNT(*) n FROM sectors WHERE is_fedspace = 1 AND band <> 0')['n']);
        Esito::uguale('ogni altro settore e\' in una fascia da I a V', 0,
            (int) Database::first('SELECT COUNT(*) n FROM sectors WHERE is_fedspace = 0 AND (band IS NULL OR band NOT BETWEEN 1 AND 5)')['n']);

        // piu' lontano da Sol, mai una fascia piu' bassa
        $c = Fasce::centro();
        $righe = Database::all('SELECT x, y, band FROM sectors WHERE is_fedspace = 0');
        usort($righe, static fn ($a, $b) => hypot($a['x'] - $c['cx'], $a['y'] - $c['cy']) <=> hypot($b['x'] - $c['cx'], $b['y'] - $c['cy']));
        $monotona = true;
        $prec = 0;
        foreach ($righe as $r) {
            $monotona = $monotona && (int) $r['band'] >= $prec;
            $prec = (int) $r['band'];
        }
        Esito::verifica('la fascia cresce con la distanza', $monotona);
        foreach ([1, 2, 3, 4, 5] as $b) {
            $n = (int) Database::first('SELECT COUNT(*) n FROM sectors WHERE band = ? AND has_port = 1', [$b])['n'];
            Esito::verifica("la fascia {$b} ha porti con cui commerciare", $n >= 5, "{$n} porti");
        }
        Esito::uguale('la frontiera profonda e\' la fascia V',
            'deep', Universe::sector($settoreDi(5))['region_kind']);
        Esito::uguale('la Cintura di Sol e\' frontiera',
            'frontier', Universe::sector($settoreDi(1))['region_kind']);

        Esito::sezione('NPC — ognuno nella sua fascia');

        $okI = true;
        for ($i = 0; $i < 6; $i++) {
            $id = Npc::spawnOne('pirate', 1);
            $npcIds[] = (int) $id;
            $n = Database::first('SELECT n.*, s.band sb FROM npcs n JOIN sectors s ON s.id = n.sector_id WHERE n.id = ?', [$id]);
            [$a, $z] = Fasce::predoniCaccia(1);
            $okI = $okI && (int) $n['sb'] === 1 && (int) $n['band'] === 1
                && (int) $n['fighters'] >= $a && (int) $n['fighters'] <= $z;
        }
        // Prima: da 600 a 3.000 caccia, in un settore qualunque.
        Esito::verifica('i predoni della Cintura nascono li\' e deboli (80-250 caccia)', $okI);

        $okF = true;
        for ($i = 0; $i < 6; $i++) {
            $id = Npc::spawnOne('ferrengi');
            $npcIds[] = (int) $id;
            $okF = $okF && (int) Database::first('SELECT s.band FROM npcs n JOIN sectors s ON s.id = n.sector_id WHERE n.id = ?', [$id])['band'] >= Fasce::ferrengiDa();
        }
        Esito::verifica('i Ferrengi nascono solo nelle fasce esterne', $okF);

        $bordo = Database::first(
            "SELECT w.from_sector id FROM warps w
               JOIN sectors a ON a.id = w.from_sector AND a.band = 2
               JOIN sectors b ON b.id = w.to_sector
              GROUP BY w.from_sector
             HAVING SUM(b.band = 1) > 0 AND SUM(b.band >= 3) > 0 LIMIT 1"
        );
        if ($bordo !== null) {
            $adj = Universe::warpsFrom((int) $bordo['id']);
            $dest = Npc::destinazioniAmmesse(['kind' => 'pirate', 'band' => 1, 'aggression' => 100], $adj);
            $fuori = array_filter($dest, static fn ($s) => Fasce::diSettore($s) < 1 || Fasce::diSettore($s) > 2);
            Esito::uguale('un predone della Cintura non va oltre l\'Anello dei Coloni', [], array_values($fuori));
            // Prima, con la regola simmetrica, i predoni della fascia III
            // scendevano nell'Anello e quelli della IV in Frontiera.
            $destII = Npc::destinazioniAmmesse(['kind' => 'pirate', 'band' => 2, 'aggression' => 100], $adj);
            Esito::verifica('un predone dell\'Anello non scende verso Sol',
                array_filter($destII, static fn ($s) => Fasce::diSettore($s) < 2) === []);
            $destF = Npc::destinazioniAmmesse(['kind' => 'ferrengi', 'band' => 4, 'aggression' => 100], $adj);
            Esito::verifica('un Ferrengi non scende sotto le fasce esterne',
                array_filter($destF, static fn ($s) => Fasce::diSettore($s) < Fasce::ferrengiDa()) === []);
        }
        Esito::verifica('vicino a Sol si attacca di rado, lontano quasi sempre',
            Fasce::ingaggioPct(0) === 0 && Fasce::ingaggioPct(1) < Fasce::ingaggioPct(3) && Fasce::ingaggioPct(3) < Fasce::ingaggioPct(5));

        Esito::sezione('Razzia — vicino a Sol chi perde non perde la nave');

        $s1 = $settoreDi(1);
        [$p, $sh] = Finti::comandante(10000, ['fighters' => 10, 'shields' => 0, 'hold_ore' => 5], $s1);
        $npc = $npcFinto($s1, 1, 500);
        $out = Combat::npcEngagePlayer($npc, $p, $sh, true);
        [$p2, $sh2] = Finti::ricarica((int) $p['id']);
        // Prima: nave distrutta, capsula allo StarDock.
        Esito::verifica('la nave resta in piedi', empty($out['destroyed']) && $sh2['type_key'] === 'merchant_cruiser', $sh2['type_key']);
        Esito::verifica('ed e\' rimasta dov\'era', (int) $p2['sector_id'] === $s1);
        Esito::uguale('il carico passa ai predoni', 0, (int) $sh2['hold_ore']);
        Esito::uguale('con il 10% dei crediti a bordo', 9000, (int) $p2['credits']);
        $npcDopo = Database::first('SELECT credits, cargo_ore FROM npcs WHERE id = ?', [(int) $npc['id']]);
        Esito::verifica('che chi abbatte il predone ritrova', (int) $npcDopo['credits'] === 1000 && (int) $npcDopo['cargo_ore'] === 5);
        Esito::verifica('e scatta la tregua', Fasce::inTregua($p2));

        Esito::scenario('rientro nello stesso settore durante la tregua');
        $npcFinto($s1, 1, 500);
        $e = Combat::onEnterSector($p2, $sh2);
        [$p3] = Finti::ricarica((int) $p['id']);
        Esito::verifica('i predoni lo lasciano passare', empty($e['destroyed']) && (int) $p3['credits'] === 9000,
            implode(' | ', $e['events']));

        Esito::scenario('la stessa sconfitta nelle Marche Remote (fascia IV)');
        $s4 = $settoreDi(4);
        [$q, $qs] = Finti::comandante(10000, ['fighters' => 10, 'shields' => 0], $s4);
        $out4 = Combat::npcEngagePlayer($npcFinto($s4, 4, 500), $q, $qs, true);
        [, $qs2] = Finti::ricarica((int) $q['id']);
        Esito::verifica('li\' si perde la nave', !empty($out4['destroyed']) && $qs2['type_key'] === 'escape_pod', $qs2['type_key']);

        Esito::verifica('i cacciatori di taglie non razziano: arrestano',
            !Combat::razziaInveceDiDistruzione(['sector_id' => $s1, 'name' => Combat::CACCIATORE]));

        Esito::sezione('Ricompense — piu\' lontano, piu\' si guadagna');

        $porto = Database::first(
            "SELECT p.sector_id, CASE WHEN p.ore_mode = 'buy' THEN 'ore' WHEN p.org_mode = 'buy' THEN 'organics' ELSE 'equipment' END c
               FROM ports p JOIN sectors s ON s.id = p.sector_id
              WHERE s.band = 5 AND p.destroyed = 0 AND (p.ore_mode = 'buy' OR p.org_mode = 'buy' OR p.equ_mode = 'buy') LIMIT 1"
        );
        $row = Economy::portAt((int) $porto['sector_id']);
        $v5 = Economy::quote($row, $porto['c'], 'sell', 10)['unit_raw'];
        $v1 = Economy::quote(['band' => 1] + $row, $porto['c'], 'sell', 10)['unit_raw'];
        // Prima: stesso prezzo ovunque.
        Esito::verifica('un porto dell\'Orlo paga il 20% in piu\' di uno della Cintura',
            abs($v5 / $v1 - 1.20) < 0.001, sprintf('%.4f', $v5 / $v1));
        $cv = array_search('sell', ['ore' => $row['ore_mode'], 'organics' => $row['org_mode'], 'equipment' => $row['equ_mode']], true);
        if ($cv !== false) {
            Esito::uguale('ma non vende a meno', Economy::quote($row, $cv, 'buy', 10)['unit_raw'],
                Economy::quote(['band' => 1] + $row, $cv, 'buy', 10)['unit_raw']);
        }
        Esito::verifica('e il premio da solo non basta a un giro in attivo (resta sotto il ricarico)',
            0.90 * (1 + Economy::premioFascia(['band' => 5])) < 1.12);

        $pick = new ReflectionMethod(\App\Game\Loot::class, 'pickItem');
        $rari = static function (int $band) use ($pick): int {
            $n = 0;
            for ($i = 0; $i < 400; $i++) {
                $it = $pick->invoke(null, 'npc', $band);
                $n += $it !== null && in_array($it['rarity'], ['exp', 'xeno', 'precursor'], true) ? 1 : 0;
            }
            return $n;
        };
        [$r1, $r5] = [$rari(1), $rari(5)];
        Esito::verifica('i moduli rari escono nell\'Orlo molto piu\' che nella Cintura', $r5 > 3 * max(1, $r1), "{$r1} contro {$r5} su 400");
        Esito::verifica('e anche piu\' spesso', Fasce::bottinoMult(5) > Fasce::bottinoMult(1));

        Esito::scenario('abbattere un predone di fascia V e uno di fascia I');
        $s5 = $settoreDi(5);
        [$k] = Finti::comandante(0, ['fighters' => 20000, 'shields' => 1000], $s5);
        $a5 = Combat::attackNpc($k, Finti::ricarica((int) $k['id'])[1], (int) $npcFinto($s5, 5, 10)['id']);
        [$k1] = Finti::comandante(0, ['fighters' => 20000, 'shields' => 1000], $s1);
        $a1 = Combat::attackNpc($k1, Finti::ricarica((int) $k1['id'])[1], (int) $npcFinto($s1, 1, 10)['id']);
        $base = \App\Game\GameConfig::int('npc.kill_exp_pirate', 70);
        Esito::uguale('fascia V: esperienza doppia', (int) round($base * Fasce::xpMult(5)), (int) ($a5['exp'] ?? -1), (string) ($a5['error'] ?? ''));
        Esito::uguale('fascia I: dimezzata', (int) round($base * Fasce::xpMult(1)), (int) ($a1['exp'] ?? -1), (string) ($a1['error'] ?? ''));

        $spawn = new ReflectionMethod(\App\Game\SectorFeatures::class, 'spawn');
        foreach ([1, 5] as $b) {
            $max = (int) Database::first('SELECT COALESCE(MAX(id),0) m FROM sector_features')['m'];
            $spawn->invoke(null, $settoreDi($b), 'cache', $b, 24);
            $f = Database::first('SELECT id, richness FROM sector_features WHERE id > ?', [$max]);
            $featIds[] = (int) $f['id'];
            [$ra, $rz] = Fasce::ricchezza($b);
            Esito::verifica("deposito di fascia {$b}: ricchezza {$ra}-{$rz}",
                (int) $f['richness'] >= $ra && (int) $f['richness'] <= $rz, 'ricchezza ' . $f['richness']);
        }
        Esito::uguale('nessun pericolo ambientale attivo nella Cintura di Sol', 0, (int) Database::first(
            "SELECT COUNT(*) n FROM sector_features sf JOIN sectors s ON s.id = sf.sector_id
              WHERE sf.kind = 'hazard' AND sf.depleted = 0 AND s.band < ?", [Fasce::pericoliDa()]
        )['n']);

        Esito::sezione('Plancia — il salto verso il pericolo si annuncia');

        $sol = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
        [$v] = Finti::comandante(0, [], $sol);
        $look = Navigation::look($v);
        $lontani = array_filter($look['warps'], static fn ($w) => $w['band'] >= 3);
        $vicini = array_filter($look['warps'], static fn ($w) => $w['band'] <= 1);
        Esito::verifica('ogni warp dice la fascia di arrivo', array_filter($look['warps'], static fn ($w) => !isset($w['band'])) === []);
        if ($lontani !== []) {
            Esito::verifica('le corsie federali verso l\'esterno chiedono conferma',
                array_filter($lontani, static fn ($w) => empty($w['avviso'])) === []);
        }
        Esito::verifica('i salti vicini no', array_filter($vicini, static fn ($w) => !empty($w['avviso'])) === []);
    } finally {
        if ($npcIds !== []) {
            Database::run('DELETE FROM npcs WHERE id IN (' . implode(',', array_fill(0, count($npcIds), '?')) . ')', $npcIds);
        }
        Database::run("DELETE FROM npcs WHERE name LIKE '\\_\\_test\\_%'");
        foreach ($featIds as $id) {
            Database::run('DELETE FROM sector_features WHERE id = ?', [$id]);
        }
        Database::run("DELETE le FROM live_events le JOIN players p ON le.scope = 'player' AND p.id = le.scope_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        Database::run("DELETE a FROM alerts a JOIN players p ON p.id = a.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        if ($preso) {
            flock($lock, LOCK_UN);
        }
    }
};
