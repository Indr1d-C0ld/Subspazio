<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Guasti ai sottosistemi (slice B3). Un modulo che va OFFLINE (ship_modules
 * .broken_at IS NOT NULL) non contribuisce agli effetti in ShipStats::effective().
 * Cause: colpi incassati in combattimento, sovraccarico della griglia di
 * potenza (un canale EPS al massimo). Riparazione: StarDock (a pagamento),
 * Ingegnere di bordo sul tick, oppure ripristino automatico dopo qualche ora.
 */
final class Subsystems
{
    /** Canale EPS → categoria di modulo che stressa quando è al massimo. */
    public const SLOT_FOR_CHANNEL = [
        'weapons' => 'weapon', 'shields' => 'defense', 'engines' => 'drive', 'sensors' => 'computer',
    ];

    public static function breakChance(): int
    {
        return max(0, GameConfig::int('subsys.break_chance', 10));
    }

    public static function strainChance(): int
    {
        return max(0, GameConfig::int('subsys.strain_chance', 4));
    }

    public static function repairCostEach(): int
    {
        return max(0, GameConfig::int('subsys.repair_cost_each', 450));
    }

    public static function autoRepairHours(): int
    {
        return max(1, GameConfig::int('subsys.auto_repair_hours', 18));
    }

    public static function engineerFixChance(): int
    {
        return max(0, GameConfig::int('subsys.engineer_fix_chance', 25));
    }

    // --- lettura ---------------------------------------------------------

    /** @return list<array<string,mixed>> moduli fuori uso di una nave */
    public static function brokenList(int $shipId): array
    {
        return Database::all(
            "SELECT sm.id, sm.slot, sm.item_key, sm.broken_at, it.name
             FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
             WHERE sm.ship_id = ? AND sm.broken_at IS NOT NULL
             ORDER BY sm.broken_at ASC",
            [$shipId]
        );
    }

    public static function brokenCount(int $shipId): int
    {
        return (int) (Database::first(
            'SELECT COUNT(*) AS n FROM ship_modules WHERE ship_id = ? AND broken_at IS NOT NULL',
            [$shipId]
        )['n'] ?? 0);
    }

    // --- rottura -------------------------------------------------------

    /**
     * Prova a mettere offline un modulo funzionante a caso. Chance =
     * break_chance% (×2 se $heavy). Restituisce il nome del modulo colpito
     * o null (miss / nessun modulo funzionante). Con $log = false non scrive
     * sul giornale: usalo quando è il chiamante a mostrare l'evento.
     */
    public static function maybeBreak(int $shipId, string $cause, bool $heavy = false, bool $log = true): ?string
    {
        $chance = self::breakChance() * ($heavy ? 2 : 1);
        if ($chance <= 0 || mt_rand(1, 100) > $chance) {
            return null;
        }
        return self::breakOne($shipId, null, "Colpo ai sistemi ({$cause})", $log);
    }

    /**
     * Sovraccarico della griglia: se un canale EPS è al massimo, per warp c'è
     * strain_chance% (mitigata dall'Ingegnere) che un modulo di quel comparto
     * vada offline.
     *
     * @param array<string,int> $epsAlloc  canale => tacche
     */
    public static function maybeStrain(int $shipId, array $epsAlloc, int $engineering = 0): ?string
    {
        $maxPips = PowerGrid::maxPerChannel();
        $hot = [];
        foreach (self::SLOT_FOR_CHANNEL as $channel => $slot) {
            if ((int) ($epsAlloc[$channel] ?? 0) >= $maxPips) {
                $hot[] = $slot;
            }
        }
        if ($hot === []) {
            return null;
        }
        $chance = self::strainChance() * (1 - min(0.6, $engineering * 0.06));
        if ($chance <= 0 || mt_rand(1, 10000) > (int) round($chance * 100)) {
            return null;
        }
        return self::breakOne($shipId, $hot, 'Sovraccarico della griglia di potenza', false);
    }

    /**
     * @param list<string>|null $preferSlots  se dato, prova prima questi slot
     */
    private static function breakOne(int $shipId, ?array $preferSlots, string $logTitle, bool $log = true): ?string
    {
        $pick = null;
        if ($preferSlots) {
            $in = implode(',', array_fill(0, count($preferSlots), '?'));
            $pick = Database::first(
                "SELECT sm.id, it.name FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
                 WHERE sm.ship_id = ? AND sm.broken_at IS NULL AND sm.slot IN ($in)
                 ORDER BY RAND() LIMIT 1",
                array_merge([$shipId], $preferSlots)
            );
        }
        if ($pick === null) {
            $pick = Database::first(
                "SELECT sm.id, it.name FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
                 WHERE sm.ship_id = ? AND sm.broken_at IS NULL
                 ORDER BY RAND() LIMIT 1",
                [$shipId]
            );
        }
        if ($pick === null) {
            return null;
        }
        Database::run('UPDATE ship_modules SET broken_at = NOW() WHERE id = ?', [(int) $pick['id']]);

        if ($log) {
            $pid = (int) (Database::first('SELECT player_id FROM ships WHERE id = ?', [$shipId])['player_id'] ?? 0);
            if ($pid > 0) {
                ShipLog::write($pid, 'system', 'warning', $logTitle,
                    "Modulo fuori uso: {$pick['name']}. Riparalo allo StarDock o lascia fare all'Ingegnere.");
            }
        }
        return (string) $pick['name'];
    }

    // --- riparazione ------------------------------------------------

    /**
     * Riparazione allo StarDock: rimette in linea TUTTI i moduli fuori uso a
     * repair_cost_each cr l'uno.
     * @return array{ok:bool, count?:int, cost?:int, error?:string}
     */
    public static function repairAll(int $shipId, array $player): array
    {
        $broken = self::brokenCount($shipId);
        if ($broken === 0) {
            return ['ok' => false, 'error' => 'Nessun modulo da riparare.'];
        }
        $cost = $broken * self::repairCostEach();
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr per riparare {$broken} moduli."];
        }
        Database::run('UPDATE ship_modules SET broken_at = NULL WHERE ship_id = ? AND broken_at IS NOT NULL', [$shipId]);
        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, (int) $player['id']]);
        ShipLog::write((int) $player['id'], 'system', 'info', 'Riparazioni allo StarDock',
            "{$broken} moduli rimessi in linea per {$cost} cr.");
        return ['ok' => true, 'count' => $broken, 'cost' => $cost];
    }

    // --- tick -------------------------------------------------------

    /** @return array{auto:int, engineer:int} */
    public static function tick(): array
    {
        $auto = Database::run(
            'UPDATE ship_modules SET broken_at = NULL
             WHERE broken_at IS NOT NULL AND broken_at < DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [self::autoRepairHours()]
        )->rowCount();

        $engineer = 0;
        $base = self::engineerFixChance();
        if ($base > 0) {
            $rows = Database::all(
                "SELECT s.id AS ship_id, p.id AS player_id,
                        (SELECT o.skills FROM officers o
                          WHERE o.player_id = p.id AND o.role = 'engineer' AND o.assigned = 1 AND o.status <> 'dead'
                          ORDER BY o.level DESC LIMIT 1) AS eng_skills
                 FROM ships s JOIN players p ON p.id = s.player_id
                 WHERE EXISTS (SELECT 1 FROM ship_modules sm WHERE sm.ship_id = s.id AND sm.broken_at IS NOT NULL)"
            );
            foreach ($rows as $r) {
                if ($r['eng_skills'] === null) {
                    continue;
                }
                $skills = json_decode((string) $r['eng_skills'], true) ?: [];
                $eng = (int) ($skills['engineering'] ?? 0);
                $chance = min(90, $base + $eng * 5);
                if (mt_rand(1, 100) > $chance) {
                    continue;
                }
                $fix = Database::first(
                    'SELECT sm.id, it.name FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
                     WHERE sm.ship_id = ? AND sm.broken_at IS NOT NULL ORDER BY sm.broken_at ASC LIMIT 1',
                    [(int) $r['ship_id']]
                );
                if ($fix === null) {
                    continue;
                }
                Database::run('UPDATE ship_modules SET broken_at = NULL WHERE id = ?', [(int) $fix['id']]);
                ShipLog::write((int) $r['player_id'], 'system', 'info', 'Riparazione a bordo',
                    "L'Ingegnere ha rimesso in linea {$fix['name']}.");
                $engineer++;
            }
        }

        return ['auto' => $auto, 'engineer' => $engineer];
    }
}
