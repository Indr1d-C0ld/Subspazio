<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Bus di eventi in tempo reale: le azioni di gioco depositano righe in
 * live_events; l'endpoint SSE (/api/stream) le recapita ai client il cui
 * "scope" combacia (globale, settore corrente, giocatore, corporazione).
 *
 * alert() aggiunge anche una notifica persistente (campanella) per gli
 * eventi che toccano gli asset del giocatore anche quando e' offline.
 */
final class Live
{
    private static bool $enabled = true;

    /** @param array<string,mixed> $payload */
    public static function emit(string $scope, int $scopeId, string $kind, ?string $title = null, ?string $body = null, array $payload = []): void
    {
        if (!self::$enabled) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO live_events (scope, scope_id, kind, title, body, payload) VALUES (?, ?, ?, ?, ?, ?)',
                [$scope, $scopeId, $kind, $title, $body, $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE)]
            );
        } catch (\Throwable) {
            // il realtime non deve mai far fallire un'azione di gioco
        }
    }

    public static function global(string $kind, ?string $title, ?string $body, array $payload = []): void
    {
        self::emit('global', 0, $kind, $title, $body, $payload);
    }

    public static function sector(int $sectorId, string $kind, ?string $title, ?string $body, array $payload = []): void
    {
        self::emit('sector', $sectorId, $kind, $title, $body, $payload);
    }

    public static function player(int $playerId, string $kind, ?string $title, ?string $body, array $payload = []): void
    {
        self::emit('player', $playerId, $kind, $title, $body, $payload);
    }

    public static function corp(?int $corpId, string $kind, ?string $title, ?string $body, array $payload = []): void
    {
        if ($corpId !== null && $corpId > 0) {
            self::emit('corp', $corpId, $kind, $title, $body, $payload);
        }
    }

    /** Notifica persistente + evento live per il giocatore. */
    public static function alert(int $playerId, string $kind, string $title, string $body, ?string $link = null): void
    {
        try {
            Database::run(
                'INSERT INTO alerts (player_id, kind, title, body, link) VALUES (?, ?, ?, ?, ?)',
                [$playerId, $kind, mb_substr($title, 0, 120), mb_substr($body, 0, 400), $link]
            );
        } catch (\Throwable) {
        }
        self::player($playerId, 'alert', $title, $body, ['link' => $link, 'alert_kind' => $kind]);
    }

    /**
     * Eventi recenti visibili al giocatore con id > $lastId.
     *
     * @param array<string,mixed> $player
     * @return list<array<string,mixed>>
     */
    public static function since(array $player, int $lastId, int $limit = 80): array
    {
        $pid = (int) $player['id'];
        $sectorId = (int) $player['sector_id'];
        $corpId = Corp::corpIdOf($pid) ?? 0;

        return Database::all(
            "SELECT id, scope, kind, title, body, payload, created_at
             FROM live_events
             WHERE id > ?
               AND ( scope = 'global'
                  OR (scope = 'sector' AND scope_id = ?)
                  OR (scope = 'player' AND scope_id = ?)
                  OR (scope = 'corp'   AND scope_id = ?) )
             ORDER BY id ASC
             LIMIT ?",
            [$lastId, $sectorId, $pid, $corpId, $limit]
        );
    }

    public static function lastId(): int
    {
        return (int) (Database::first('SELECT COALESCE(MAX(id),0) m FROM live_events')['m'] ?? 0);
    }

    // --- alert (campanella) ------------------------------------------

    /** @return list<array<string,mixed>> */
    public static function alerts(int $playerId, int $limit = 30): array
    {
        return Database::all(
            'SELECT id, kind, title, body, link, read_at, created_at FROM alerts WHERE player_id = ? ORDER BY id DESC LIMIT ?',
            [$playerId, $limit]
        );
    }

    public static function unreadAlerts(int $playerId): int
    {
        return (int) (Database::first('SELECT COUNT(*) c FROM alerts WHERE player_id = ? AND read_at IS NULL', [$playerId])['c'] ?? 0);
    }

    public static function markAlertsRead(int $playerId): void
    {
        Database::run('UPDATE alerts SET read_at = NOW() WHERE player_id = ? AND read_at IS NULL', [$playerId]);
    }

    /** Pulizia (dal tick). */
    public static function gc(): int
    {
        $min = GameConfig::int('live.retention_min', 60);
        $n = 0;
        try {
            $n = Database::run('DELETE FROM live_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$min])->rowCount();
            Database::run('DELETE FROM alerts WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 14 DAY)');
        } catch (\Throwable) {
        }
        return $n;
    }
}
