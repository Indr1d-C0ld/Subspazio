<?php

declare(strict_types=1);

/**
 * Navigazione, schieramenti, viste remote. Audit del 23/09/2026; ogni prova
 * fallisce sul codice di prima.
 */

use App\Core\Database;
use App\Game\Deploy;
use App\Game\Navigation;
use App\Game\PlayerService;
use App\Game\SectorFeatures;

return static function (): void {
    $feature = [];
    // un settore di partenza fuori dalla Federazione con un'uscita libera da NPC
    $da = null; $a = null;
    foreach (Database::all('SELECT w.from_sector f, w.to_sector t FROM warps w JOIN sectors s ON s.id = w.to_sector
                             WHERE s.is_fedspace = 0 AND s.is_stardock = 0 LIMIT 200') as $r) {
        if ((int) Database::first('SELECT COUNT(*) n FROM npcs WHERE sector_id IN (?, ?)', [(int) $r['f'], (int) $r['t']])['n'] === 0
            && (int) Database::first('SELECT COUNT(*) n FROM sector_features WHERE sector_id = ?', [(int) $r['t']])['n'] === 0
            && (int) Database::first('SELECT COUNT(*) n FROM sector_fighters WHERE sector_id = ?', [(int) $r['t']])['n'] === 0) {
            [$da, $a] = [(int) $r['f'], (int) $r['t']];
            break;
        }
    }
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);

    try {
        Esito::sezione('Navigazione — un\'abilita\' si spende solo se il warp parte');

        Esito::scenario('«Rotta rapida» con zero turni verso un pozzo gravitazionale');
        Database::run("INSERT INTO sector_features (sector_id, kind, subtype, richness, data, spawned_at, depleted)
                       VALUES (?, 'hazard', 'gravity', 1, '{}', NOW(), 0)", [$a]);
        $feature[] = Database::lastInsertId();
        [$p, $s] = Finti::comandante(0, [], $da, 0);
        // davvero zero turni: senza la data del rifornimento, il primo accesso
        // ricaricherebbe la dose giornaliera
        Database::run('UPDATE players SET turns = 0, turns_reset_on = ? WHERE id = ?', [\App\Game\TurnManager::gameDay(), (int) $p['id']]);
        Database::run("INSERT INTO crew_pending (player_id, effect, magnitude, expires_at) VALUES (?, 'free_warp', 1, DATE_ADD(NOW(), INTERVAL 1 HOUR))", [(int) $p['id']]);
        $r = Navigation::move($rileggi((int) $p['id']), PlayerService::ship((int) $s['id']), $a);
        Esito::verifica('il warp e\' rifiutato (serve 1 turno per il pozzo)', empty($r['ok']), (string) ($r['error'] ?? ''));
        // Prima l'abilita' veniva consumata per prima cosa, anche se poi il warp
        // non partiva.
        Esito::verifica('e «Rotta rapida» resta disponibile',
            Database::first("SELECT 1 x FROM crew_pending WHERE player_id = ? AND effect = 'free_warp'", [(int) $p['id']]) !== null);

        Esito::scenario('un pozzo gravitazionale gia\' noto costa meno');
        Database::run('INSERT IGNORE INTO player_feature_state (player_id, feature_id) VALUES (?, ?)', [(int) $p['id'], (int) end($feature)]);
        Esito::verifica('meno di uno ignoto',
            SectorFeatures::gravityTurnPenalty((int) $p['id'], $a) < \App\Game\GameConfig::int('scan.hazard_gravity_turns', 1));

        Esito::sezione('Navigazione — chi e\' occultato non si annuncia');

        [$p2, $s2] = Finti::comandante(0, [], $da);
        Database::run('UPDATE ships SET dev_cloak = 1, cloaked = 1 WHERE id = ?', [(int) $s2['id']]);
        $prima = (int) Database::first('SELECT COALESCE(MAX(id), 0) m FROM live_events')['m'];
        Navigation::move($rileggi((int) $p2['id']), PlayerService::ship((int) $s2['id']), $a);
        $annunci = (int) Database::first(
            "SELECT COUNT(*) n FROM live_events WHERE id > ? AND kind IN ('move_in','move_out') AND payload LIKE ?",
            [$prima, '%' . $p2['handle'] . '%']
        )['n'];
        // Prima la scheda del settore nascondeva la nave, ma questi due eventi
        // dicevano a chiunque avesse la plancia aperta chi era entrato.
        Esito::uguale('nessun «e\' entrato nel settore»', 0, $annunci);

        Esito::sezione('Schieramenti — il pedaggio ha un tetto');

        [$p3, $s3] = Finti::comandante(0, ['fighters' => 10], $a);
        $rt = Deploy::deployFighters($rileggi((int) $p3['id']), PlayerService::ship((int) $s3['id']), 1, 'toll', 5_000_000);
        Esito::verifica('un pedaggio da 5 milioni e\' rifiutato', empty($rt['ok']), (string) ($rt['error'] ?? ''));
        Esito::uguale('e i caccia restano a bordo', 10, (int) Database::first('SELECT fighters FROM ships WHERE id = ?', [(int) $s3['id']])['fighters']);
    } finally {
        foreach ($feature as $id) {
            Database::run('DELETE FROM player_feature_state WHERE feature_id = ?', [$id]);
            Database::run('DELETE FROM sector_features WHERE id = ?', [$id]);
        }
        Database::run("DELETE FROM crew_pending WHERE player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
        Database::run("DELETE FROM sector_fighters WHERE owner_player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
    }
};
