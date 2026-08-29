<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Preferiti e note personali sui settori.
 */
final class SectorNotes
{
    /** @return array<string,mixed>|null */
    public static function get(int $playerId, int $sectorId): ?array
    {
        return Database::first(
            'SELECT sector_id, label, note, pinned FROM player_sector_notes WHERE player_id = ? AND sector_id = ?',
            [$playerId, $sectorId]
        );
    }

    /** @return list<array<string,mixed>> settori aggiunti ai preferiti */
    public static function pinned(int $playerId): array
    {
        return array_map(static fn ($r) => [
            'sector' => (int) $r['sector_id'],
            'label'  => $r['label'],
            'name'   => $r['name'],
        ], Database::all(
            'SELECT n.sector_id, n.label, s.name
             FROM player_sector_notes n JOIN sectors s ON s.id = n.sector_id
             WHERE n.player_id = ? AND n.pinned = 1
             ORDER BY n.label IS NULL, n.label, n.sector_id',
            [$playerId]
        ));
    }

    /** @return list<array<string,mixed>> tutte le note (per la pagina rotte) */
    public static function all(int $playerId): array
    {
        return Database::all(
            'SELECT n.sector_id, n.label, n.note, n.pinned, s.name
             FROM player_sector_notes n JOIN sectors s ON s.id = n.sector_id
             WHERE n.player_id = ?
             ORDER BY n.pinned DESC, n.updated_at DESC',
            [$playerId]
        );
    }

    public static function set(int $playerId, int $sectorId, string $label, string $note, bool $pinned): array
    {
        if (Database::first('SELECT 1 x FROM sectors WHERE id = ?', [$sectorId]) === null) {
            return ['ok' => false, 'error' => 'Settore inesistente.'];
        }
        $label = trim(mb_substr($label, 0, 32));
        $note = trim(mb_substr($note, 0, 255));

        if ($label === '' && $note === '' && !$pinned) {
            Database::run('DELETE FROM player_sector_notes WHERE player_id = ? AND sector_id = ?', [$playerId, $sectorId]);
            return ['ok' => true, 'removed' => true];
        }

        Database::run(
            'INSERT INTO player_sector_notes (player_id, sector_id, label, note, pinned) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), note = VALUES(note), pinned = VALUES(pinned)',
            [$playerId, $sectorId, $label ?: null, $note ?: null, $pinned ? 1 : 0]
        );
        return ['ok' => true];
    }

    public static function remove(int $playerId, int $sectorId): void
    {
        Database::run('DELETE FROM player_sector_notes WHERE player_id = ? AND sector_id = ?', [$playerId, $sectorId]);
    }
}
