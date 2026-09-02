<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 7 — officina moduli (allo StarDock): installa/rimuovi/smonta/potenzia.
 * L'inventario non installato vive in player_items, gli installati in
 * ship_modules. Il materiale è players.salvage.
 */
final class Modules
{
    private const CATS = ['weapon', 'defense', 'drive', 'computer', 'utility'];

    private const CAT_LABEL = [
        'weapon' => 'Armi', 'defense' => 'Difesa', 'drive' => 'Propulsione',
        'computer' => 'Computer', 'utility' => 'Utility',
    ];

    public static function catLabel(string $c): string
    {
        return self::CAT_LABEL[$c] ?? $c;
    }

    /** @return list<array<string,mixed>> inventario non installato del giocatore */
    public static function inventory(int $playerId): array
    {
        return Database::all(
            'SELECT pi.id, pi.item_key, pi.rolled, pi.source, pi.acquired_at,
                    it.name, it.category, it.rarity, it.effects, it.base_salvage, it.descr
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.player_id = ?
             ORDER BY FIELD(it.rarity,\'precursor\',\'xeno\',\'exp\',\'mil\',\'civ\'), it.category, pi.id',
            [$playerId]
        );
    }

    /** @return list<array<string,mixed>> moduli installati sulla nave */
    public static function installed(int $shipId): array
    {
        return Database::all(
            'SELECT sm.id, sm.slot, sm.item_key, sm.rolled,
                    it.name, it.category, it.rarity, it.effects, it.base_salvage, it.descr
             FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
             WHERE sm.ship_id = ?
             ORDER BY FIELD(sm.slot,\'weapon\',\'defense\',\'drive\',\'computer\',\'utility\'), sm.id',
            [$shipId]
        );
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function install(array $player, array $ship, int $itemId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'L\'officina moduli è solo allo StarDock.'];
        }
        if (($ship['type_key'] ?? '') === 'escape_pod') {
            return ['ok' => false, 'error' => 'La capsula non ha slot per moduli.'];
        }
        $it = Database::first(
            'SELECT pi.id, pi.item_key, pi.rolled, it.name, it.category
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.id = ? AND pi.player_id = ?',
            [$itemId, (int) $player['id']]
        );
        if ($it === null) {
            return ['ok' => false, 'error' => 'Modulo non trovato nell\'inventario.'];
        }
        $cat = (string) $it['category'];
        $slots = ShipStats::slots((string) $ship['type_key']);
        $used = (int) (Database::first(
            'SELECT COUNT(*) c FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
             WHERE sm.ship_id = ? AND it.category = ?',
            [(int) $ship['id'], $cat]
        )['c'] ?? 0);
        if ($used >= ($slots[$cat] ?? 0)) {
            return ['ok' => false, 'error' => 'Nessuno slot ' . self::catLabel($cat) . ' libero su questo scafo.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO ship_modules (ship_id, slot, item_key, rolled) VALUES (?, ?, ?, ?)',
                [(int) $ship['id'], $cat, $it['item_key'], $it['rolled']]
            );
            Database::run('DELETE FROM player_items WHERE id = ?', [$itemId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $it['name'], 'slot' => $cat];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function remove(array $player, array $ship, int $modId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'L\'officina moduli è solo allo StarDock.'];
        }
        $m = Database::first(
            'SELECT sm.id, sm.item_key, sm.rolled, it.name FROM ship_modules sm
             JOIN item_types it ON it.ckey = sm.item_key
             WHERE sm.id = ? AND sm.ship_id = ?',
            [$modId, (int) $ship['id']]
        );
        if ($m === null) {
            return ['ok' => false, 'error' => 'Modulo non installato su questa nave.'];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, ?, ?, ?)',
                [(int) $player['id'], $m['item_key'], $m['rolled'], 'shop']
            );
            Database::run('DELETE FROM ship_modules WHERE id = ?', [$modId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $m['name']];
    }

    /** @param array<string,mixed> $player */
    public static function scrap(array $player, int $itemId): array
    {
        $it = Database::first(
            'SELECT pi.id, it.name, it.base_salvage, it.rarity
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.id = ? AND pi.player_id = ?',
            [$itemId, (int) $player['id']]
        );
        if ($it === null) {
            return ['ok' => false, 'error' => 'Modulo non trovato.'];
        }
        $gain = (int) $it['base_salvage'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM player_items WHERE id = ?', [$itemId]);
            Database::run('UPDATE players SET salvage = salvage + ? WHERE id = ?', [$gain, (int) $player['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $it['name'], 'salvage' => $gain];
    }

    /**
     * Potenzia un modulo dell'inventario di una fascia (civ→mil→exp→xeno→precursor).
     *
     * @param array<string,mixed> $player
     */
    public static function upgrade(array $player, int $itemId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'L\'officina moduli è solo allo StarDock.'];
        }
        $it = Database::first(
            'SELECT pi.id, pi.rolled, it.ckey, it.name, it.category, it.rarity, it.effects
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.id = ? AND pi.player_id = ?',
            [$itemId, (int) $player['id']]
        );
        if ($it === null) {
            return ['ok' => false, 'error' => 'Modulo non trovato nell\'inventario.'];
        }
        $order = Loot::RARITIES;
        $ci = array_search($it['rarity'], $order, true);
        if (!is_int($ci) || $ci >= count($order) - 1) {
            return ['ok' => false, 'error' => 'Questo modulo è già al massimo della rarità.'];
        }
        $next = $order[$ci + 1];

        // un modello di destinazione nella stessa categoria e fascia superiore
        $target = Database::first(
            'SELECT ckey, name, effects FROM item_types WHERE category = ? AND rarity = ? ORDER BY RAND() LIMIT 1',
            [$it['category'], $next]
        );
        if ($target === null) {
            return ['ok' => false, 'error' => 'Nessun modello superiore disponibile in questa categoria.'];
        }

        $costCr  = self::tierCost('loot.upgrade_cost_credits', (string) $it['rarity']);
        $costMat = self::tierCost('loot.upgrade_cost_salvage', (string) $it['rarity']);
        if ((int) $player['credits'] < $costCr) {
            return ['ok' => false, 'error' => "Servono {$costCr} cr."];
        }
        if ((int) ($player['salvage'] ?? 0) < $costMat) {
            return ['ok' => false, 'error' => "Servono {$costMat} Leghe di recupero."];
        }

        $rolled = json_encode(ShipStats::decode($target['effects']) ?? [], JSON_UNESCAPED_UNICODE);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET credits = credits - ?, salvage = salvage - ? WHERE id = ?',
                [$costCr, $costMat, (int) $player['id']]);
            Database::run('UPDATE player_items SET item_key = ?, rolled = ? WHERE id = ?',
                [$target['ckey'], $rolled, $itemId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $target['name'], 'rarity' => $next,
                'label' => Loot::RARITY_LABEL[$next] ?? $next, 'cost' => $costCr, 'mat' => $costMat];
    }

    private static function tierCost(string $key, string $rarity): int
    {
        foreach (explode(',', GameConfig::str($key, '')) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2 && $p[0] === $rarity) {
                return max(0, (int) $p[1]);
            }
        }
        return 0;
    }
}
