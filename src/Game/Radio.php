<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;
use App\Core\RateLimiter;

/**
 * Radio subspaziale e messaggistica: broadcast di galassia (radio),
 * canale Federazione, canale corporazione, messaggi privati, hail di settore.
 */
final class Radio
{
    private const BROADCAST = ['radio', 'fedcomm', 'corp', 'system'];

    /**
     * @param array<string,mixed> $from
     * @return array{ok:bool, error?:string}
     */
    public static function send(array $from, string $channel, string $body, string $target = ''): array
    {
        $body = trim($body);
        $max = GameConfig::int('radio.body_max', 480);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Messaggio vuoto.'];
        }
        $body = mb_substr($body, 0, $max);

        if (!RateLimiter::hit('radio:' . $from['id'], GameConfig::int('radio.msg_max_per_min', 6), 60)) {
            return ['ok' => false, 'error' => 'Stai trasmettendo troppo in fretta.'];
        }

        $toPlayer = null;
        $toCorp = null;
        $sectorId = null;

        switch ($channel) {
            case 'radio':
                break;
            case 'fedcomm':
                $sec = Universe::sector((int) $from['sector_id']);
                if (!(bool) $sec['is_fedspace']) {
                    return ['ok' => false, 'error' => 'Il canale Federazione si usa solo in Fedspace.'];
                }
                break;
            case 'corp':
                $toCorp = Corp::corpIdOf((int) $from['id']);
                if ($toCorp === null) {
                    return ['ok' => false, 'error' => 'Non sei in una corporazione.'];
                }
                break;
            case 'hail':
                $sectorId = (int) $from['sector_id'];
                break;
            case 'private':
                $t = Database::first('SELECT id FROM players WHERE handle = ?', [trim($target)]);
                if ($t === null) {
                    return ['ok' => false, 'error' => "Nessun comandante di nome \"{$target}\"."];
                }
                $toPlayer = (int) $t['id'];
                break;
            default:
                return ['ok' => false, 'error' => 'Canale sconosciuto.'];
        }

        Database::run(
            'INSERT INTO messages (channel, from_player_id, from_name, to_player_id, to_corp_id, sector_id, body)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$channel, $from['id'], $from['handle'], $toPlayer, $toCorp, $sectorId, $body]
        );

        $who = (string) $from['handle'];
        match ($channel) {
            'private' => Live::alert((int) $toPlayer, 'dm', "Messaggio da {$who}", $body, '/gioco/radio'),
            'corp'    => Live::corp($toCorp, 'radio', "Corp — {$who}", $body),
            'hail'    => Live::sector((int) $sectorId, 'radio', "Hail — {$who}", $body),
            default   => Live::global('radio', strtoupper($channel) . " — {$who}", $body),
        };
        return ['ok' => true];
    }

    /** Messaggio di sistema (eventi, NPC). */
    public static function system(string $body, ?int $sectorId = null): void
    {
        $body = mb_substr($body, 0, 480);
        Database::run(
            'INSERT INTO messages (channel, from_name, sector_id, body) VALUES (?, ?, ?, ?)',
            [$sectorId === null ? 'system' : 'hail', 'Sistema', $sectorId, $body]
        );
        if ($sectorId === null) {
            Live::global('system', 'Sistema', $body);
        } else {
            Live::sector($sectorId, 'system', 'Sistema', $body);
        }
    }

    /**
     * @param array<string,mixed> $player
     * @return list<array<string,mixed>>
     */
    public static function inbox(array $player, int $limit = 60): array
    {
        $pid = (int) $player['id'];
        $corpId = Corp::corpIdOf($pid) ?? 0;
        $sectorId = (int) $player['sector_id'];

        $rows = Database::all(
            "SELECT m.*, p.handle AS from_handle
             FROM messages m LEFT JOIN players p ON p.id = m.from_player_id
             WHERE m.channel IN ('radio','fedcomm','system')
                OR (m.channel = 'corp' AND m.to_corp_id = ?)
                OR (m.channel = 'private' AND (m.to_player_id = ? OR m.from_player_id = ?))
                OR (m.channel = 'hail' AND m.sector_id = ?)
             ORDER BY m.id DESC
             LIMIT ?",
            [$corpId, $pid, $pid, $sectorId, $limit]
        );

        return array_map(static fn ($m) => [
            'id'      => (int) $m['id'],
            'channel' => $m['channel'],
            'from'    => $m['from_handle'] ?? $m['from_name'] ?? 'Ignoto',
            'mine'    => (int) ($m['from_player_id'] ?? 0) === $pid,
            'to'      => $m['channel'] === 'private'
                ? ((int) $m['to_player_id'] === $pid ? 'a te' : 'da te')
                : null,
            'body'    => $m['body'],
            'at'      => $m['created_at'],
        ], $rows);
    }

    /** @param array<string,mixed> $player */
    public static function unread(array $player): int
    {
        $pid = (int) $player['id'];
        $corpId = Corp::corpIdOf($pid) ?? 0;

        $state = [];
        foreach (Database::all('SELECT channel, last_read_id FROM msg_state WHERE player_id = ?', [$pid]) as $s) {
            $state[$s['channel']] = (int) $s['last_read_id'];
        }
        $bcMax = max(0, ...array_map(static fn ($c) => $state[$c] ?? 0, self::BROADCAST));

        $n = (int) (Database::first(
            "SELECT COUNT(*) c FROM messages
             WHERE (channel IN ('radio','fedcomm','system') AND id > ?)
                OR (channel = 'corp' AND to_corp_id = ? AND id > ?)",
            [$state['radio'] ?? 0, $corpId, $state['corp'] ?? 0]
        )['c'] ?? 0);

        $n += (int) (Database::first(
            "SELECT COUNT(*) c FROM messages WHERE channel = 'private' AND to_player_id = ? AND read_at IS NULL",
            [$pid]
        )['c'] ?? 0);

        unset($bcMax);
        return $n;
    }

    /** @param array<string,mixed> $player */
    public static function markRead(array $player): void
    {
        $pid = (int) $player['id'];
        $maxId = (int) (Database::first('SELECT COALESCE(MAX(id),0) m FROM messages')['m'] ?? 0);
        foreach (['radio', 'fedcomm', 'corp', 'system'] as $ch) {
            Database::run(
                'INSERT INTO msg_state (player_id, channel, last_read_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_read_id = VALUES(last_read_id)',
                [$pid, $ch, $maxId]
            );
        }
        Database::run(
            "UPDATE messages SET read_at = NOW() WHERE channel = 'private' AND to_player_id = ? AND read_at IS NULL",
            [$pid]
        );
    }
}
