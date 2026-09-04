<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Giornale di bordo: registro incidenti persistente per giocatore.
 *
 * Ogni voce e' un rapporto coerente con l'ambientazione su qualcosa
 * *capitato* alla nave o agli asset del giocatore (fuori da un'azione
 * esplicita): scontri all'ingresso di un settore, hazard, NPC, spostamenti
 * di reputazione, pianeti colpiti, esiti di contratti.
 *
 * write() non deve mai far fallire l'azione di gioco che la chiama.
 */
final class ShipLog
{
    /** kind -> etichetta di canale in-fiction (prefisso della voce). */
    private const CHANNELS = [
        'destroyed' => 'EMERGENZA',
        'combat'    => 'ALLERTA DI COMBATTIMENTO',
        'npc'       => 'ALLERTA DI COMBATTIMENTO',
        'hazard'    => 'RAPPORTO DI BORDO',
        'travel'    => 'REGISTRO DI ROTTA',
        'faction'   => 'COMUNICAZIONE DIPLOMATICA',
        'planet'    => 'RAPPORTO COLONIALE',
        'contract'  => 'RETE CONTRATTI',
        'system'    => 'COMPUTER DI BORDO',
    ];

    public static function channel(string $kind): string
    {
        return self::CHANNELS[$kind] ?? self::CHANNELS['system'];
    }

    /**
     * Registra una voce di giornale.
     *
     * @param array<string,mixed> $data extra strutturati (link, id, …)
     */
    public static function write(
        int $playerId,
        string $kind,
        string $severity,
        string $title,
        string $body,
        ?int $sectorId = null,
        array $data = []
    ): void {
        if ($playerId <= 0) {
            return;
        }
        $sev = in_array($severity, ['info', 'warning', 'alert'], true) ? $severity : 'info';
        try {
            Database::run(
                'INSERT INTO ship_log (player_id, kind, severity, title, body, sector_id, data)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $playerId,
                    mb_substr($kind, 0, 24),
                    $sev,
                    mb_substr(trim($title), 0, 140),
                    mb_substr(trim($body), 0, 4000),
                    $sectorId !== null && $sectorId > 0 ? $sectorId : null,
                    $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (\Throwable) {
            // il giornale non deve mai far fallire un'azione di gioco
        }
    }

    /**
     * Compone una voce da una lista di righe-evento (quelle prodotte da
     * Combat::onEnterSector / Navigation::move) e la registra.
     *
     * @param list<string> $lines
     */
    public static function fromEntryEvents(int $playerId, int $sectorId, array $lines, bool $destroyed): void
    {
        $lines = array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
        if ($lines === []) {
            return;
        }
        $joined = strtolower(implode(' ', $lines));
        $combat = $destroyed
            || str_contains($joined, 'danni')
            || str_contains($joined, 'scontro')
            || str_contains($joined, 'ingaggio')
            || str_contains($joined, 'fuoco')
            || str_contains($joined, 'attacc');

        if ($destroyed) {
            $kind = 'destroyed';
            $sev = 'alert';
            $title = "Nave perduta nel settore {$sectorId}";
        } elseif ($combat) {
            $kind = 'combat';
            $sev = 'warning';
            $title = "Contatto ostile nel settore {$sectorId}";
        } else {
            $kind = 'travel';
            $sev = 'info';
            $title = "Ingresso nel settore {$sectorId}";
        }

        self::write($playerId, $kind, $sev, $title, implode("\n", $lines), $sectorId);
    }

    /** @return list<array<string,mixed>> voci piu' recenti, dalla piu' nuova. */
    public static function recent(int $playerId, int $limit = 6): array
    {
        try {
            return Database::all(
                'SELECT id, kind, severity, title, body, sector_id, read_at, created_at
                 FROM ship_log WHERE player_id = ? ORDER BY id DESC LIMIT ?',
                [$playerId, max(1, $limit)]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Pagina di storico. $beforeId = 0 -> dalla piu' recente.
     *
     * @return list<array<string,mixed>>
     */
    public static function page(int $playerId, int $limit = 60, int $beforeId = 0): array
    {
        try {
            if ($beforeId > 0) {
                return Database::all(
                    'SELECT id, kind, severity, title, body, sector_id, read_at, created_at
                     FROM ship_log WHERE player_id = ? AND id < ? ORDER BY id DESC LIMIT ?',
                    [$playerId, $beforeId, max(1, $limit)]
                );
            }
            return Database::all(
                'SELECT id, kind, severity, title, body, sector_id, read_at, created_at
                 FROM ship_log WHERE player_id = ? ORDER BY id DESC LIMIT ?',
                [$playerId, max(1, $limit)]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function unread(int $playerId): int
    {
        try {
            return (int) (Database::first(
                'SELECT COUNT(*) c FROM ship_log WHERE player_id = ? AND read_at IS NULL',
                [$playerId]
            )['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function markRead(int $playerId): void
    {
        try {
            Database::run(
                'UPDATE ship_log SET read_at = NOW() WHERE player_id = ? AND read_at IS NULL',
                [$playerId]
            );
        } catch (\Throwable) {
        }
    }

    /** Pota lo storico: tiene le ultime N voci per giocatore. Dal tick. */
    public static function gc(): int
    {
        $keep = max(20, GameConfig::int('shiplog.keep_per_player', 200));
        $deleted = 0;
        try {
            $rows = Database::all(
                'SELECT player_id, COUNT(*) c FROM ship_log GROUP BY player_id HAVING c > ?',
                [$keep]
            );
            foreach ($rows as $r) {
                $pid = (int) $r['player_id'];
                $cut = Database::first(
                    'SELECT id FROM ship_log WHERE player_id = ? ORDER BY id DESC LIMIT 1 OFFSET ?',
                    [$pid, $keep]
                );
                if ($cut !== null) {
                    $deleted += Database::run(
                        'DELETE FROM ship_log WHERE player_id = ? AND id <= ?',
                        [$pid, (int) $cut['id']]
                    )->rowCount();
                }
            }
        } catch (\Throwable) {
        }
        return $deleted;
    }
}
