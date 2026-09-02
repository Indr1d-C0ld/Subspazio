<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 9 — Codex: diario delle scoperte. Le voci si sbloccano scansionando
 * feature, risolvendo anomalie, spingendosi nel profondo.
 */
final class Codex
{
    public static function unlock(int $playerId, string $key): bool
    {
        try {
            $n = Database::run(
                'INSERT IGNORE INTO player_codex (player_id, entry_key) VALUES (?, ?)',
                [$playerId, $key]
            )->rowCount();
            return $n > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> tutte le voci, con flag unlocked */
    public static function forPlayer(int $playerId): array
    {
        return Database::all(
            'SELECT c.ckey, c.title, c.category, c.body, c.sort_order,
                    pc.unlocked_at IS NOT NULL AS unlocked
             FROM codex_entries c
             LEFT JOIN player_codex pc ON pc.entry_key = c.ckey AND pc.player_id = ?
             ORDER BY c.sort_order, c.ckey',
            [$playerId]
        );
    }

    public static function counts(int $playerId): array
    {
        $r = Database::first(
            'SELECT (SELECT COUNT(*) FROM codex_entries) tot,
                    (SELECT COUNT(*) FROM player_codex WHERE player_id = ?) got',
            [$playerId]
        );
        return ['got' => (int) ($r['got'] ?? 0), 'tot' => (int) ($r['tot'] ?? 0)];
    }
}
