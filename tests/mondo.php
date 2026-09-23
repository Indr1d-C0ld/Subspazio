<?php

declare(strict_types=1);

/**
 * Il mondo intorno al giocatore: classifica, rapporto di rientro, eventi dal
 * vivo, sonde, pericoli, anomalie, stagioni. Audit del 23/09/2026.
 *
 * Alcune operazioni — chiudere una stagione, il Big Bang, riempire la radio
 * pubblica — toccherebbero tutti i giocatori veri: per quelle la prova e'
 * dichiaratamente statica (si controlla che il vincolo sia nel codice). Le
 * altre girano davvero, su comandanti sintetici.
 */

use App\Core\Database;
use App\Game\Digest;
use App\Game\Leaderboard;
use App\Game\Live;
use App\Game\PlayerService;
use App\Game\SectorFeatures;

return static function (): void {
    $feature = [];
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $sorgente = static fn (string $f): string => (string) file_get_contents(dirname(__DIR__) . '/' . $f);

    try {
        Esito::sezione('Classifica — un bandito non sta in vetta');

        [$b] = Finti::comandante(0, [], $sd);
        Database::run('UPDATE players SET rating = 999999999 WHERE id = ?', [(int) $b['id']]);
        Database::run("UPDATE users SET status = 'banned' WHERE id = ?", [(int) $b['user_id']]);
        $nomi = array_column(Leaderboard::topPlayers(25), 'handle');
        // Prima restava primo in classifica, poteva vincere la stagione e finire
        // nel notiziario della Federazione come comandante di vertice.
        Esito::verifica('non compare in classifica', !in_array($b['handle'], $nomi, true));
        Esito::verifica('ne\' nel podio di stagione (statica: la chiusura non si prova sul gioco vero)',
            str_contains($sorgente('src/Game/Season.php'), "WHERE u.status = 'active'"));
        Esito::verifica('ne\' nel notiziario', str_contains($sorgente('src/Game/FedNews.php'), "u.status = 'active'"));

        Esito::sezione('Stagioni — si chiudono una volta sola (statica)');
        $s = $sorgente('src/Game/Season.php');
        Esito::verifica('la chiusura si reclama con il vincolo sullo stato',
            str_contains($s, "WHERE id = ? AND status = 'active'") && strpos($s, "AND status = 'active'") < strpos($s, 'Leaderboard::recalcAll()'));

        Esito::sezione('Rapporto di rientro — solo a chi rientra davvero');

        Esito::scenario('un giocatore attivo un minuto fa, ultimo rapporto due ore fa');
        [$g] = Finti::comandante(0, [], $sd);
        Database::run('UPDATE players SET last_seen_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE), last_digest_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = ?', [(int) $g['id']]);
        Database::run("INSERT INTO ship_log (player_id, kind, severity, title, body, created_at) VALUES (?, 'system', 'info', 'prova', 'prova', DATE_SUB(NOW(), INTERVAL 1 HOUR))", [(int) $g['id']]);
        // Prima l'assenza si misurava dall'ultimo rapporto: a chi giocava senza
        // sosta il «rapporto di rientro» ricompariva ogni venti minuti.
        Esito::verifica('non vede nessun rapporto', Digest::forView($rileggi((int) $g['id'])) === null);

        Esito::scenario('un giocatore via da tre ore');
        Database::run('UPDATE players SET last_seen_at = DATE_SUB(NOW(), INTERVAL 3 HOUR), last_digest_at = DATE_SUB(NOW(), INTERVAL 5 HOUR) WHERE id = ?', [(int) $g['id']]);
        Esito::verifica('lo vede', Digest::forView($rileggi((int) $g['id'])) !== null);

        Esito::sezione('Eventi dal vivo — un messaggio lungo arriva comunque');

        [$l] = Finti::comandante(0, [], $sd);
        $prima = (int) Database::first('SELECT COALESCE(MAX(id), 0) m FROM live_events')['m'];
        Live::alert((int) $l['id'], 'dm', 'Messaggio privato', str_repeat('x', 470));
        $ev = Database::first("SELECT body FROM live_events WHERE id > ? AND scope = 'player' AND scope_id = ?", [$prima, (int) $l['id']]);
        // La radio accetta 480 caratteri, la colonna degli eventi 400: prima
        // l'inserimento falliva in silenzio e l'avviso in tempo reale spariva.
        Esito::verifica('l\'evento c\'e\'', $ev !== null);
        Esito::uguale('troncato alla misura della colonna', 400, mb_strlen((string) ($ev['body'] ?? '')));

        Esito::sezione('Sonde — una sonda, un rilevamento');

        [$pr, $sp] = Finti::comandante(0, ['probes' => 1], $sd);
        $adiacente = (int) Database::first('SELECT to_sector FROM warps WHERE from_sector = ? LIMIT 1', [$sd])['to_sector'];
        $vecchia = PlayerService::ship((int) $sp['id']);           // la fotografia con 1 sonda
        $r1 = SectorFeatures::probe($rileggi((int) $pr['id']), $vecchia, $adiacente);
        $r2 = SectorFeatures::probe($rileggi((int) $pr['id']), $vecchia, $adiacente);   // seconda richiesta, stessa fotografia
        Esito::verifica('la prima parte', !empty($r1['ok']), (string) ($r1['error'] ?? ''));
        Esito::verifica('la seconda no', empty($r2['ok']), (string) ($r2['error'] ?? ''));
        Esito::uguale('e le sonde restano a zero', 0, (int) Database::first('SELECT probes FROM ships WHERE id = ?', [(int) $sp['id']])['probes']);

        Esito::sezione('Pericoli — le tempeste ioniche non si estinguono');

        // una regione in cui i pericoli sono gia' tutti permanenti (e' lo stato
        // attuale di tutte le regioni del gioco)
        $reg = Database::first(
            "SELECT s.region_id r, MIN(s.id) sid FROM sector_features sf JOIN sectors s ON s.id = sf.sector_id
              WHERE sf.kind = 'hazard' AND sf.depleted = 0
              GROUP BY s.region_id HAVING SUM(sf.expires_at IS NULL) = COUNT(*) LIMIT 1"
        );
        if ($reg !== null) {
            $spawn = new ReflectionMethod(SectorFeatures::class, 'spawn');
            $tipi = [];
            for ($i = 0; $i < 6; $i++) {
                $max = (int) Database::first('SELECT COALESCE(MAX(id),0) m FROM sector_features')['m'];
                $spawn->invoke(null, (int) $reg['sid'], 'hazard', 'deep', 24);
                $nuova = Database::first('SELECT id, subtype FROM sector_features WHERE id > ?', [$max]);
                if ($nuova !== null) {
                    $feature[] = (int) $nuova['id'];
                    $tipi[] = $nuova['subtype'];
                    Database::run('DELETE FROM sector_features WHERE id = ?', [(int) $nuova['id']]);   // non resta nel gioco
                }
            }
            // Prima il tipo si estraeva a caso: due volte su tre un altro
            // pericolo permanente, e le tempeste sparivano.
            Esito::verifica('in una regione tutta di permanenti nascono tempeste', $tipi !== [] && count(array_unique($tipi)) === 1 && $tipi[0] === 'ion_storm',
                implode(', ', $tipi));
        } else {
            Esito::verifica('nessuna regione di soli permanenti: prova saltata', true);
        }

        Esito::sezione('Anomalie — chi arriva secondo lo sa');

        [$an] = Finti::comandante(0, [], $sd);
        Database::run("INSERT INTO sector_features (sector_id, kind, subtype, richness, data, spawned_at, depleted)
                       VALUES (?, 'anomaly', 'spaziale', 1, '{\"need\":55}', NOW(), 1)", [$sd]);
        $feature[] = Database::lastInsertId();
        Database::run('INSERT INTO player_feature_state (player_id, feature_id, progress, resolved) VALUES (?, ?, 40, 0)', [(int) $an['id'], (int) end($feature)]);
        $rs = SectorFeatures::study($rileggi((int) $an['id']), PlayerService::ship((int) $an['ship_id']), (int) end($feature));
        // Prima: «Nessuna anomalia scansionata», dopo 40 punti di studio spesi.
        Esito::verifica('gli viene detto che un altro l\'ha risolta',
            empty($rs['ok']) && mb_stripos((string) ($rs['error'] ?? ''), 'un altro comandante') !== false, (string) ($rs['error'] ?? ''));

        Esito::sezione('Universo nuovo — niente resta sui vecchi numeri (statica)');
        $gen = $sorgente('src/Game/UniverseGenerator.php');
        foreach (['planets', 'sector_fighters', 'sector_mines', 'sector_features', 'player_feature_state', 'player_sector_notes'] as $t) {
            Esito::verifica("la rigenerazione svuota {$t}", str_contains($gen, "'{$t}'"));
        }
    } finally {
        foreach ($feature as $id) {
            Database::run('DELETE FROM player_feature_state WHERE feature_id = ?', [$id]);
            Database::run('DELETE FROM sector_features WHERE id = ?', [$id]);
        }
        Database::run("DELETE le FROM live_events le JOIN players p ON le.scope = 'player' AND p.id = le.scope_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        Database::run("DELETE a FROM alerts a JOIN players p ON p.id = a.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
    }
};
