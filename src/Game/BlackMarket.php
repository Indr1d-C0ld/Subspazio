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
     * Prezzo unitario che il mercato nero paga in questo settore, o null se qui
     * quella merce non la ritira.
     *
     * Il premio si applica al prezzo equo del porto LOCALE, come promette
     * l'aiuto, e il mercato nero non compra la merce che il porto del settore
     * vende. Prima pagava un premio sul prezzo medio di regione, ignorando le
     * scorte locali, proprio accanto a un porto che quella merce la vendeva a
     * sconto: si comprava al porto e si rivendeva al mercato nero sul posto,
     * +87% a giro allo StarDock, senza spendere un turno.
     */
    public static function buyPrice(int $sectorId, string $commodity): ?float
    {
        if (!in_array($commodity, Economy::COMMODITIES, true)) {
            return null;
        }
        $port = Economy::portAt($sectorId);
        if ($port === null || $port[Economy::prefix($commodity) . '_mode'] === 'sell') {
            return null;
        }
        return Economy::fairUnit($port, $commodity) * GameConfig::float('blackmarket.sell_premium', 1.15);
    }

    /** @return array<string,?float> prezzo per merce nel settore (null = qui non la ritira) */
    public static function buyPrices(int $sectorId): array
    {
        $out = [];
        foreach (Economy::COMMODITIES as $c) {
            $out[$c] = self::buyPrice($sectorId, $c);
        }
        return $out;
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

        $unit = self::buyPrice((int) $player['sector_id'], $commodity);
        if ($unit === null) {
            return ['ok' => false, 'error' => 'Qui il porto vende gia\' questa merce: il mercato nero non la ritira.'];
        }
        $total = (int) round($unit * $qty);
        $alignHit = (int) floor(GameConfig::int('blackmarket.align_per_sale', -3) * max(1, $qty / 100));
        $alignHit = max($alignHit, -60);

        // Merce e pagamento si muovono insieme: la stiva viene scalata solo se
        // il carico c'e' davvero (il controllo sopra guarda una copia in
        // memoria, che due richieste concorrenti condividerebbero) e i crediti
        // seguono nella stessa transazione.
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::takeFromShip((int) $ship['id'], $col, $qty)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Carico insufficiente.'];
            }
            Wallet::credit((int) $player['id'], ['credits' => $total]);
            Wallet::charge((int) $player['id'], [], ['alignment' => $alignHit]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
            $pdo = Database::pdo();
            $pdo->beginTransaction();
            try {
                if (!Wallet::charge((int) $player['id'], ['credits' => $unit], ['alignment' => $alignBuy])) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => "Servono {$unit} cr."];
                }
                if (Database::run("UPDATE ships SET {$spec['col']} = 1 WHERE id = ? AND {$spec['col']} = 0", [$ship['id']])->rowCount() === 0) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Gia\' installato.'];
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            Achievements::award((int) $player['id'], 'black_market');
            return ['ok' => true, 'cost' => $unit, 'align' => $alignBuy];
        }

        // Prima era max(1, min(qty, spazio)): a tetto pieno lo spazio e' 0, il
        // max lo riportava a 1, e il controllo qui sotto non scattava mai.
        // Genesis e mine si compravano oltre il limite, uno alla volta, senza fine.
        $cap = GameConfig::int($spec['cap'], 9999);
        $grezza = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        $room = $cap - (int) $grezza[$spec['col']];
        if ($room <= 0) {
            return ['ok' => false, 'error' => 'Capacita\' massima raggiunta.'];
        }
        $qty = max(1, min($qty, $room));
        $cost = $unit * $qty;
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr."];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge((int) $player['id'], ['credits' => $cost], ['alignment' => $alignBuy])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Servono {$cost} cr."];
            }
            $consegnato = Database::run(
                "UPDATE ships SET {$spec['col']} = {$spec['col']} + ? WHERE id = ? AND {$spec['col']} + ? <= ?",
                [$qty, $ship['id'], $qty, $cap]
            )->rowCount() > 0;
            if (!$consegnato) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Capacita\' massima raggiunta.'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
        // Si toglie esattamente la taglia che si e' pagata. Prima si azzerava
        // tutto: una taglia aggiunta nel frattempo (un'uccisione parallela)
        // spariva senza essere pagata.
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge((int) $player['id'], ['credits' => $cost])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Ripulire la taglia costa {$cost} cr."];
            }
            if (Database::run('UPDATE players SET bounty = bounty - ? WHERE id = ? AND bounty >= ?', [$bounty, (int) $player['id'], $bounty])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'La taglia e\' cambiata: riprova.'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
