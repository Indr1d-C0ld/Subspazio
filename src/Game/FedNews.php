<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * FedNews / Frontier Broadcast (slice C3). Bollettino quotidiano composto sul
 * tick dallo stato reale del gioco e pubblicato nella Radio (canale 'fedcomm'),
 * firmato da un conduttore NPC che ruota. Archivio nella tabella `fednews`.
 */
final class FedNews
{
    /** @var list<string> conduttori che si alternano (tributo FNN / ME) */
    private const ANCHORS = [
        'Kesh Varo · Notiziario della Federazione',
        'Dax Oduya · Frontier Broadcast',
        'Lyra Venn · Rete Comm della Flotta',
    ];

    public static function enabled(): bool
    {
        return GameConfig::bool('fednews.enabled', true);
    }

    public static function intervalHours(): int
    {
        return max(1, GameConfig::int('fednews.interval_hours', 24));
    }

    /**
     * Chiamato dal tick: pubblica un bollettino se è passato l'intervallo.
     * @return array{posted:bool, headlines?:int}
     */
    public static function tick(): array
    {
        if (!self::enabled()) {
            return ['posted' => false];
        }

        $intervalSec = self::intervalHours() * 3600;

        // guardia 1: ultima riga d'archivio
        $last = Database::first("SELECT created_at FROM fednews ORDER BY id DESC LIMIT 1");
        if ($last !== null && (time() - strtotime((string) $last['created_at'])) < $intervalSec) {
            return ['posted' => false];
        }

        // guardia 2: ultimo bollettino andato in onda sulla Radio. Sopravvive a
        // un troncamento di `fednews` (es. durante un e2e che corre insieme al
        // cron) ed evita così il doppio invio ravvicinato.
        $lastAir = Database::first(
            "SELECT created_at FROM messages
             WHERE channel = 'fedcomm' AND body LIKE 'NOTIZIARIO DELLA FEDERAZIONE%'
             ORDER BY id DESC LIMIT 1"
        );
        if ($lastAir !== null && (time() - strtotime((string) $lastAir['created_at'])) < $intervalSec) {
            return ['posted' => false];
        }

        $headlines = self::compose();
        $anchor = self::ANCHORS[array_rand(self::ANCHORS)];
        $body = implode("\n", array_map(static fn ($h) => '• ' . $h, $headlines));

        Database::run(
            "INSERT INTO fednews (anchor, headlines, body) VALUES (?, ?, ?)",
            [$anchor, json_encode($headlines, JSON_UNESCAPED_UNICODE), $body]
        );
        Database::run(
            "INSERT INTO messages (channel, from_name, body) VALUES ('fedcomm', ?, ?)",
            [$anchor, "NOTIZIARIO DELLA FEDERAZIONE\n\n" . $body]
        );
        try {
            Live::global('fedcomm', $anchor, $headlines[0] ?? 'Nuovo bollettino.');
        } catch (\Throwable) {
        }

        return ['posted' => true, 'headlines' => count($headlines)];
    }

    /**
     * L'ultimo bollettino, per il teaser di plancia. `null` se non c'è o è più
     * vecchio del doppio dell'intervallo (per non lasciarlo in eterno).
     * @return array{anchor:string, headlines:list<string>, created_at:string}|null
     */
    public static function latest(): ?array
    {
        $r = Database::first("SELECT anchor, headlines, created_at FROM fednews ORDER BY id DESC LIMIT 1");
        if ($r === null) {
            return null;
        }
        if ((time() - strtotime((string) $r['created_at'])) > self::intervalHours() * 3600 * 2) {
            return null;
        }
        return [
            'anchor'     => (string) $r['anchor'],
            'headlines'  => json_decode((string) $r['headlines'], true) ?: [],
            'created_at' => (string) $r['created_at'],
        ];
    }

    // --- composizione ---------------------------------------------

    /** @return list<string> */
    private static function compose(): array
    {
        $h = [];
        $hrs = self::intervalHours();

        // 1) mercato: evento attivo più recente
        $ev = Database::first(
            "SELECT title, body FROM events
             WHERE reverted = 0 AND (ends_at IS NULL OR ends_at > NOW())
             ORDER BY id DESC LIMIT 1"
        );
        if ($ev !== null) {
            $h[] = trim((string) $ev['title']) . ': ' . rtrim(trim((string) $ev['body']), '.') . '.';
        }

        // 2) cronaca di frontiera: kill PvP recente
        $kill = Database::first(
            "SELECT a.handle AS att, d.handle AS def, c.sector_id
             FROM combat_log c
             JOIN players a ON a.id = c.attacker_player_id
             JOIN players d ON d.id = c.defender_player_id
             WHERE c.kind = 'ship' AND c.outcome = 'def_destroyed'
               AND c.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY c.id DESC LIMIT 1",
            [$hrs]
        );
        if ($kill !== null) {
            $h[] = "Cronaca di frontiera: {$kill['att']} ha avuto la meglio su {$kill['def']} nel settore " . (int) $kill['sector_id'] . '.';
        }

        // 3) frontiera: nuova colonia
        $col = Database::first(
            "SELECT pl.name, pl.sector_id, p.handle
             FROM planets pl LEFT JOIN players p ON p.id = pl.owner_player_id
             WHERE pl.destroyed = 0 AND pl.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY pl.id DESC LIMIT 1",
            [$hrs]
        );
        if ($col !== null) {
            $who = !empty($col['handle']) ? "da {$col['handle']} " : '';
            $h[] = "Nuovo avamposto coloniale {$who}nel settore " . (int) $col['sector_id'] . " ({$col['name']}).";
        }

        // 4) classifica: comandante di vertice
        $top = Database::first("SELECT handle, rating FROM players WHERE rating > 0 ORDER BY rating DESC, experience DESC LIMIT 1");
        if ($top !== null) {
            $h[] = "In vetta alla classifica dei comandanti: {$top['handle']} (rating " . number_format((int) $top['rating'], 0, ',', '.') . ').';
        }

        // 5) traffico registrazioni
        $newCmd = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM players WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$hrs]
        )['n'] ?? 0);
        if ($newCmd > 0) {
            $h[] = $newCmd === 1
                ? 'Un nuovo comandante ha lasciato lo StarDock nelle ultime ore.'
                : "{$newCmd} nuovi comandanti hanno lasciato lo StarDock nelle ultime ore.";
        }

        if ($h === []) {
            $h[] = "Nessun evento di rilievo nelle ultime {$hrs} ore. Rotte sgombre, mercati stabili.";
        }
        $h[] = 'Bollettino di servizio: la protezione novizio resta attiva 48 ore dopo la registrazione. Buona rotta, comandanti.';

        return $h;
    }
}
