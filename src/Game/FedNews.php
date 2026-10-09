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
            "SELECT c.id, a.handle AS att, d.handle AS def, c.sector_id
             FROM combat_log c
             JOIN players a ON a.id = c.attacker_player_id
             JOIN players d ON d.id = c.defender_player_id
             WHERE c.kind = 'ship' AND c.outcome = 'def_destroyed'
               AND c.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY c.id DESC LIMIT 1",
            [$hrs]
        );

        // 2b) legge: l'ultima taglia riscossa e i ricercati. Le regole sulle
        // taglie sono cambiate il 09/10 (le paga il ricercato): il notiziario
        // e' il posto dove i comandanti le vedono applicate.
        $riscossa = Database::first(
            "SELECT c.id, c.sector_id, c.outcome, a.handle AS att, d.handle AS def,
                    CAST(JSON_VALUE(c.detail, '$.taglia') AS UNSIGNED) AS taglia
             FROM combat_log c
             JOIN players a ON a.id = c.attacker_player_id
             JOIN players d ON d.id = c.defender_player_id
             WHERE c.kind = 'ship' AND c.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND CAST(JSON_VALUE(c.detail, '$.taglia') AS UNSIGNED) > 0
             ORDER BY c.id DESC LIMIT 1",
            [$hrs]
        );
        // La stessa uccisione non va in pagina due volte: se ha fruttato una
        // taglia la racconta quella notizia, non la cronaca.
        if ($kill !== null && ($riscossa === null || (int) $riscossa['id'] !== (int) $kill['id'])) {
            $h[] = "Cronaca di frontiera: {$kill['att']} ha avuto la meglio su {$kill['def']} nel settore " . (int) $kill['sector_id'] . '.';
        }
        if ($riscossa !== null) {
            // chi si difende e abbatte l'aggressore incassa anche lui; e la
            // taglia puo' essere quella da uccisioni di uno che non e' (piu')
            // ricercato: «aveva una taglia sulla testa» vale in tutti i casi
            [$cacciatore, $preda] = $riscossa['outcome'] === 'att_destroyed'
                ? [$riscossa['def'], $riscossa['att']]
                : [$riscossa['att'], $riscossa['def']];
            $h[] = "Taglia riscossa: {$cacciatore} ha abbattuto {$preda}, che aveva una taglia sulla testa, nel settore "
                . (int) $riscossa['sector_id'] . ' e incassato ' . number_format((int) $riscossa['taglia'], 0, ',', '.')
                . ' cr, confiscati a lui.';
        }

        $ricercati = [];
        foreach (Database::all(
            "SELECT p.handle, p.notorieta, p.notorieta_at FROM players p JOIN users u ON u.id = p.user_id
             WHERE p.notorieta > 0 AND u.status = 'active'"
        ) as $p) {
            $punti = Legge::punti($p);
            if (Legge::grado($punti) >= 2) {
                $ricercati[] = ['handle' => (string) $p['handle'], 'grado' => Legge::grado($punti), 'taglia' => Legge::taglia($p)];
            }
        }
        if ($ricercati !== []) {
            usort($ricercati, static fn (array $x, array $y): int => $y['taglia'] <=> $x['taglia']);
            $nomi = array_map(
                static fn (array $r): string => $r['handle'] . ' (' . mb_strtolower(Legge::nome($r['grado'])) . ', '
                    . number_format($r['taglia'], 0, ',', '.') . ' cr)',
                array_slice($ricercati, 0, 3)
            );
            $h[] = (count($ricercati) === 1 ? 'Ricercato dalla Federazione: ' : 'Ricercati dalla Federazione: ') . implode(', ', $nomi)
                . '. La taglia la paga il ricercato: chi lo abbatte la incassa, confiscata ai suoi crediti a bordo e in banca.';
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
        // Un bandito non va in prima pagina: prima restava in classifica, poteva
        // vincere la stagione e comparire qui come comandante di vertice.
        $top = Database::first("SELECT p.handle, p.rating FROM players p JOIN users u ON u.id = p.user_id
                                 WHERE p.rating > 0 AND u.status = 'active' ORDER BY p.rating DESC, p.experience DESC LIMIT 1");
        if ($top !== null) {
            $h[] = "In vetta alla classifica dei comandanti: {$top['handle']} (rating " . number_format((int) $top['rating'], 0, ',', '.') . ').';
        }

        // 4b) moda di plancia: il tema grafico piu' scelto (Console, quello di
        // base, non conta: e' di chi non ha scelto)
        try {
            $moda = Database::first(
                "SELECT u.tema, COUNT(*) AS n FROM users u JOIN players p ON p.user_id = u.id
                 WHERE u.status = 'active' AND u.tema IS NOT NULL AND u.tema <> ?
                 GROUP BY u.tema ORDER BY n DESC, u.tema LIMIT 1",
                [\App\Core\Temi::PREDEFINITO]
            );
        } catch (\Throwable) {
            $moda = null;   // colonna non ancora migrata: il bollettino esce lo stesso
        }
        if ($moda !== null && \App\Core\Temi::valido((string) $moda['tema'])) {
            $n = (int) $moda['n'];
            $h[] = 'Moda di plancia: il tema grafico piu\' scelto e\' ' . \App\Core\Temi::CATALOGO[$moda['tema']]['nome']
                . ($n === 1 ? ', sulla plancia di un comandante.' : ", sulle plance di {$n} comandanti.");
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
        // Gli avvisi di servizio si alternano giorno per giorno.
        $servizio = self::avvisiDiServizio();
        $h[] = 'Bollettino di servizio: ' . $servizio[(int) date('z') % count($servizio)] . ' Buona rotta, comandanti.';

        return $h;
    }

    /**
     * Gli avvisi che chiudono il bollettino, uno al giorno a rotazione.
     *
     * @return list<string>
     */
    private static function avvisiDiServizio(): array
    {
        $temi = array_column(\App\Core\Temi::CATALOGO, 'nome');
        return [
            'la protezione novizio resta attiva ' . GameConfig::int('newbie.protect_hours', 48) . ' ore dopo la registrazione.',
            'le taglie le paga chi le ha sulla testa. Chi abbatte un ricercato incassa la sua taglia, confiscata ai crediti a bordo'
                . ' e poi alla banca del ricercato: da un ricercato al verde non si ricava nulla. Capsule e navi di soccorso abbattute'
                . ' non valgono esperienza né bottino.',
            'ogni comandante sceglie l\'aspetto della sua plancia fra ' . count($temi) . ' temi grafici: '
                . implode(', ', array_slice($temi, 0, -1)) . ' e ' . end($temi) . '. Si cambia dal Profilo, o dal piè di pagina'
                . ' di qualunque schermata; vale per l\'account su ogni dispositivo. Cambia l\'aspetto, non il gioco.',
        ];
    }
}
