<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Cantiere dello StarDock: acquisto navi (con permuta), potenziamenti
 * (stive / caccia / scudi) e hardware (sonde, mine, capsula, scanner,
 * transwarp, occultamento).
 */
final class Shipyard
{
    private const HARDWARE = [
        'probe'            => ['col' => 'probes',       'price' => 'hardware.probe_price',           'cap' => 'hardware.probe_capacity'],
        'genesis'          => ['col' => 'genesis',      'price' => 'hardware.genesis_price',         'cap' => 'hardware.genesis_capacity'],
        'armid'            => ['col' => 'mines_armid',  'price' => 'hardware.armid_price',           'cap' => 'hardware.mine_capacity'],
        'limpet'           => ['col' => 'mines_limpet', 'price' => 'hardware.limpet_price',          'cap' => 'hardware.mine_capacity'],
        'escape_pod'       => ['col' => 'escape_pod',   'price' => 'hardware.escape_pod_price',      'flag' => true],
        'scanner_density'  => ['col' => 'dev_scanner',  'price' => 'hardware.scanner_density_price', 'enum' => 'density'],
        'scanner_holo'     => ['col' => 'dev_scanner',  'price' => 'hardware.scanner_holo_price',    'enum' => 'holo'],
        'transwarp'        => ['col' => 'dev_transwarp', 'price' => 'hardware.transwarp_price',      'flag' => true],
        'cloak'            => ['col' => 'dev_cloak',    'price' => 'hardware.cloak_price',           'flag' => true],
    ];

    public static function atShipyard(int $sectorId): bool
    {
        return Database::first('SELECT 1 AS x FROM sectors WHERE id = ? AND is_stardock = 1', [$sectorId]) !== null;
    }

    /** @return list<array<string,mixed>> */
    public static function catalog(): array
    {
        return Database::all("SELECT * FROM ship_types WHERE ckey <> 'escape_pod' ORDER BY sort_order");
    }

    /** @param array<string,mixed> $ship */
    public static function tradeInValue(array $ship): int
    {
        $type = Database::first('SELECT base_cost FROM ship_types WHERE ckey = ?', [$ship['type_key']]);
        return (int) floor((int) ($type['base_cost'] ?? 0) * 0.4);
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function buyShip(array $player, array $ship, string $typeKey): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        $type = Database::first("SELECT * FROM ship_types WHERE ckey = ? AND ckey <> 'escape_pod'", [$typeKey]);
        if ($type === null) {
            return ['ok' => false, 'error' => 'Modello sconosciuto.'];
        }
        if ($type['ckey'] === $ship['type_key']) {
            return ['ok' => false, 'error' => 'Hai gia\' questo modello.'];
        }

        $tradeIn = self::tradeInValue($ship);
        $cost = max(0, (int) $type['base_cost'] - $tradeIn);
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr (permuta {$tradeIn}); ne hai " . (int) $player['credits'] . '.'];
        }

        $cargo = Economy::holdsUsed($ship);
        if ($cargo > (int) $type['base_holds']) {
            return ['ok' => false, 'error' => "Svuota le stive: la nuova nave ha {$type['base_holds']} stive, ne usi {$cargo}."];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, $player['id']]);
            // caccia/scudi/scanner/transwarp/cloak NON si trasferiscono; sonde/mine/genesis si'.
            Database::run(
                "UPDATE ships SET type_key = ?, name = ?, holds_total = ?, fighters = ?, shields = ?,
                 dev_scanner = 'none', dev_transwarp = 0, dev_cloak = 0
                 WHERE id = ?",
                [
                    $type['ckey'],
                    'SS ' . $player['handle'],
                    (int) $type['base_holds'],
                    (int) $type['base_fighters'],
                    (int) $type['base_shields'],
                    $ship['id'],
                ]
            );
            Database::run('DELETE FROM ship_limpets WHERE ship_id = ?', [$ship['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['ok' => true, 'cost' => $cost, 'trade_in' => $tradeIn, 'type' => $type['ckey'], 'name' => $type['name']];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @param 'holds'|'fighters'|'shields' $kind
     */
    public static function upgrade(array $player, array $ship, string $kind, int $qty): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantita\' non valida.'];
        }
        $type = Database::first('SELECT * FROM ship_types WHERE ckey = ?', [$ship['type_key']]);

        [$col, $max, $unit] = match ($kind) {
            'holds' => ['holds_total', (int) $type['max_holds'],
                (int) round((int) $type['hold_price'] * GameConfig::float('hardware.hold_price_mult', 1.0))],
            'fighters' => ['fighters', (int) $type['max_fighters'], (int) ceil(GameConfig::float('hardware.fighter_price', 12))],
            'shields' => ['shields', (int) $type['max_shields'], (int) ceil(GameConfig::float('hardware.shield_price', 8))],
            default => [null, 0, 0],
        };
        if ($col === null) {
            return ['ok' => false, 'error' => 'Potenziamento sconosciuto.'];
        }

        $room = $max - (int) $ship[$col];
        if ($room <= 0) {
            return ['ok' => false, 'error' => 'Gia\' al massimo per questo scafo.'];
        }
        $qty = min($qty, $room);
        $cost = $qty * $unit;
        if ((int) $player['credits'] < $cost) {
            $aff = $unit > 0 ? intdiv((int) $player['credits'], $unit) : 0;
            return ['ok' => false, 'error' => "Servono {$cost} cr; puoi permetterti {$aff} unita\'."];
        }

        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, $player['id']]);
        Database::run("UPDATE ships SET {$col} = {$col} + ? WHERE id = ?", [$qty, $ship['id']]);
        return ['ok' => true, 'kind' => $kind, 'qty' => $qty, 'cost' => $cost];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function buyHardware(array $player, array $ship, string $item, int $qty = 1): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        $spec = self::HARDWARE[$item] ?? null;
        if ($spec === null) {
            return ['ok' => false, 'error' => 'Articolo sconosciuto.'];
        }
        $unit = GameConfig::int($spec['price'], 0);

        if (!empty($spec['flag'])) {
            if ((int) $ship[$spec['col']] === 1) {
                return ['ok' => false, 'error' => 'Gia\' installato.'];
            }
            if ((int) $player['credits'] < $unit) {
                return ['ok' => false, 'error' => "Servono {$unit} cr."];
            }
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$unit, $player['id']]);
            Database::run("UPDATE ships SET {$spec['col']} = 1 WHERE id = ?", [$ship['id']]);
            return ['ok' => true, 'item' => $item, 'cost' => $unit];
        }

        if (!empty($spec['enum'])) {
            if ($ship[$spec['col']] === $spec['enum']) {
                return ['ok' => false, 'error' => 'Gia\' installato.'];
            }
            if ((int) $player['credits'] < $unit) {
                return ['ok' => false, 'error' => "Servono {$unit} cr."];
            }
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$unit, $player['id']]);
            Database::run("UPDATE ships SET {$spec['col']} = ? WHERE id = ?", [$spec['enum'], $ship['id']]);
            return ['ok' => true, 'item' => $item, 'cost' => $unit];
        }

        // articolo a quantita' (sonde, mine)
        $cap = GameConfig::int($spec['cap'], 9999);
        $room = $cap - (int) $ship[$spec['col']];
        if ($room <= 0) {
            return ['ok' => false, 'error' => "Capacita\' massima ({$cap}) raggiunta."];
        }
        $qty = max(1, min($qty, $room));
        $cost = $qty * $unit;
        if ((int) $player['credits'] < $cost) {
            $aff = $unit > 0 ? intdiv((int) $player['credits'], $unit) : 0;
            return ['ok' => false, 'error' => "Servono {$cost} cr; puoi permetterti {$aff}."];
        }
        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, $player['id']]);
        Database::run("UPDATE ships SET {$spec['col']} = {$spec['col']} + ? WHERE id = ?", [$qty, $ship['id']]);
        return ['ok' => true, 'item' => $item, 'qty' => $qty, 'cost' => $cost];
    }
}
