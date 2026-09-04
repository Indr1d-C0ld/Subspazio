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

    /** Minuti di lavorazione per una data rarita' di modulo. */
    public static function jobMinutes(string $rarity): int
    {
        $map = [];
        foreach (explode(',', GameConfig::str('craft.job_minutes', 'civ:4,mil:12,exp:35,xeno:90,precursor:180')) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2) {
                $map[$p[0]] = max(1, (int) $p[1]);
            }
        }
        return $map[$rarity] ?? ($map['mil'] ?? 12);
    }

    /** @return list<array<string,mixed>> lavori in corso del giocatore, dal piu' vicino al completamento. */
    public static function craftJobs(int $playerId): array
    {
        try {
            $rows = Database::all(
                'SELECT id, recipe_key, item_key, item_name, rarity, ready_at, created_at,
                        GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), ready_at)) AS secs_left
                 FROM craft_jobs WHERE player_id = ? ORDER BY ready_at ASC',
                [$playerId]
            );
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['ready'] = (int) $r['secs_left'] <= 0;
        }
        return $rows;
    }

    /**
     * Mette in coda la produzione di un modulo: i costi vengono scalati
     * subito, il modulo arriva quando il lavoro matura sul tick.
     *
     * @param array<string,mixed> $player @param array<string,mixed> $ship
     */
    public static function craft(array $player, array $ship, string $recipeKey): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'La produzione moduli è allo StarDock.'];
        }
        $r = Database::first(
            'SELECT r.*, it.name AS item_name, it.rarity AS rarity FROM recipes r JOIN item_types it ON it.ckey = r.output_item WHERE r.ckey = ?',
            [$recipeKey]
        );
        if ($r === null) {
            return ['ok' => false, 'error' => 'Ricetta sconosciuta.'];
        }
        if ($r['min_faction'] !== null
            && !Faction::tierAtLeast((int) $player['id'], (string) $r['min_faction'], (string) ($r['min_tier'] ?? 'friendly'))) {
            return ['ok' => false, 'error' => 'Reputazione di fazione insufficiente per questa ricetta.'];
        }

        $maxJobs = max(1, GameConfig::int('craft.max_jobs', 3));
        $running = (int) (Database::first('SELECT COUNT(*) c FROM craft_jobs WHERE player_id = ?', [(int) $player['id']])['c'] ?? 0);
        if ($running >= $maxJobs) {
            return ['ok' => false, 'error' => "Officina al completo: {$maxJobs} lavori già in corso."];
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

        $minutes = self::jobMinutes((string) $r['rarity']);
        $refund = [
            'credits'    => (int) $r['cost_credits'],
            'components' => (int) $r['cost_components'],
            'crystals'   => (int) $r['cost_crystals'],
            'salvage'    => (int) $r['cost_salvage'],
            'cargo_ore'  => (int) $r['cargo_ore'],
            'cargo_equ'  => (int) $r['cargo_equ'],
            'cargo_org'  => (int) $r['cargo_org'],
        ];

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
            Database::run(
                "INSERT INTO craft_jobs (player_id, recipe_key, item_key, item_name, rarity, cost, ready_at)
                 VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
                [
                    (int) $player['id'], $recipeKey, (string) $r['output_item'], (string) $r['item_name'],
                    (string) $r['rarity'], json_encode($refund, JSON_UNESCAPED_UNICODE), $minutes,
                ]
            );
            Codex::unlock((int) $player['id'], 'production_chain');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $r['item_name'], 'minutes' => $minutes];
    }

    /** Annulla un lavoro in corso: rimborso pieno dei materiali (non dei turni). */
    public static function cancelJob(array $player, int $jobId): array
    {
        $j = Database::first('SELECT * FROM craft_jobs WHERE id = ? AND player_id = ?', [$jobId, (int) $player['id']]);
        if ($j === null) {
            return ['ok' => false, 'error' => 'Lavoro inesistente.'];
        }
        $c = json_decode((string) ($j['cost'] ?? '{}'), true) ?: [];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE players SET credits = credits + ?, components = components + ?, crystals = crystals + ?, salvage = salvage + ? WHERE id = ?',
                [(int) ($c['credits'] ?? 0), (int) ($c['components'] ?? 0), (int) ($c['crystals'] ?? 0), (int) ($c['salvage'] ?? 0), (int) $player['id']]
            );
            if ((int) ($c['cargo_ore'] ?? 0) + (int) ($c['cargo_equ'] ?? 0) + (int) ($c['cargo_org'] ?? 0) > 0) {
                Database::run(
                    'UPDATE ships SET hold_ore = hold_ore + ?, hold_equipment = hold_equipment + ?, hold_organics = hold_organics + ? WHERE id = ?',
                    [(int) ($c['cargo_ore'] ?? 0), (int) ($c['cargo_equ'] ?? 0), (int) ($c['cargo_org'] ?? 0), (int) $player['ship_id']]
                );
            }
            Database::run('DELETE FROM craft_jobs WHERE id = ?', [$jobId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => (string) $j['item_name']];
    }

    /** Tick: consegna i moduli dei lavori maturati. */
    public static function craftJobsTick(): array
    {
        $out = ['delivered' => 0];
        try {
            $jobs = Database::all('SELECT * FROM craft_jobs WHERE ready_at <= NOW() ORDER BY ready_at ASC LIMIT 200');
            foreach ($jobs as $j) {
                $pid = (int) $j['player_id'];
                Database::run("INSERT INTO player_items (player_id, item_key, source) VALUES (?, ?, 'shop')",
                    [$pid, (string) $j['item_key']]);
                Database::run('DELETE FROM craft_jobs WHERE id = ?', [(int) $j['id']]);
                ShipLog::write($pid, 'system', 'info',
                    "Officina: {$j['item_name']} completato",
                    "La fabbricazione di «{$j['item_name']}» è terminata. Il modulo è nell'inventario, pronto da installare allo StarDock.");
                Live::alert($pid, 'craft', 'Modulo pronto', "{$j['item_name']} è nell'inventario moduli.", '/gioco/moduli');
                $out['delivered']++;
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
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
