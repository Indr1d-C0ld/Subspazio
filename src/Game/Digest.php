<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Rapporto di rientro: quando torni in plancia dopo un'assenza, un
 * riassunto di cosa e' maturato mentre eri via. Composto da dati che
 * esistono gia' (giornale di bordo, colonie, contratti, turni).
 *
 * Mostrato una volta per assenza: forView() ritorna null se sei stato via
 * meno di digest.min_away_min, altrimenti il chiamante segna markShown().
 */
final class Digest
{
    /**
     * @param array<string,mixed> $player
     * @return array<string,mixed>|null
     */
    public static function forView(array $player): ?array
    {
        $pid = (int) $player['id'];
        $minAway = max(5, GameConfig::int('digest.min_away_min', 20));

        $ref = $player['last_digest_at'] ?? null;
        if ($ref === null) {
            $ref = $player['last_seen_at'] ?? null;
        }
        if ($ref === null) {
            return null; // primo ingresso in assoluto: niente rientro
        }
        $cutoffTs = strtotime((string) $ref);
        $awaySec = time() - $cutoffTs;
        if ($awaySec < $minAway * 60) {
            return null;
        }
        $cutoff = date('Y-m-d H:i:s', $cutoffTs);

        $lines = [];

        // giornale di bordo accumulato
        try {
            $log = Database::first(
                "SELECT COUNT(*) c, SUM(severity = 'alert') a, SUM(severity = 'warning') w
                 FROM ship_log WHERE player_id = ? AND created_at > ?",
                [$pid, $cutoff]
            );
            $lc = (int) ($log['c'] ?? 0);
            if ($lc > 0) {
                $crit = (int) ($log['a'] ?? 0);
                $warn = (int) ($log['w'] ?? 0);
                $tail = $crit > 0 ? " ({$crit} critici)" : ($warn > 0 ? " ({$warn} da rivedere)" : '');
                $lines[] = [
                    'icon' => $crit > 0 ? '⚠' : '›',
                    'text' => "{$lc} nuove voci nel giornale di bordo{$tail}.",
                    'link' => '/gioco/giornale',
                ];
            }
        } catch (\Throwable) {
        }

        // turni ricaricati (il ciclo giornaliero e' passato durante l'assenza)
        if (date('Y-m-d', $cutoffTs) !== TurnManager::gameDay()) {
            $lines[] = ['icon' => '⟳', 'text' => 'Il ciclo giornaliero ha ricaricato i turni.', 'link' => null];
        }

        // colonie: continuano a produrre
        try {
            $pl = Database::first(
                "SELECT COUNT(*) c, COALESCE(SUM(stock_ore),0) ore, COALESCE(SUM(industry),0) ind
                 FROM planets WHERE destroyed = 0 AND owner_player_id = ?",
                [$pid]
            );
            $pc = (int) ($pl['c'] ?? 0);
            if ($pc > 0) {
                $ind = (int) ($pl['ind'] ?? 0);
                $t = $pc === 1 ? 'La tua colonia ha' : "Le tue {$pc} colonie hanno";
                $extra = $ind > 0 ? " — {$ind} in modalità industria." : '.';
                $lines[] = ['icon' => '⬡', 'text' => "{$t} continuato a produrre" . $extra, 'link' => '/gioco/pianeti'];
            }
        } catch (\Throwable) {
        }

        // lavori dell'officina completati mentre eri via
        try {
            $done = (int) (Database::first(
                "SELECT COUNT(*) c FROM ship_log WHERE player_id = ? AND kind = 'system'
                 AND created_at > ? AND title LIKE 'Officina%'",
                [$pid, $cutoff]
            )['c'] ?? 0);
            if ($done > 0) {
                $lines[] = ['icon' => '⚙', 'text' => "{$done} lavori dell'Officina completati.", 'link' => '/gioco/moduli'];
            }
        } catch (\Throwable) {
        }

        // contratti tuoi scaduti nell'intervallo
        try {
            $exp = (int) (Database::first(
                "SELECT COUNT(*) c FROM contracts
                 WHERE status = 'expired' AND expires_at > ? AND expires_at <= NOW()
                   AND (issuer_player_id = ? OR target_player_id = ?)",
                [$cutoff, $pid, $pid]
            )['c'] ?? 0);
            if ($exp > 0) {
                $lines[] = ['icon' => '✕', 'text' => "{$exp} contratti che ti riguardavano sono scaduti.", 'link' => '/gioco/contratti'];
            }
        } catch (\Throwable) {
        }

        if ($lines === []) {
            return null; // sei stato via ma non e' successo niente di notevole
        }

        return [
            'away'  => self::humanGap($awaySec),
            'lines' => $lines,
        ];
    }

    public static function markShown(int $playerId): void
    {
        try {
            Database::run('UPDATE players SET last_digest_at = NOW() WHERE id = ?', [$playerId]);
        } catch (\Throwable) {
        }
    }

    private static function humanGap(int $sec): string
    {
        if ($sec < 3600) {
            return max(1, (int) round($sec / 60)) . ' minuti';
        }
        if ($sec < 86400) {
            $h = $sec / 3600;
            return ($h < 2 ? '1 ora' : (int) round($h) . ' ore');
        }
        $d = (int) round($sec / 86400);
        return $d === 1 ? '1 giorno' : "{$d} giorni";
    }
}
