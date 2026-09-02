<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Registro dei combattimenti e replay round-per-round dal combat_log.
 */
final class BattleLog
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function forPlayer(int $playerId, int $limit = 40): array
    {
        $rows = Database::all(
            "SELECT cl.*, a.handle AS att_handle, d.handle AS def_handle, po.name AS port_name
             FROM combat_log cl
             LEFT JOIN players a ON a.id = cl.attacker_player_id
             LEFT JOIN players d ON d.id = cl.defender_player_id
             LEFT JOIN ports po ON po.id = cl.defender_port_id
             WHERE cl.attacker_player_id = ? OR cl.defender_player_id = ?
             ORDER BY cl.id DESC
             LIMIT ?",
            [$playerId, $playerId, $limit]
        );

        return array_map(static function ($r) use ($playerId) {
            $role = (int) $r['attacker_player_id'] === $playerId ? 'attaccante'
                : ((int) $r['defender_player_id'] === $playerId ? 'difensore' : 'osservatore');
            return [
                'id'        => (int) $r['id'],
                'kind'      => $r['kind'],
                'sector'    => (int) $r['sector_id'],
                'role'      => $role,
                'opponent'  => self::opponentLabel($r, $playerId),
                'rounds'    => (int) $r['rounds'],
                'att_lost'  => (int) $r['att_fighters_lost'],
                'def_lost'  => (int) $r['def_fighters_lost'],
                'outcome'   => self::outcomeLabel((string) $r['outcome'], $role),
                'outcome_raw' => $r['outcome'],
                'loot'      => (int) $r['loot_credits'],
                'at'        => $r['created_at'],
                'replayable' => self::hasTrace($r['detail']),
            ];
        }, $rows);
    }

    /** @return array<string,mixed>|null */
    public static function get(int $id, int $playerId, bool $isAdmin = false): ?array
    {
        $r = Database::first(
            "SELECT cl.*, a.handle AS att_handle, d.handle AS def_handle, po.name AS port_name, s.name AS sector_name
             FROM combat_log cl
             LEFT JOIN players a ON a.id = cl.attacker_player_id
             LEFT JOIN players d ON d.id = cl.defender_player_id
             LEFT JOIN ports po ON po.id = cl.defender_port_id
             LEFT JOIN sectors s ON s.id = cl.sector_id
             WHERE cl.id = ?",
            [$id]
        );
        if ($r === null) {
            return null;
        }
        if (!$isAdmin && (int) $r['attacker_player_id'] !== $playerId && (int) $r['defender_player_id'] !== $playerId) {
            return null;
        }

        $detail = $r['detail'] ? json_decode((string) $r['detail'], true) : [];
        $duel = $detail['duel'] ?? (isset($detail['trace']) ? $detail : null);
        $trace = $duel['trace'] ?? null;

        return [
            'id'       => (int) $r['id'],
            'kind'     => $r['kind'],
            'sector'   => (int) $r['sector_id'],
            'sector_name' => $r['sector_name'],
            'attacker' => $r['att_handle'] ?? 'Ignoto',
            'defender' => $r['def_handle'] ?? $r['port_name'] ?? ($r['kind'] === 'planet' ? 'Pianeta' : ($r['kind'] === 'npc' ? 'NPC' : 'Bersaglio')),
            'rounds'   => (int) $r['rounds'],
            'outcome'  => $r['outcome'],
            'loot'     => (int) $r['loot_credits'],
            'at'       => $r['created_at'],
            'stolen'   => $detail['stolen'] ?? [],
            'drops'    => is_array($detail['drops'] ?? null) ? $detail['drops'] : ['items' => [], 'salvage' => 0],
            'trace'    => is_array($trace) ? $trace : [],
            'att_ftr0' => $duel['att_ftr0'] ?? null,
            'def_ftr0' => $duel['def_ftr0'] ?? null,
        ];
    }

    private static function hasTrace(mixed $detail): bool
    {
        if (!is_string($detail) || $detail === '') {
            return false;
        }
        $d = json_decode($detail, true);
        return isset($d['trace']) || isset($d['duel']['trace']);
    }

    private static function opponentLabel(array $r, int $playerId): string
    {
        if ($r['kind'] === 'port') {
            return $r['port_name'] ?? 'Porto';
        }
        if ($r['kind'] === 'planet') {
            return 'Pianeta';
        }
        if ($r['kind'] === 'npc') {
            $d = $r['detail'] ? json_decode((string) $r['detail'], true) : [];
            return $d['npc'] ?? 'NPC';
        }
        return (int) $r['attacker_player_id'] === $playerId
            ? ($r['def_handle'] ?? 'Ignoto')
            : ($r['att_handle'] ?? 'Ignoto');
    }

    private static function outcomeLabel(string $outcome, string $role): string
    {
        return match ($outcome) {
            'def_destroyed' => $role === 'attaccante' ? 'vittoria' : 'distrutto',
            'att_destroyed' => $role === 'attaccante' ? 'distrutto' : 'vittoria',
            'repelled'      => $role === 'attaccante' ? 'respinto' : 'difesa riuscita',
            'att_win'       => 'in vantaggio',
            'def_win'       => 'respinto',
            'passed'        => 'passato',
            default         => $outcome,
        };
    }
}
