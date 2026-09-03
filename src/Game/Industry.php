<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 11 — profondità economica: raffineria (minerale+equ -> Componenti),
 * produzione di moduli su ricetta, modalità industria dei pianeti.
 */
final class Industry
{
    // --- raffineria ---------------------------------------------------

    /** @param array<string,mixed> $player @param array<string,mixed> $ship */
    public static function refine(array $player, array $ship, int $qty): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'La raffineria è allo StarDock.'];
        }
        $qty = max(1, min($qty, GameConfig::int('craft.refine_batch_max', 200)));
        $orePer = GameConfig::int('craft.refine_ore_per_component', 4);
        $equPer = GameConfig::int('craft.refine_equ_per_component', 3);

        $fresh = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        $maxByOre = $orePer > 0 ? intdiv((int) $fresh['hold_ore'], $orePer) : $qty;
        $maxByEqu = $equPer > 0 ? intdiv((int) $fresh['hold_equipment'], $equPer) : $qty;
        $qty = min($qty, $maxByOre, $maxByEqu);
        if ($qty <= 0) {
            return ['ok' => false, 'error' => "Servono {$orePer} minerale e {$equPer} equipaggiamento per Componente."];
        }
        // il costo in turni scala col lotto (niente più 200 Componenti a 3 turni)
        $perTurn = max(1, GameConfig::int('craft.refine_units_per_turn', 12));
        $cost = max(GameConfig::int('craft.refine_turn_cost', 3), (int) ceil($qty / $perTurn));

        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost} per {$qty} Componenti)."];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE ships SET hold_ore = hold_ore - ?, hold_equipment = hold_equipment - ? WHERE id = ?',
                [$qty * $orePer, $qty * $equPer, (int) $ship['id']]
            );
            Database::run('UPDATE players SET turns = turns - ?, components = components + ? WHERE id = ?',
                [$cost, $qty, (int) $player['id']]);
            Codex::unlock((int) $player['id'], 'production_chain');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'components' => $qty, 'ore' => $qty * $orePer, 'equ' => $qty * $equPer];
    }

    // --- ricette ---------------------------------------------------

    /** @return list<array<string,mixed>> ricette con flag affordable/unlocked */
    public static function recipes(array $player, array $ship): array
    {
        try {
            $rows = Database::all(
                'SELECT r.*, it.name AS item_name, it.rarity, it.category
                 FROM recipes r JOIN item_types it ON it.ckey = r.output_item
                 ORDER BY r.sort, r.ckey'
            );
        } catch (\Throwable) {
            return [];
        }
        $fresh = Database::first('SELECT hold_ore, hold_equipment, hold_organics FROM ships WHERE id = ?', [(int) $ship['id']]);
        foreach ($rows as &$r) {
            $r['unlocked'] = $r['min_faction'] === null
                || Faction::tierAtLeast((int) $player['id'], (string) $r['min_faction'], (string) ($r['min_tier'] ?? 'friendly'));
            $r['affordable'] = (int) $player['credits'] >= (int) $r['cost_credits']
                && (int) ($player['components'] ?? 0) >= (int) $r['cost_components']
                && (int) ($player['crystals'] ?? 0) >= (int) $r['cost_crystals']
                && (int) ($player['salvage'] ?? 0) >= (int) $r['cost_salvage']
                && (int) $fresh['hold_ore'] >= (int) $r['cargo_ore']
                && (int) $fresh['hold_equipment'] >= (int) $r['cargo_equ']
                && (int) $fresh['hold_organics'] >= (int) $r['cargo_org'];
        }
        return $rows;
    }

    /** @param array<string,mixed> $player @param array<string,mixed> $ship */
    public static function craft(array $player, array $ship, string $recipeKey): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'La produzione moduli è allo StarDock.'];
        }
        $r = Database::first(
            'SELECT r.*, it.name AS item_name FROM recipes r JOIN item_types it ON it.ckey = r.output_item WHERE r.ckey = ?',
            [$recipeKey]
        );
        if ($r === null) {
            return ['ok' => false, 'error' => 'Ricetta sconosciuta.'];
        }
        if ($r['min_faction'] !== null
            && !Faction::tierAtLeast((int) $player['id'], (string) $r['min_faction'], (string) ($r['min_tier'] ?? 'friendly'))) {
            return ['ok' => false, 'error' => 'Reputazione di fazione insufficiente per questa ricetta.'];
        }
        $cost = GameConfig::int('craft.turn_cost', 6);
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti (servono {$cost})."];
        }
        $fresh = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        $p = Database::first('SELECT * FROM players WHERE id = ?', [(int) $player['id']]);

        $lack = [];
        if ((int) $p['credits'] < (int) $r['cost_credits']) { $lack[] = 'crediti'; }
        if ((int) $p['components'] < (int) $r['cost_components']) { $lack[] = 'Componenti'; }
        if ((int) $p['crystals'] < (int) $r['cost_crystals']) { $lack[] = 'Cristalli'; }
        if ((int) $p['salvage'] < (int) $r['cost_salvage']) { $lack[] = 'Leghe'; }
        if ((int) $fresh['hold_ore'] < (int) $r['cargo_ore']) { $lack[] = 'minerale'; }
        if ((int) $fresh['hold_equipment'] < (int) $r['cargo_equ']) { $lack[] = 'equipaggiamento'; }
        if ((int) $fresh['hold_organics'] < (int) $r['cargo_org']) { $lack[] = 'organico'; }
        if ($lack !== []) {
            return ['ok' => false, 'error' => 'Manca: ' . implode(', ', $lack) . '.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE players SET turns = turns - ?, credits = credits - ?, components = components - ?, crystals = crystals - ?, salvage = salvage - ? WHERE id = ?',
                [$cost, (int) $r['cost_credits'], (int) $r['cost_components'], (int) $r['cost_crystals'], (int) $r['cost_salvage'], (int) $player['id']]
            );
            if ((int) $r['cargo_ore'] + (int) $r['cargo_equ'] + (int) $r['cargo_org'] > 0) {
                Database::run(
                    'UPDATE ships SET hold_ore = hold_ore - ?, hold_equipment = hold_equipment - ?, hold_organics = hold_organics - ? WHERE id = ?',
                    [(int) $r['cargo_ore'], (int) $r['cargo_equ'], (int) $r['cargo_org'], (int) $ship['id']]
                );
            }
            Database::run("INSERT INTO player_items (player_id, item_key, source) VALUES (?, ?, 'shop')",
                [(int) $player['id'], $r['output_item']]);
            Codex::unlock((int) $player['id'], 'production_chain');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $r['item_name']];
    }

    // --- industria planetaria -------------------------------------

    public static function togglePlanet(array $player, int $planetId, bool $on): array
    {
        $p = Database::first('SELECT * FROM planets WHERE id = ? AND destroyed = 0', [$planetId]);
        if ($p === null || !Planets::isOwn($p, $player)) {
            return ['ok' => false, 'error' => 'Non è un tuo pianeta.'];
        }
        Database::run('UPDATE planets SET industry = ?, last_industry_at = NOW() WHERE id = ?', [$on ? 1 : 0, $planetId]);
        return ['ok' => true, 'on' => $on, 'name' => $p['name']];
    }

    /** Tick: i pianeti in industria convertono scorte di minerale in Componenti per il proprietario. */
    public static function tick(): array
    {
        $out = ['planets' => 0, 'components' => 0];
        try {
            $perDay = GameConfig::int('craft.planet_component_per_day', 48);
            $orePer = GameConfig::int('craft.planet_ore_per_component', 3);
            foreach (Database::all(
                "SELECT id, owner_player_id, stock_ore, last_industry_at FROM planets
                 WHERE destroyed = 0 AND industry = 1 AND owner_player_id IS NOT NULL"
            ) as $pl) {
                $since = $pl['last_industry_at'] !== null ? strtotime((string) $pl['last_industry_at']) : time() - 3600;
                $elapsed = max(0, time() - $since);
                $target = (int) floor($perDay * $elapsed / 86400);
                $byStock = $orePer > 0 ? intdiv((int) $pl['stock_ore'], $orePer) : $target;
                $made = min($target, $byStock);
                if ($made <= 0) {
                    continue;
                }
                Database::run('UPDATE planets SET stock_ore = stock_ore - ?, last_industry_at = NOW() WHERE id = ?',
                    [$made * $orePer, (int) $pl['id']]);
                Database::run('UPDATE players SET components = components + ? WHERE id = ?',
                    [$made, (int) $pl['owner_player_id']]);
                $out['planets']++;
                $out['components'] += $made;
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
    }
}
