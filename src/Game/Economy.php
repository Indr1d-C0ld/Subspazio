<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Motore economico: prezzo "equo" per porto derivato da domanda/offerta
 * locale (stock vs capacita') e da un valore base regionale che deriva nel
 * tempo. Il commercio muove lo stock del porto e quindi il prezzo successivo.
 */
final class Economy
{
    public const COMMODITIES = ['ore', 'organics', 'equipment'];

    private const LABELS = [
        'ore'       => 'Minerale',
        'organics'  => 'Organico',
        'equipment' => 'Equipaggiamento',
    ];

    /** @var array<string,float> */
    private static array $regionBaseCache = [];

    public static function label(string $c): string
    {
        return self::LABELS[$c] ?? $c;
    }

    public static function prefix(string $c): string
    {
        return ['ore' => 'ore', 'organics' => 'org', 'equipment' => 'equ'][$c] ?? $c;
    }

    public static function shipColumn(string $c): string
    {
        return ['ore' => 'hold_ore', 'organics' => 'hold_organics', 'equipment' => 'hold_equipment'][$c] ?? '';
    }

    public static function anchor(string $c): float
    {
        return GameConfig::float('economy.anchor.' . $c, match ($c) {
            'ore' => 18.0, 'organics' => 28.0, default => 38.0,
        });
    }

    // --- lettura porto + rigenerazione lazy ------------------------------

    /** @return array<string,mixed>|null porto nel settore, con stock rigenerato */
    public static function portAt(int $sectorId): ?array
    {
        $port = Database::first(
            'SELECT p.*, s.region_id, s.name AS sector_name, s.is_stardock
             FROM ports p JOIN sectors s ON s.id = p.sector_id
             WHERE p.sector_id = ? AND p.destroyed = 0',
            [$sectorId]
        );
        if ($port === null) {
            return null;
        }
        return self::regenerate($port);
    }

    /**
     * Avanza stock e tesoreria del porto in base al tempo trascorso e
     * persiste. Idempotente entro 1 secondo.
     *
     * @param array<string,mixed> $port
     * @return array<string,mixed>
     */
    public static function regenerate(array $port): array
    {
        $elapsed = time() - strtotime((string) $port['last_update']);
        if ($elapsed < 1) {
            return $port;
        }

        $hoursFull = max(1.0, GameConfig::float('economy.regen_hours_full', 72.0));
        $span = $hoursFull * 3600.0;

        foreach (self::COMMODITIES as $c) {
            $pf = self::prefix($c);
            $cap = (float) $port["{$pf}_capacity"];
            $stock = (float) $port["{$pf}_stock"];
            $rate = $cap / $span;
            $delta = $rate * $elapsed;

            if ($port["{$pf}_mode"] === 'sell') {
                $stock = min($cap, $stock + $delta);          // il porto si rifornisce
            } else {
                $stock = max(0.0, $stock - $delta);           // il porto smaltisce le scorte comprate
            }
            $port["{$pf}_stock"] = (int) round($stock);
        }

        $crMax = (float) $port['credits_max'];
        if ($crMax > 0) {
            $port['credits'] = (int) round(min($crMax, (float) $port['credits'] + ($crMax / $span) * $elapsed));
        }

        $fMax = (int) ($port['fighters_max'] ?? 0);
        if ($fMax > 0 && (int) $port['fighters'] < $fMax) {
            $port['fighters'] = (int) round(min($fMax, (float) $port['fighters'] + ($fMax / $span) * $elapsed));
        }

        Database::run(
            'UPDATE ports SET ore_stock = ?, org_stock = ?, equ_stock = ?, credits = ?, fighters = ?, last_update = NOW()
             WHERE id = ?',
            [$port['ore_stock'], $port['org_stock'], $port['equ_stock'], $port['credits'], $port['fighters'], $port['id']]
        );

        return $port;
    }

    // --- prezzi ---------------------------------------------------------

    public static function regionBase(int $regionId, string $commodity): float
    {
        $key = $regionId . ':' . $commodity;
        if (!isset(self::$regionBaseCache[$key])) {
            $row = Database::first(
                'SELECT base_value FROM commodity_market WHERE region_id = ? AND commodity = ?',
                [$regionId, $commodity]
            );
            self::$regionBaseCache[$key] = $row !== null
                ? (float) $row['base_value']
                : self::anchor($commodity);
        }
        return self::$regionBaseCache[$key];
    }

    /**
     * Prezzo unitario "equo" (senza ricarico/sconto), funzione della
     * scarsita' locale.
     *
     * @param array<string,mixed> $port
     */
    public static function fairUnit(array $port, string $commodity): float
    {
        $pf = self::prefix($commodity);
        $cap = (float) $port["{$pf}_capacity"];
        $ratio = $cap > 0 ? min(1.0, max(0.0, (float) $port["{$pf}_stock"] / $cap)) : 0.5;
        $base = self::regionBase((int) $port['region_id'], $commodity);
        $elasticity = GameConfig::float('economy.price_elasticity', 0.9);

        $fair = $base * (1.0 + $elasticity * (0.5 - $ratio));
        return max($base * 0.35, min($base * 1.9, $fair));
    }

    /**
     * Quotazione per un ordine di dimensione $qty.
     *
     * @param array<string,mixed> $port
     * @param 'buy'|'sell' $action  punto di vista del giocatore
     * @return array{fair:float, unit:float, unit_raw:float, total:int, base:float}
     */
    public static function quote(array $port, string $commodity, string $action, int $qty): array
    {
        $fair = self::fairUnit($port, $commodity);
        $pf = self::prefix($commodity);
        $cap = max(1.0, (float) $port["{$pf}_capacity"]);
        $qtyRel = max(0.0, $qty / $cap);
        $slip = GameConfig::float('economy.slippage', 0.35);

        if ($action === 'buy') {
            $markup = GameConfig::float('economy.sell_markup', 1.12);
            $unit = $fair * $markup * (1.0 + $slip * $qtyRel);
        } else {
            $discount = GameConfig::float('economy.buy_discount', 0.90);
            $unit = max($fair * 0.30, $fair * $discount * (1.0 - $slip * $qtyRel));
        }

        return [
            'fair'     => round($fair, 2),
            'unit'     => round($unit, 2),
            'unit_raw' => $unit,
            'total'    => (int) round($unit * $qty),
            'base'     => round(self::regionBase((int) $port['region_id'], $commodity), 2),
        ];
    }

    /** Totale neutro (obiettivo di convergenza della contrattazione). */
    public static function fairTotal(array $port, string $commodity, string $action, int $qty): int
    {
        $fair = self::fairUnit($port, $commodity);
        $cap = max(1.0, (float) $port[self::prefix($commodity) . '_capacity']);
        $qtyRel = max(0.0, $qty / $cap);
        $slip = GameConfig::float('economy.slippage', 0.35) * 0.5;
        $f = $action === 'buy' ? (1.0 + $slip * $qtyRel) : (1.0 - $slip * $qtyRel);
        return (int) round($fair * $qty * $f);
    }

    /**
     * Quantita' massima trattabile ora, dati i vincoli di entrambe le parti.
     *
     * @param array<string,mixed> $port
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function maxQty(array $port, array $player, array $ship, string $commodity, string $action): int
    {
        $pf = self::prefix($commodity);
        $stock = (int) $port["{$pf}_stock"];
        $cap = (int) $port["{$pf}_capacity"];

        if ($action === 'buy') {
            if ($port["{$pf}_mode"] !== 'sell') {
                return 0;
            }
            $room = (int) $ship['holds_total'] - self::holdsUsed($ship);
            $cap1 = min($stock, max(0, $room));
            if ($cap1 <= 0) {
                return 0;
            }
            $unit = self::quote($port, $commodity, 'buy', $cap1)['unit_raw'];
            $byCredits = $unit > 0 ? (int) floor((int) $player['credits'] / $unit) : 0;
            return max(0, min($cap1, $byCredits));
        }

        if ($port["{$pf}_mode"] !== 'buy') {
            return 0;
        }
        $byCargo = (int) $ship[self::shipColumn($commodity)];
        $byRoom = max(0, $cap - $stock);
        $cap1 = min($byCargo, $byRoom);
        if ($cap1 <= 0) {
            return 0;
        }
        $unit = self::quote($port, $commodity, 'sell', $cap1)['unit_raw'];
        $byPortCredits = $unit > 0 ? (int) floor((int) $port['credits'] / $unit) : 0;
        return max(0, min($cap1, $byPortCredits));
    }

    /** @param array<string,mixed> $ship */
    public static function holdsUsed(array $ship): int
    {
        return (int) $ship['hold_ore'] + (int) $ship['hold_organics']
            + (int) $ship['hold_equipment'] + (int) $ship['hold_colonists'];
    }

    /**
     * Sintesi del porto per la scheda settore.
     *
     * @param array<string,mixed> $port
     * @return array<string,mixed>
     */
    public static function portSummary(array $port): array
    {
        $lines = [];
        foreach (self::COMMODITIES as $c) {
            $pf = self::prefix($c);
            $mode = $port["{$pf}_mode"];
            $q = self::quote($port, $c, $mode === 'sell' ? 'buy' : 'sell', 1);
            $cap = max(1, (int) $port["{$pf}_capacity"]);
            $lines[$c] = [
                'commodity' => $c,
                'label'     => self::label($c),
                'mode'      => $mode,                      // 'sell' = vende a te, 'buy' = compra da te
                'stock'     => (int) $port["{$pf}_stock"],
                'capacity'  => $cap,
                'pct'       => (int) round(100 * (int) $port["{$pf}_stock"] / $cap),
                'unit'      => $q['unit'],
                'fair'      => $q['fair'],
            ];
        }
        return [
            'id'     => (int) $port['id'],
            'name'   => $port['name'],
            'class'  => (int) $port['class'],
            'code'   => self::classCode($port),
            'tech'   => (int) $port['tech_level'],
            'is_stardock' => (bool) $port['is_stardock'],
            'commodities' => $lines,
        ];
    }

    /** Sigla tipo BBS / SSB ... (ordine minerale, organico, equipaggiamento). */
    public static function classCode(array $port): string
    {
        return strtoupper(
            ($port['ore_mode'] === 'buy' ? 'B' : 'S')
            . ($port['org_mode'] === 'buy' ? 'B' : 'S')
            . ($port['equ_mode'] === 'buy' ? 'B' : 'S')
        );
    }

    // --- transazione --------------------------------------------------

    /**
     * Esegue lo scambio. Server-authoritative: il prezzo NON arriva dal
     * client per lo "scambio veloce"; per la contrattazione arriva da una
     * sessione server (vedi Haggle) e viene comunque validato.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @param 'buy'|'sell' $action
     * @return array{ok:bool, error?:string, player?:array, ship?:array, port?:array, total?:int, unit?:float, fair_total?:int}
     */
    public static function settle(
        array $player,
        array $ship,
        int $sectorId,
        string $commodity,
        string $action,
        int $qty,
        ?int $agreedTotal,
        int $haggleRounds = 0
    ): array {
        if (!in_array($commodity, self::COMMODITIES, true) || !in_array($action, ['buy', 'sell'], true)) {
            return ['ok' => false, 'error' => 'Parametri di scambio non validi.'];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantita\' non valida.'];
        }
        if ((int) $player['sector_id'] !== $sectorId) {
            return ['ok' => false, 'error' => 'Non sei nel settore del porto.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $port = Database::first(
                'SELECT p.*, s.region_id, s.name AS sector_name, s.is_stardock
                 FROM ports p JOIN sectors s ON s.id = p.sector_id
                 WHERE p.sector_id = ? AND p.destroyed = 0 FOR UPDATE',
                [$sectorId]
            );
            if ($port === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Nessun porto in questo settore.'];
            }
            $port = self::regenerate($port);

            $pf = self::prefix($commodity);
            $needMode = $action === 'buy' ? 'sell' : 'buy';
            if ($port["{$pf}_mode"] !== $needMode) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Il porto non tratta questa merce in quel verso.'];
            }

            $freshShip = Database::first('SELECT * FROM ships WHERE id = ?', [$ship['id']]);
            $freshPlayer = Database::first('SELECT * FROM players WHERE id = ?', [$player['id']]);
            $max = self::maxQty($port, $freshPlayer, $freshShip, $commodity, $action);
            if ($qty > $max) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Quantita' non disponibile ora (max {$max})."];
            }

            $fairUnit = self::fairUnit($port, $commodity);
            $std = self::quote($port, $commodity, $action, $qty);
            $walk = GameConfig::float('economy.haggle.walk_band', 0.16) + 0.03;

            if ($agreedTotal === null) {
                $total = $std['total'];
            } else {
                $total = $agreedTotal;
                // limiti di sanita': niente prezzi forgiati
                $loBound = (int) floor($fairUnit * $qty * (1 - $walk) * ($action === 'buy' ? GameConfig::float('economy.buy_discount', 0.9) : 0.9));
                $hiBound = (int) ceil($fairUnit * $qty * (1 + $walk) * ($action === 'buy' ? 1.3 : GameConfig::float('economy.sell_markup', 1.12) + 0.2));
                if ($total < $loBound || $total > $hiBound) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Prezzo concordato fuori dai limiti.'];
                }
            }
            $total = max(0, $total);

            $col = self::shipColumn($commodity);
            if ($action === 'buy') {
                if ((int) $freshPlayer['credits'] < $total) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Crediti insufficienti.'];
                }
                Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$total, $player['id']]);
                Database::run("UPDATE ships SET {$col} = {$col} + ? WHERE id = ?", [$qty, $ship['id']]);
                Database::run(
                    "UPDATE ports SET {$pf}_stock = {$pf}_stock - ?, credits = credits + ? WHERE id = ?",
                    [$qty, $total, $port['id']]
                );
            } else {
                if ((int) $port['credits'] < $total) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Il porto non ha credito sufficiente.'];
                }
                Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [$total, $player['id']]);
                Database::run("UPDATE ships SET {$col} = {$col} - ? WHERE id = ?", [$qty, $ship['id']]);
                Database::run(
                    "UPDATE ports SET {$pf}_stock = {$pf}_stock + ?, credits = credits - ? WHERE id = ?",
                    [$qty, $total, $port['id']]
                );
            }

            Database::run(
                'INSERT INTO trade_log (player_id, port_id, sector_id, commodity, action, qty, unit_price, total, fair_total, haggle_rounds)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $player['id'], $port['id'], $sectorId, $commodity, $action, $qty,
                    round($total / $qty, 4), $total, (int) round($fairUnit * $qty), $haggleRounds,
                ]
            );
            Database::run(
                'INSERT INTO commodity_market (region_id, commodity, base_value, anchor, volume_buy, volume_sell)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE volume_buy = volume_buy + VALUES(volume_buy),
                                         volume_sell = volume_sell + VALUES(volume_sell)',
                [
                    (int) $port['region_id'], $commodity,
                    self::anchor($commodity), self::anchor($commodity),
                    $action === 'buy' ? $qty : 0,
                    $action === 'sell' ? $qty : 0,
                ]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok'         => true,
            'total'      => $total,
            'unit'       => round($total / $qty, 2),
            'fair_total' => (int) round($fairUnit * $qty),
            'player'     => Database::first('SELECT * FROM players WHERE id = ?', [$player['id']]),
            'ship'       => PlayerService::ship((int) $ship['id']),
            'port'       => self::portAt($sectorId),
        ];
    }

    // --- drift regionale (tick) -------------------------------------

    public static function driftRegions(bool $force = false): int
    {
        $intervalMin = GameConfig::int('economy.drift.interval_min', 30);
        $last = GameConfig::str('economy.drift.last_run', '');
        if (!$force && $last !== '' && (time() - strtotime($last)) < $intervalMin * 60) {
            return 0;
        }

        $rate = GameConfig::float('economy.drift.rate', 0.06);
        $impact = GameConfig::float('economy.drift.impact', 0.9);
        $band = GameConfig::float('economy.drift.band', 0.45);

        $rows = Database::all('SELECT * FROM commodity_market');
        foreach ($rows as $r) {
            $anchor = (float) $r['anchor'];
            $base = (float) $r['base_value'];
            $vb = (int) $r['volume_buy'];
            $vs = (int) $r['volume_sell'];
            $tot = $vb + $vs;
            $pressure = $tot > 0 ? ($vb - $vs) / $tot : 0.0;

            $base += $impact * $pressure * $anchor * 0.05;
            $base += ($anchor - $base) * $rate;
            $base = max($anchor * (1 - $band), min($anchor * (1 + $band), $base));

            Database::run(
                'UPDATE commodity_market SET base_value = ?, volume_buy = 0, volume_sell = 0, last_update = NOW()
                 WHERE region_id = ? AND commodity = ?',
                [round($base, 4), (int) $r['region_id'], $r['commodity']]
            );
        }

        GameConfig::set('economy.drift.last_run', date('Y-m-d H:i:s'));
        return count($rows);
    }
}
