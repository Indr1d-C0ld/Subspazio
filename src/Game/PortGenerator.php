<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Popola l'universo di porti (classi 1-8 con distribuzione domanda/offerta)
 * e inizializza il mercato regionale. Lo StarDock riceve un porto speciale
 * di classe 0 che vende tutte le merci.
 */
final class PortGenerator
{
    /** ordine merci: minerale, organico, equipaggiamento. B = il porto compra, S = vende. */
    private const CLASSES = [
        1 => 'BBS', 2 => 'BSB', 3 => 'SBB', 4 => 'SSB',
        5 => 'SBS', 6 => 'BSS', 7 => 'SSS', 8 => 'BBB',
    ];

    /** pesi di frequenza per classe (7 e 8 piu' rare) */
    private const CLASS_WEIGHTS = [1 => 18, 2 => 18, 3 => 18, 4 => 14, 5 => 14, 6 => 12, 7 => 3, 8 => 3];

    private const NAME_PREFIX = ['Avamposto', 'Scalo', 'Emporio', 'Deposito', 'Stazione', 'Mercato', 'Rada', 'Approdo'];

    /** @return array<string,mixed> */
    public static function generate(bool $force): array
    {
        $exists = (int) (Database::first('SELECT COUNT(*) AS c FROM ports')['c'] ?? 0) > 0;
        if ($exists && !$force) {
            throw new \RuntimeException('I porti esistono gia\'. Usa --force per rigenerarli.');
        }
        if (!Universe::exists()) {
            throw new \RuntimeException('Genera prima l\'universo (universe:generate).');
        }

        $t0 = microtime(true);
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['trade_log', 'commodity_market', 'ports'] as $t) {
            $pdo->exec("TRUNCATE TABLE {$t}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Database::run('UPDATE sectors SET has_port = 0');

        $density = GameConfig::float('economy.port_density', 0.32);
        $anchorAvg = (Economy::anchor('ore') + Economy::anchor('organics') + Economy::anchor('equipment')) / 3;

        $sectors = Database::all('SELECT id, name, is_fedspace, is_stardock FROM sectors ORDER BY id');
        $classPick = self::weightedPicker(self::CLASS_WEIGHTS);

        $rows = [];
        $params = [];
        $portSectors = [];
        $count = 0;
        $classCounts = [];

        $flush = function () use (&$rows, &$params): void {
            if ($rows === []) {
                return;
            }
            Database::run(
                'INSERT INTO ports
                 (sector_id, name, class, tech_level, ore_mode, org_mode, equ_mode,
                  ore_stock, org_stock, equ_stock, ore_capacity, org_capacity, equ_capacity,
                  credits, credits_max)
                 VALUES ' . implode(',', $rows),
                $params
            );
            $rows = [];
            $params = [];
        };

        foreach ($sectors as $s) {
            $sid = (int) $s['id'];
            $isDock = (int) $s['is_stardock'] === 1;

            if ($isDock) {
                $class = 0;
                $modes = ['sell', 'sell', 'sell'];
                $tech = 6;
            } elseif ((int) $s['is_fedspace'] === 1) {
                continue; // niente porti commerciali dentro la Federazione (Fase 2)
            } elseif (self::frand() < $density) {
                $class = $classPick();
                $modes = array_map(
                    static fn ($ch) => $ch === 'B' ? 'buy' : 'sell',
                    str_split(self::CLASSES[$class])
                );
                $tech = self::weightedPicker([1 => 26, 2 => 24, 3 => 20, 4 => 14, 5 => 9, 6 => 7])();
            } else {
                continue;
            }

            $classCounts[$class] = ($classCounts[$class] ?? 0) + 1;
            $techFactor = 1 + ($tech - 1) * 0.35;
            $baseCap = $isDock ? 20000 : 3000;

            $caps = [];
            $stocks = [];
            $capSum = 0;
            foreach (['ore', 'organics', 'equipment'] as $i => $c) {
                $cap = (int) round($baseCap * $techFactor * (0.6 + 0.8 * self::frand()));
                $caps[$c] = max(200, $cap);
                $capSum += $caps[$c];
                $stocks[$c] = $modes[$i] === 'sell'
                    ? (int) round($caps[$c] * (0.75 + 0.25 * self::frand()))
                    : (int) round($caps[$c] * (0.0 + 0.2 * self::frand()));
            }

            $crMax = (int) round($capSum * $anchorAvg * ($isDock ? 4 : 1.5));
            $cr = (int) round($crMax * (0.5 + 0.5 * self::frand()));

            $name = $isDock
                ? 'StarDock'
                : self::NAME_PREFIX[array_rand(self::NAME_PREFIX)] . ' ' . $s['name'];

            $rows[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            array_push(
                $params,
                $sid, $name, $class, $tech,
                $modes[0], $modes[1], $modes[2],
                $stocks['ore'], $stocks['organics'], $stocks['equipment'],
                $caps['ore'], $caps['organics'], $caps['equipment'],
                $cr, $crMax,
            );
            $portSectors[] = $sid;
            $count++;

            if (count($rows) >= 150) {
                $flush();
            }
        }
        $flush();

        if ($portSectors !== []) {
            $chunks = array_chunk($portSectors, 500);
            foreach ($chunks as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                Database::run("UPDATE sectors SET has_port = 1 WHERE id IN ($in)", $chunk);
            }
        }

        // mercato regionale
        $regions = Database::all('SELECT id FROM regions');
        foreach ($regions as $r) {
            foreach (Economy::COMMODITIES as $c) {
                $anchor = Economy::anchor($c);
                Database::run(
                    'INSERT INTO commodity_market (region_id, commodity, base_value, anchor)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE base_value = VALUES(base_value), anchor = VALUES(anchor)',
                    [(int) $r['id'], $c, round($anchor * (0.9 + 0.2 * self::frand()), 3), $anchor]
                );
            }
        }

        GameConfig::set('economy.generated_at', date('Y-m-d H:i:s'));
        GameConfig::forget();

        ksort($classCounts);
        return [
            'ports'         => $count,
            'by_class'      => $classCounts,
            'regions'       => count($regions),
            'seconds'       => round(microtime(true) - $t0, 2),
        ];
    }

    /** @param array<int,int> $weights @return callable():int */
    private static function weightedPicker(array $weights): callable
    {
        $total = array_sum($weights);
        return static function () use ($weights, $total): int {
            $r = mt_rand(1, $total);
            foreach ($weights as $k => $w) {
                $r -= $w;
                if ($r <= 0) {
                    return $k;
                }
            }
            return array_key_last($weights);
        };
    }

    private static function frand(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
