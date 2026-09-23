<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Operazioni del pannello di controllo del gioco (solo admin).
 * Ogni azione lascia una traccia in audit_log.
 */
final class Admin
{
    public static function audit(int $actorUserId, string $action, array $meta = []): void
    {
        try {
            Database::run(
                'INSERT INTO audit_log (actor_user_id, action, target_type, meta) VALUES (?, ?, ?, ?)',
                [$actorUserId, $action, 'game', $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE)]
            );
        } catch (\Throwable) {
        }
    }

    // --- statistiche ------------------------------------------------

    /** @return array<string,mixed> */
    public static function stats(): array
    {
        $one = static fn (string $sql, array $p = []) => (int) (Database::first($sql, $p)['c'] ?? 0);

        return [
            'players'        => $one('SELECT COUNT(*) c FROM players'),
            'online'         => $one("SELECT COUNT(*) c FROM players WHERE last_seen_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"),
            'users_pending'  => $one("SELECT COUNT(*) c FROM users WHERE status = 'pending'"),
            'users_active'   => $one("SELECT COUNT(*) c FROM users WHERE status = 'active'"),
            'corps'          => $one('SELECT COUNT(*) c FROM corporations'),
            'planets'        => $one('SELECT COUNT(*) c FROM planets WHERE destroyed = 0'),
            'trades_today'   => $one('SELECT COUNT(*) c FROM trade_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'combats_today'  => $one('SELECT COUNT(*) c FROM combat_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'sectors'        => $one('SELECT COUNT(*) c FROM sectors'),
            'ports'          => $one('SELECT COUNT(*) c FROM ports'),
            'npc'            => Database::all('SELECT kind, COUNT(*) c FROM npcs GROUP BY kind'),
            'events'         => Database::all("SELECT id, kind, title, ends_at FROM events WHERE ends_at IS NULL OR ends_at > NOW() ORDER BY id DESC"),
            'richest'        => Database::first('SELECT handle, credits FROM players ORDER BY credits DESC LIMIT 1'),
            'top_rating'     => Database::first('SELECT handle, rating FROM players ORDER BY rating DESC LIMIT 1'),
            'universe_at'    => GameConfig::str('universe.generated_at', '(mai)'),
            'tick'           => TickHealth::stato(),
        ];
    }

    // --- configurazione -------------------------------------------

    /** @return array<string, list<array<string,mixed>>> raggruppata per prefisso */
    public static function configGrouped(): array
    {
        $out = [];
        foreach (Database::all('SELECT ckey, cvalue, ctype, default_value FROM game_config ORDER BY ckey') as $r) {
            $group = explode('.', (string) $r['ckey'])[0];
            $out[$group][] = $r;
        }
        ksort($out);
        return $out;
    }

    public static function setConfig(int $actor, string $key, string $value): array
    {
        if (Database::first('SELECT 1 x FROM game_config WHERE ckey = ?', [$key]) === null) {
            return ['ok' => false, 'error' => 'Chiave inesistente.'];
        }
        Database::run('UPDATE game_config SET cvalue = ?, updated_by = ? WHERE ckey = ?', [$value, $actor, $key]);
        GameConfig::forget();
        self::audit($actor, 'config.set', ['key' => $key, 'value' => $value]);
        return ['ok' => true];
    }

    public static function resetConfig(int $actor, string $key): array
    {
        $row = Database::first('SELECT default_value FROM game_config WHERE ckey = ?', [$key]);
        if ($row === null || $row['default_value'] === null) {
            return ['ok' => false, 'error' => 'Nessun valore di default registrato.'];
        }
        Database::run('UPDATE game_config SET cvalue = default_value, updated_by = ? WHERE ckey = ?', [$actor, $key]);
        GameConfig::forget();
        self::audit($actor, 'config.reset', ['key' => $key]);
        return ['ok' => true, 'value' => $row['default_value']];
    }

    // --- eventi / NPC --------------------------------------------

    public static function forceEvent(int $actor, string $kind): array
    {
        $res = Events::force($kind);
        self::audit($actor, 'event.force', ['kind' => $kind, 'result' => $res]);
        return $res === null ? ['ok' => false, 'error' => 'Tipo evento sconosciuto.'] : ['ok' => true, 'kind' => $res];
    }

    public static function endEvent(int $actor, int $id): array
    {
        Database::run('UPDATE events SET ends_at = NOW() WHERE id = ? AND reverted = 0', [$id]);
        Events::expireDue();
        self::audit($actor, 'event.end', ['id' => $id]);
        return ['ok' => true];
    }

    public static function spawnNpcs(int $actor, string $kind, int $n): array
    {
        if (!in_array($kind, ['ferrengi', 'pirate', 'trader'], true)) {
            return ['ok' => false, 'error' => 'Tipo NPC non valido.'];
        }
        $n = max(1, min(50, $n));
        for ($i = 0; $i < $n; $i++) {
            Npc::spawnOne($kind);
        }
        self::audit($actor, 'npc.spawn', ['kind' => $kind, 'n' => $n]);
        return ['ok' => true, 'spawned' => $n];
    }

    public static function purgeNpcs(int $actor, string $kind): array
    {
        $n = $kind === 'all'
            ? Database::run('DELETE FROM npcs')->rowCount()
            : Database::run('DELETE FROM npcs WHERE kind = ?', [$kind])->rowCount();
        self::audit($actor, 'npc.purge', ['kind' => $kind, 'removed' => $n]);
        return ['ok' => true, 'removed' => $n];
    }

    // --- universo -------------------------------------------------

    public static function bigBang(int $actor): array
    {
        $cfg = [
            'sectors'         => GameConfig::int('universe.sectors', 1000),
            'fedspace_max'    => GameConfig::int('universe.fedspace_max', 10),
            'stardock_sector' => GameConfig::int('universe.stardock_sector', 1),
            'warp_density'    => GameConfig::float('universe.warp_density', 3.2),
        ];
        Radio::system('BIG BANG — la galassia collassa e si riforma. Tutti i comandanti sono riportati allo StarDock.');
        // Le consegne aperte puntano a settori che stanno per cambiare: si
        // annullano restituendo la cauzione, una volta sola.
        foreach (Database::all("SELECT id, issuer_player_id, reward FROM contracts WHERE kind = 'delivery' AND status = 'open'") as $c) {
            if (Database::run("UPDATE contracts SET status = 'cancelled' WHERE id = ? AND status = 'open'", [(int) $c['id']])->rowCount() > 0) {
                Wallet::credit((int) $c['issuer_player_id'], ['credits' => (int) $c['reward']]);
            }
        }
        Database::run("UPDATE player_encounters SET status = 'expired' WHERE status = 'pending'");
        $u = (new UniverseGenerator($cfg))->generate(true);
        $p = PortGenerator::generate(true);
        Database::run('DELETE FROM npcs');
        Database::run('DELETE FROM live_events');
        GameConfig::forget();
        self::audit($actor, 'universe.bigbang', ['universe' => $u, 'ports' => $p]);
        Radio::system('BIG BANG completato: ' . $u['sectors'] . ' settori, ' . $p['ports'] . ' porti.');
        return ['ok' => true, 'universe' => $u, 'ports' => $p];
    }

    // --- moderazione giocatori --------------------------------

    /** @return list<array<string,mixed>> */
    public static function players(int $limit = 100): array
    {
        return Database::all(
            "SELECT p.id, p.handle, p.credits, p.turns, p.sector_id, p.alignment, p.kills, p.deaths,
                    p.rating, p.last_seen_at, p.color, p.crest, p.motto, p.protected_until,
                    u.id AS user_id, u.username, u.status, u.role
             FROM players p JOIN users u ON u.id = p.user_id
             ORDER BY p.last_seen_at IS NULL, p.last_seen_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    public static function kick(int $actor, int $userId): array
    {
        Database::run('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?', [$userId]);
        self::audit($actor, 'player.kick', ['user_id' => $userId]);
        return ['ok' => true];
    }

    public static function setStatus(int $actor, int $userId, string $status): array
    {
        if (!in_array($status, ['active', 'suspended', 'banned'], true)) {
            return ['ok' => false, 'error' => 'Stato non valido.'];
        }
        $target = Database::first('SELECT role FROM users WHERE id = ?', [$userId]);
        if ($target === null) {
            return ['ok' => false, 'error' => 'Utente inesistente.'];
        }
        if ($target['role'] === 'admin') {
            return ['ok' => false, 'error' => 'Non puoi cambiare lo stato di un admin.'];
        }
        Database::run('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
        if ($status !== 'active') {
            Database::run('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?', [$userId]);
        }
        self::audit($actor, 'player.status', ['user_id' => $userId, 'status' => $status]);
        return ['ok' => true];
    }

    public static function teleport(int $actor, int $playerId, int $sectorId): array
    {
        if (Database::first('SELECT 1 x FROM sectors WHERE id = ?', [$sectorId]) === null) {
            return ['ok' => false, 'error' => 'Settore inesistente.'];
        }
        Database::run('UPDATE players SET sector_id = ? WHERE id = ?', [$sectorId, $playerId]);
        Database::run('UPDATE ships SET sector_id = ? WHERE player_id = ?', [$sectorId, $playerId]);
        Database::run('INSERT IGNORE INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)', [$playerId, $sectorId]);
        self::audit($actor, 'player.teleport', ['player_id' => $playerId, 'sector' => $sectorId]);
        return ['ok' => true];
    }

    public static function adjust(int $actor, int $playerId, int $credits, ?int $turns): array
    {
        if ($turns !== null) {
            Database::run('UPDATE players SET credits = GREATEST(0, credits + ?), turns = ? WHERE id = ?', [$credits, max(0, $turns), $playerId]);
        } else {
            Database::run('UPDATE players SET credits = GREATEST(0, credits + ?) WHERE id = ?', [$credits, $playerId]);
        }
        self::audit($actor, 'player.adjust', ['player_id' => $playerId, 'credits_delta' => $credits, 'turns' => $turns]);
        return ['ok' => true];
    }

    public static function resetPlayer(int $actor, int $playerId): array
    {
        $row = Database::first('SELECT handle FROM players WHERE id = ?', [$playerId]);

        // Cancellare il comandante e basta lasciava dietro di se' una
        // corporazione con un CEO inesistente (tesoro e alleanze congelati) e
        // pianeti di nessuno — che continuavano a produrre, saccheggiabili da
        // chiunque, occupando per sempre un posto nel settore.
        foreach (Database::all('SELECT id FROM corporations WHERE ceo_player_id = ?', [$playerId]) as $c) {
            $erede = Database::first(
                'SELECT m.player_id FROM corp_members m JOIN players p ON p.id = m.player_id
                  WHERE m.corp_id = ? AND m.player_id <> ? ORDER BY p.rating DESC, p.id ASC LIMIT 1',
                [(int) $c['id'], $playerId]
            );
            if ($erede !== null) {
                Database::run('UPDATE corporations SET ceo_player_id = ? WHERE id = ?', [(int) $erede['player_id'], (int) $c['id']]);
                Database::run("UPDATE corp_members SET role = 'ceo' WHERE corp_id = ? AND player_id = ?", [(int) $c['id'], (int) $erede['player_id']]);
            } else {
                Database::run('DELETE FROM corporations WHERE id = ?', [(int) $c['id']]);
            }
        }
        // i pianeti di corporazione restano alla corporazione; gli altri si spengono
        Database::run('UPDATE planets SET destroyed = 1 WHERE owner_player_id = ? AND corp_id IS NULL', [$playerId]);
        Database::run('DELETE FROM craft_jobs WHERE player_id = ?', [$playerId]);
        Database::run('DELETE FROM players WHERE id = ?', [$playerId]);
        self::audit($actor, 'player.reset', ['player_id' => $playerId, 'handle' => $row['handle'] ?? null]);
        return ['ok' => true];
    }

    /**
     * Modifica del profilo di un giocatore da parte dell'admin: handle,
     * identità (colore/stemma/motto), allineamento e protezione novizio.
     * @param array<string,mixed> $in campi: handle, color, crest, motto, alignment, protected_until
     */
    public static function editProfile(int $actor, int $playerId, array $in): array
    {
        $player = Database::first('SELECT * FROM players WHERE id = ?', [$playerId]);
        if ($player === null) {
            return ['ok' => false, 'error' => 'Giocatore inesistente.'];
        }

        $handle = trim((string) ($in['handle'] ?? ''));
        $color  = (string) ($in['color'] ?? '');
        $crest  = (string) ($in['crest'] ?? '');
        $motto  = trim((string) ($in['motto'] ?? ''));
        $alignment = (int) ($in['alignment'] ?? $player['alignment']);
        $protectedRaw = trim((string) ($in['protected_until'] ?? ''));

        $errors = [];
        if ($handle === '' || mb_strlen($handle) > 24 || !preg_match('/^[A-Za-z0-9_ -]+$/', $handle)) {
            $errors[] = 'Handle non valido (max 24 caratteri: lettere, numeri, spazio, - o _).';
        } elseif (Database::first('SELECT 1 x FROM players WHERE handle = ? AND id <> ?', [$handle, $playerId]) !== null) {
            $errors[] = 'Handle già in uso.';
        }
        if ($color !== '' && !isset(Identity::PALETTE[$color])) {
            $errors[] = 'Colore non valido.';
        }
        if ($crest !== '' && !Identity::validCrest($crest)) {
            $errors[] = 'Stemma non valido.';
        }
        if (mb_strlen($motto) > 80) {
            $errors[] = 'Il motto non può superare 80 caratteri.';
        }
        $protectedUntil = null;
        if ($protectedRaw !== '') {
            $ts = strtotime($protectedRaw);
            if ($ts === false) {
                $errors[] = 'Data di protezione non valida.';
            } else {
                $protectedUntil = date('Y-m-d H:i:s', $ts);
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'error' => implode(' ', $errors)];
        }

        Database::run(
            'UPDATE players SET handle = ?, color = ?, crest = ?, motto = ?, alignment = ?, protected_until = ? WHERE id = ?',
            [$handle, $color ?: null, $crest ?: null, $motto !== '' ? mb_substr($motto, 0, 80) : null, $alignment, $protectedUntil, $playerId]
        );
        self::audit($actor, 'player.profile', [
            'player_id' => $playerId,
            'before' => [
                'handle' => $player['handle'], 'color' => $player['color'], 'crest' => $player['crest'],
                'motto' => $player['motto'], 'alignment' => (int) $player['alignment'], 'protected_until' => $player['protected_until'],
            ],
            'after' => [
                'handle' => $handle, 'color' => $color ?: null, 'crest' => $crest ?: null,
                'motto' => $motto ?: null, 'alignment' => $alignment, 'protected_until' => $protectedUntil,
            ],
        ]);
        return ['ok' => true];
    }
}
