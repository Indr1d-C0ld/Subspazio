<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Stagioni con ladder e reset periodico. Una sola stagione attiva.
 * Il reset e' "soft": le righe players restano (id stabile -> i traguardi
 * persistono), tornano ai valori iniziali; navi resettate; pianeti e/o
 * corp azzerati secondo configurazione; universo rigenerato su richiesta.
 */
final class Season
{
    /** @return array<string,mixed> */
    public static function current(): array
    {
        $s = Database::first("SELECT * FROM seasons WHERE status = 'active' ORDER BY number DESC LIMIT 1");
        if ($s === null) {
            $n = (int) (Database::first('SELECT COALESCE(MAX(number),0) m FROM seasons')['m'] ?? 0) + 1;
            Database::run('INSERT INTO seasons (number, name) VALUES (?, ?)', [$n, "Stagione {$n}"]);
            $s = Database::first('SELECT * FROM seasons WHERE number = ?', [$n]);
        }
        return $s;
    }

    /** @return list<array<string,mixed>> */
    public static function hall(): array
    {
        return Database::all(
            "SELECT s.number, s.name, s.ended_at,
                    (SELECT handle FROM season_results r WHERE r.season_id = s.id AND r.position = 1) AS winner
             FROM seasons s WHERE s.status = 'ended' ORDER BY s.number DESC"
        );
    }

    /** @return list<array<string,mixed>> */
    public static function results(int $number): array
    {
        return Database::all(
            'SELECT r.* FROM season_results r JOIN seasons s ON s.id = r.season_id WHERE s.number = ? ORDER BY r.position',
            [$number]
        );
    }

    /**
     * Chiude la stagione attiva e ne apre una nuova.
     *
     * @return array{ok:bool, error?:string, number?:int, snapshot?:int, universe?:array}
     */
    public static function close(int $actorUserId, bool $regenUniverse): array
    {
        $season = self::current();
        $sid = (int) $season['id'];

        // Si reclama la chiusura prima di tutto: un doppio invio del modulo
        // chiudeva due stagioni, e la seconda premiava col «top 10» comandanti
        // appena azzerati, cioe' a caso.
        if (Database::run("UPDATE seasons SET status = 'ended', ended_at = NOW() WHERE id = ? AND status = 'active'", [$sid])->rowCount() === 0) {
            return ['ok' => false, 'error' => 'La stagione e\' gia\' stata chiusa.'];
        }

        Leaderboard::recalcAll();
        $topN = GameConfig::int('season.snapshot_top', 25);
        $top = Database::all(
            "SELECT p.id, p.handle, p.rating, p.experience, p.kills,
                    (SELECT COUNT(*) FROM planets pl WHERE pl.owner_player_id = p.id AND pl.destroyed = 0) AS planets
             FROM players p JOIN users u ON u.id = p.user_id
             WHERE u.status = 'active'
             ORDER BY p.rating DESC, p.experience DESC LIMIT ?",
            [$topN]
        );

        $pos = 0;
        foreach ($top as $r) {
            $pos++;
            Database::run(
                'INSERT INTO season_results (season_id, position, player_id, handle, rating, experience, kills, planets)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$sid, $pos, $r['id'], $r['handle'], (int) $r['rating'], (int) $r['experience'], (int) $r['kills'], (int) $r['planets']]
            );
            if ($pos <= 10) {
                Achievements::award((int) $r['id'], 'season_top10');
            }
        }
        $winner = $top[0]['handle'] ?? '(nessuno)';

        $nextNum = (int) $season['number'] + 1;
        Database::run('INSERT INTO seasons (number, name) VALUES (?, ?)', [$nextNum, "Stagione {$nextNum}"]);
        GameConfig::set('season.number', (string) $nextNum);

        Radio::system("FINE STAGIONE {$season['number']} — vince {$winner}. Comincia la Stagione {$nextNum}: "
            . 'crediti, navi, pianeti, materiali, tesori di corporazione e reputazione ripartono da zero; '
            . 'restano traguardi, esperienza degli ufficiali e moduli, che tornano in inventario.');

        // reset
        $wipePlanets = GameConfig::bool('season.wipe_planets', true) || $regenUniverse;
        $wipeCorps = GameConfig::bool('season.wipe_corps', false);
        $dock = (int) (Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'] ?? 1);

        $u = null;
        if ($regenUniverse) {
            $u = (new UniverseGenerator([
                'sectors'         => GameConfig::int('universe.sectors', 1000),
                'fedspace_max'    => GameConfig::int('universe.fedspace_max', 10),
                'stardock_sector' => GameConfig::int('universe.stardock_sector', 1),
                'warp_density'    => GameConfig::float('universe.warp_density', 3.2),
            ]))->generate(true);
            PortGenerator::generate(true);
            $dock = (int) (Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'] ?? 1);
        }

        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['contracts', 'combat_log', 'trade_log', 'move_log', 'sector_fighters', 'sector_mines',
            'ship_limpets', 'player_visited_sectors', 'bank_accounts', 'live_events', 'alerts',
            'messages', 'msg_state', 'player_sector_notes'] as $t) {
            $pdo->exec("TRUNCATE TABLE {$t}");
        }
        if ($wipePlanets) {
            $pdo->exec('TRUNCATE TABLE planets');
        }
        if ($wipeCorps) {
            $pdo->exec('TRUNCATE TABLE corp_alliances');
            $pdo->exec('TRUNCATE TABLE corp_members');
            $pdo->exec('TRUNCATE TABLE corporations');
        }
        $pdo->exec('DELETE FROM npcs');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $startCredits = GameConfig::int('player.start_credits', 1000);
        $startShip = GameConfig::str('player.start_ship', 'merchant_cruiser');
        $startHolds = GameConfig::int('player.start_holds', 20);
        $perDay = TurnManager::perDay();
        $today = TurnManager::gameDay();
        $type = Database::first('SELECT * FROM ship_types WHERE ckey = ?', [$startShip])
            ?? Database::first('SELECT * FROM ship_types ORDER BY sort_order LIMIT 1');

        Database::run(
            'UPDATE players SET sector_id = ?, credits = ?, turns = ?, turns_reset_on = ?,
             experience = 0, alignment = 0, kills = 0, deaths = 0, port_busts = 0, bounty = 0,
             total_warps = 0, rating = 0, last_move_at = NULL, last_death_at = NULL,
             protected_until = DATE_ADD(NOW(), INTERVAL ? HOUR)' . ($wipeCorps ? ', corp_id = NULL' : ''),
            [$dock, $startCredits, $perDay, $today, GameConfig::int('newbie.protect_hours', 48)]
        );
        Database::run(
            "UPDATE ships SET type_key = ?, sector_id = ?, holds_total = ?,
             hold_ore = 0, hold_organics = 0, hold_equipment = 0, hold_colonists = 0,
             fighters = ?, shields = ?, mines_armid = 0, mines_limpet = 0, probes = 0, genesis = 0,
             escape_pod = 1, dev_scanner = 'none', dev_transwarp = 0, dev_cloak = 0, cloaked = 0, mining_laser = 0",
            [$startShip, $dock, max($startHolds, (int) $type['base_holds']), (int) $type['base_fighters'], (int) $type['base_shields']]
        );
        Database::run('INSERT IGNORE INTO player_visited_sectors (player_id, sector_id) SELECT id, ? FROM players', [$dock]);
        Database::run('DELETE FROM events');

        // La ricchezza riparte da zero per tutti. Prima sopravvivevano i tesori
        // delle corporazioni, i materiali e i lavori d'Officina — che si potevano
        // annullare nella stagione nuova per farsi rimborsare crediti e materiali
        // — mentre la radio annunciava che tutti ripartivano da zero.
        Database::run('UPDATE players SET components = 0, crystals = 0, salvage = 0');
        if (!$wipeCorps) {
            Database::run('UPDATE corporations SET treasury = 0');
        }
        $pdo->exec('TRUNCATE TABLE craft_jobs');
        $pdo->exec('TRUNCATE TABLE player_reputation');

        // Moduli e ufficiali restano, come progresso del comandante. I moduli
        // tornano in inventario (guasti compresi): la nave ridiventa lo scafo
        // iniziale e quelli montati ne sforerebbero gli slot. Gli ufficiali oltre
        // i posti del nuovo scafo vanno in panchina.
        Database::run(
            "INSERT INTO player_items (player_id, item_key, rolled, broken_at, source)
             SELECT s.player_id, sm.item_key, sm.rolled, sm.broken_at, 'shop'
               FROM ship_modules sm JOIN ships s ON s.id = sm.ship_id"
        );
        Database::run('DELETE FROM ship_modules');
        foreach (Database::all('SELECT id FROM players') as $pl) {
            Crew::adattaAlloScafo((int) $pl['id']);
        }
        GameConfig::set('combat.bounty_mult', '1');
        GameConfig::forget();

        Admin::audit($actorUserId, 'season.close', [
            'closed' => (int) $season['number'], 'opened' => $nextNum,
            'winner' => $winner, 'regen_universe' => $regenUniverse,
        ]);

        return ['ok' => true, 'number' => $nextNum, 'snapshot' => $pos, 'universe' => $u];
    }
}
