<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Mercato nero: un contrabbandiere agli attracchi. Compra qualsiasi merce
 * a premio (ma costa allineamento), vende hardware scontato (idem), e
 * "ripulisce" la tua taglia a caro prezzo.
 */
final class BlackMarket
{
    private const HW = [
        'cloak'   => ['col' => 'dev_cloak',    'flag' => true,  'price' => 'hardware.cloak_price'],
        'genesis' => ['col' => 'genesis',      'price' => 'hardware.genesis_price', 'cap' => 'hardware.genesis_capacity'],
        'armid'   => ['col' => 'mines_armid',  'price' => 'hardware.armid_price',   'cap' => 'hardware.mine_capacity'],
        'limpet'  => ['col' => 'mines_limpet', 'price' => 'hardware.limpet_price',  'cap' => 'hardware.mine_capacity'],
    ];

    public static function available(int $sectorId): bool
    {
        return Database::first(
            'SELECT 1 x FROM sectors WHERE id = ? AND (is_stardock = 1 OR has_port = 1)',
            [$sectorId]
        ) !== null;
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function sell(array $player, array $ship, string $commodity, int $qty): array
    {
        if (!self::available((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Nessun contatto in questo settore.'];
        }
        if (!in_array($commodity, Economy::COMMODITIES, true) || $qty <= 0) {
            return ['ok' => false, 'error' => 'Parametri non validi.'];
        }
        $col = Economy::shipColumn($commodity);
        if ((int) $ship[$col] < $qty) {
            return ['ok' => false, 'error' => 'Carico insufficiente.'];
        }

        $region = (int) (Database::first('SELECT region_id FROM sectors WHERE id = ?', [(int) $player['sector_id']])['region_id'] ?? 0);
        $base = Economy::regionBase($region, $commodity);
        $unit = $base * GameConfig::float('blackmarket.sell_premium', 1.15);
        $total = (int) round($unit * $qty);
        $alignHit = (int) floor(GameConfig::int('blackmarket.align_per_sale', -3) * max(1, $qty / 100));
        $alignHit = max($alignHit, -60);

        Database::run("UPDATE ships SET {$col} = {$col} - ? WHERE id = ?", [$qty, $ship['id']]);
        Database::run('UPDATE players SET credits = credits + ?, alignment = alignment + ? WHERE id = ?', [$total, $alignHit, $player['id']]);
        Achievements::award((int) $player['id'], 'black_market');

        return ['ok' => true, 'total' => $total, 'unit' => round($unit, 2), 'align' => $alignHit];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function buy(array $player, array $ship, string $item, int $qty): array
    {
        if (!self::available((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Nessun contatto in questo settore.'];
        }
        $spec = self::HW[$item] ?? null;
        if ($spec === null) {
            return ['ok' => false, 'error' => 'Articolo non disponibile.'];
        }
        $disc = GameConfig::float('blackmarket.hw_discount', 0.75);
        $unit = (int) round(GameConfig::int($spec['price'], 0) * $disc);
        $alignBuy = GameConfig::int('blackmarket.align_per_buy', -5);

        if (!empty($spec['flag'])) {
            if ((int) $ship[$spec['col']] === 1) {
                return ['ok' => false, 'error' => 'Gia\' installato.'];
            }
            if ((int) $player['credits'] < $unit) {
                return ['ok' => false, 'error' => "Servono {$unit} cr."];
            }
            Database::run('UPDATE players SET credits = credits - ?, alignment = alignment + ? WHERE id = ?', [$unit, $alignBuy, $player['id']]);
            Database::run("UPDATE ships SET {$spec['col']} = 1 WHERE id = ?", [$ship['id']]);
            Achievements::award((int) $player['id'], 'black_market');
            return ['ok' => true, 'cost' => $unit, 'align' => $alignBuy];
        }

        $cap = GameConfig::int($spec['cap'], 9999);
        $qty = max(1, min($qty, $cap - (int) $ship[$spec['col']]));
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Capacita\' massima raggiunta.'];
        }
        $cost = $unit * $qty;
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr."];
        }
        Database::run('UPDATE players SET credits = credits - ?, alignment = alignment + ? WHERE id = ?', [$cost, $alignBuy, $player['id']]);
        Database::run("UPDATE ships SET {$spec['col']} = {$spec['col']} + ? WHERE id = ?", [$qty, $ship['id']]);
        Achievements::award((int) $player['id'], 'black_market');
        return ['ok' => true, 'qty' => $qty, 'cost' => $cost, 'align' => $alignBuy];
    }

    /** @param array<string,mixed> $player */
    public static function clearBounty(array $player): array
    {
        if (!self::available((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Nessun contatto in questo settore.'];
        }
        $bounty = (int) $player['bounty'];
        if ($bounty <= 0) {
            return ['ok' => false, 'error' => 'Non hai una taglia sulla testa.'];
        }
        $cost = (int) round($bounty * GameConfig::float('blackmarket.bounty_clear_mult', 1.5));
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Ripulire la taglia costa {$cost} cr."];
        }
        Database::run('UPDATE players SET credits = credits - ?, bounty = 0 WHERE id = ?', [$cost, $player['id']]);
        Achievements::award((int) $player['id'], 'black_market');
        return ['ok' => true, 'cost' => $cost];
    }

    /** @return list<array{item:string,name:string,price:int}> */
    public static function catalog(): array
    {
        $disc = GameConfig::float('blackmarket.hw_discount', 0.75);
        return [
            ['item' => 'genesis', 'name' => 'Siluro Genesi', 'price' => (int) round(GameConfig::int('hardware.genesis_price', 31000) * $disc)],
            ['item' => 'armid',   'name' => 'Mina Armid',    'price' => (int) round(GameConfig::int('hardware.armid_price', 95) * $disc)],
            ['item' => 'limpet',  'name' => 'Mina Limpet',   'price' => (int) round(GameConfig::int('hardware.limpet_price', 55) * $disc)],
            ['item' => 'cloak',   'name' => 'Occultamento',  'price' => (int) round(GameConfig::int('hardware.cloak_price', 35000) * $disc)],
        ];
    }
}
