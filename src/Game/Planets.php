<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Pianeti: creazione via Genesi, coloni e produzione (lazy dai timestamp),
 * Citadel, Quasar, trasferimenti di risorse/coloni/crediti.
 */
final class Planets
{
    /** Costi Citadel per livello 1..6: [coloni, ore, equ, crediti, ore_di_lavoro] */
    private const CITADEL_COSTS = [
        1 => [1000,   500,   400,   10000,   4],
        2 => [5000,   2000,  1500,  50000,   12],
        3 => [15000,  6000,  5000,  200000,  24],
        4 => [30000,  15000, 12000, 500000,  48],
        5 => [50000,  30000, 25000, 1500000, 96],
        6 => [80000,  50000, 40000, 4000000, 168],
    ];

    private const NAME_ROOTS = ['Aurora', 'Nyx', 'Terminus', 'Eos', 'Helios', 'Cerbero', 'Talos', 'Iperione',
        'Cronos', 'Gaia', 'Erebo', 'Tartaro', 'Selene', 'Atlante', 'Prometeo', 'Pandora', 'Elara', 'Teti'];

    /** @return list<array<string,mixed>> */
    public static function types(): array
    {
        return Database::all('SELECT * FROM planet_types ORDER BY spawn_weight DESC');
    }

    /** @return array<string,mixed>|null */
    public static function typeInfo(string $key): ?array
    {
        return Database::first('SELECT * FROM planet_types WHERE ckey = ?', [$key]);
    }

    /** @return array<string,mixed>|null pianeta con produzione/citadel aggiornati */
    public static function get(int $id): ?array
    {
        $p = Database::first(
            'SELECT pl.*, pt.name AS type_name, pt.descr AS type_descr, pt.max_col, pt.breed_rate,
                    pt.prod_ore, pt.prod_org, pt.prod_equ, pt.citadel_ok,
                    o.handle AS owner_handle, c.name AS corp_name, c.tag AS corp_tag
             FROM planets pl
             JOIN planet_types pt ON pt.ckey = pl.type_key
             LEFT JOIN players o ON o.id = pl.owner_player_id
             LEFT JOIN corporations c ON c.id = pl.corp_id
             WHERE pl.id = ? AND pl.destroyed = 0',
            [$id]
        );
        return $p === null ? null : self::advance($p);
    }

    /** @return list<array<string,mixed>> */
    public static function inSector(int $sectorId): array
    {
        $rows = Database::all(
            'SELECT pl.id FROM planets pl WHERE pl.sector_id = ? AND pl.destroyed = 0 ORDER BY pl.id',
            [$sectorId]
        );
        return array_values(array_filter(array_map(static fn ($r) => self::get((int) $r['id']), $rows)));
    }

    /**
     * Avanza coloni (crescita), produzione e completamento Citadel dal
     * timestamp last_prod_at, e persiste.
     *
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    public static function advance(array $p): array
    {
        $elapsed = time() - strtotime((string) $p['last_prod_at']);
        $hours = $elapsed / 3600.0;

        // completamento Citadel
        if ($p['citadel_upgrade_to'] !== null && $p['citadel_ready_at'] !== null
            && strtotime((string) $p['citadel_ready_at']) <= time()) {
            $p['citadel_level'] = (int) $p['citadel_upgrade_to'];
            $p['citadel_upgrade_to'] = null;
            $p['citadel_ready_at'] = null;
            Database::run(
                'UPDATE planets SET citadel_level = ?, citadel_upgrade_to = NULL, citadel_ready_at = NULL WHERE id = ?',
                [$p['citadel_level'], $p['id']]
            );
            if ((int) ($p['owner_player_id'] ?? 0) > 0) {
                Live::alert((int) $p['owner_player_id'], 'citadel', 'Citadel completata',
                    "{$p['name']} — Citadel livello {$p['citadel_level']} operativa.", '/gioco/pianeta/' . $p['id']);
            }
            Live::corp((int) ($p['corp_id'] ?? 0) ?: null, 'citadel', 'Citadel completata', "{$p['name']} — livello {$p['citadel_level']}.");
        }

        if ($hours < 0.01) {
            return $p;
        }

        $total = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
        $max = (int) $p['max_col'];

        // crescita coloni -> vanno negli "inattivi"
        if ($total > 0 && $total < $max) {
            $grown = min($max, (int) floor($total * ((1.0 + (float) $p['breed_rate']) ** $hours)));
            $delta = $grown - $total;
            if ($delta > 0) {
                $p['col_idle'] = (int) $p['col_idle'] + $delta;
            }
        }

        // produzione
        $capMult = GameConfig::int('planet.stock_cap_mult', 20);
        $cap = $max * $capMult;
        foreach ([['col_ore', 'prod_ore', 'stock_ore'], ['col_org', 'prod_org', 'stock_org'], ['col_equ', 'prod_equ', 'stock_equ']] as [$colc, $prodc, $stockc]) {
            $add = (int) floor((int) $p[$colc] * (float) $p[$prodc] * $hours);
            if ($add > 0) {
                $p[$stockc] = min($cap, (int) $p[$stockc] + $add);
            }
        }

        Database::run(
            'UPDATE planets SET col_idle = ?, stock_ore = ?, stock_org = ?, stock_equ = ?, last_prod_at = NOW() WHERE id = ?',
            [$p['col_idle'], $p['stock_ore'], $p['stock_org'], $p['stock_equ'], $p['id']]
        );
        return $p;
    }

    public static function isOwn(array $p, array $player): bool
    {
        if ((int) ($p['owner_player_id'] ?? 0) === (int) $player['id']) {
            return true;
        }
        $cid = (int) ($p['corp_id'] ?? 0);
        return $cid > 0 && $cid === (Corp::corpIdOf((int) $player['id']) ?? -1);
    }

    // --- creazione -----------------------------------------------------

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function genesis(array $player, array $ship): array
    {
        if ((int) $ship['genesis'] <= 0) {
            return ['ok' => false, 'error' => 'Nessun siluro Genesi a bordo.'];
        }
        $sectorId = (int) $player['sector_id'];
        $max = GameConfig::int('planet.max_per_sector', 5);
        $here = (int) (Database::first('SELECT COUNT(*) c FROM planets WHERE sector_id = ? AND destroyed = 0', [$sectorId])['c'] ?? 0);
        if ($here >= $max) {
            return ['ok' => false, 'error' => "Il settore ha gia\' {$max} pianeti."];
        }

        $type = self::rollType();
        $name = self::NAME_ROOTS[array_rand(self::NAME_ROOTS)] . ' ' . chr(mt_rand(65, 90)) . mt_rand(1, 9);
        $corpId = Corp::corpIdOf((int) $player['id']);

        Database::run('UPDATE ships SET genesis = genesis - 1 WHERE id = ?', [$ship['id']]);
        Database::run(
            'INSERT INTO planets (sector_id, name, type_key, owner_player_id, corp_id, created_by, col_idle, last_prod_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW())',
            [$sectorId, $name, $type, $player['id'], $corpId, $player['id']]
        );
        $id = Database::lastInsertId();
        Achievements::award((int) $player['id'], 'first_planet');
        Live::sector($sectorId, 'planet_new', null, "Un nuovo pianeta ({$name}) si e' formato nel settore");
        return ['ok' => true, 'planet_id' => $id, 'name' => $name, 'type' => $type];
    }

    private static function rollType(): string
    {
        $types = self::types();
        $total = array_sum(array_map(static fn ($t) => (int) $t['spawn_weight'], $types));
        $r = mt_rand(1, max(1, $total));
        foreach ($types as $t) {
            $r -= (int) $t['spawn_weight'];
            if ($r <= 0) {
                return (string) $t['ckey'];
            }
        }
        return 'M';
    }

    // --- trasferimenti ----------------------------------------------

    /**
     * Coloni fra nave e pianeta. $dir: 'down' (nave->pianeta) o 'up'.
     * $bucket: ore|org|equ|idle
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function moveColonists(array $player, array $ship, int $planetId, string $bucket, int $qty, string $dir): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $player['sector_id'] !== (int) $p['sector_id']) {
            return ['ok' => false, 'error' => 'Non sei nel settore del pianeta.'];
        }
        $col = match ($bucket) {
            'ore' => 'col_ore', 'org' => 'col_org', 'equ' => 'col_equ', 'idle' => 'col_idle',
            default => null,
        };
        if ($col === null || $qty <= 0) {
            return ['ok' => false, 'error' => 'Parametri non validi.'];
        }

        if ($dir === 'down') {
            if ($qty > (int) $ship['hold_colonists']) {
                return ['ok' => false, 'error' => 'Coloni a bordo insufficienti.'];
            }
            $total = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
            if ($total + $qty > (int) $p['max_col']) {
                return ['ok' => false, 'error' => 'Capacita\' del pianeta superata (max ' . (int) $p['max_col'] . ').'];
            }
            Database::run('UPDATE ships SET hold_colonists = hold_colonists - ? WHERE id = ?', [$qty, $ship['id']]);
            Database::run("UPDATE planets SET {$col} = {$col} + ? WHERE id = ?", [$qty, $planetId]);
        } else {
            if ($qty > (int) $p[$col]) {
                return ['ok' => false, 'error' => 'Coloni nella categoria insufficienti.'];
            }
            $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
            if ($qty > $room) {
                return ['ok' => false, 'error' => 'Stive insufficienti.'];
            }
            Database::run("UPDATE planets SET {$col} = {$col} - ? WHERE id = ?", [$qty, $planetId]);
            Database::run('UPDATE ships SET hold_colonists = hold_colonists + ? WHERE id = ?', [$qty, $ship['id']]);
        }
        return ['ok' => true, 'moved' => $qty, 'bucket' => $bucket, 'dir' => $dir];
    }

    /** Riassegna coloni fra categorie sul pianeta. */
    public static function assignColonists(array $player, int $planetId, string $from, string $to, int $qty): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        $map = ['ore' => 'col_ore', 'org' => 'col_org', 'equ' => 'col_equ', 'idle' => 'col_idle'];
        if (!isset($map[$from], $map[$to]) || $from === $to || $qty <= 0) {
            return ['ok' => false, 'error' => 'Parametri non validi.'];
        }
        if ($qty > (int) $p[$map[$from]]) {
            return ['ok' => false, 'error' => 'Coloni insufficienti nella categoria di partenza.'];
        }
        Database::run("UPDATE planets SET {$map[$from]} = {$map[$from]} - ?, {$map[$to]} = {$map[$to]} + ? WHERE id = ?", [$qty, $qty, $planetId]);
        return ['ok' => true, 'moved' => $qty];
    }

    /**
     * Risorse prodotte fra pianeta e nave. $commodity: ore|organics|equipment
     * $dir: 'load' (pianeta->nave) o 'unload' (nave->pianeta).
     */
    public static function moveResources(array $player, array $ship, int $planetId, string $commodity, int $qty, string $dir): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $player['sector_id'] !== (int) $p['sector_id']) {
            return ['ok' => false, 'error' => 'Non sei nel settore del pianeta.'];
        }
        $stockCol = match ($commodity) {
            'ore' => 'stock_ore', 'organics' => 'stock_org', 'equipment' => 'stock_equ',
            default => null,
        };
        if ($stockCol === null || $qty <= 0) {
            return ['ok' => false, 'error' => 'Parametri non validi.'];
        }
        $shipCol = Economy::shipColumn($commodity);

        if ($dir === 'load') {
            if ($qty > (int) $p[$stockCol]) {
                return ['ok' => false, 'error' => 'Scorte del pianeta insufficienti.'];
            }
            $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
            if ($qty > $room) {
                return ['ok' => false, 'error' => 'Stive insufficienti.'];
            }
            Database::run("UPDATE planets SET {$stockCol} = {$stockCol} - ? WHERE id = ?", [$qty, $planetId]);
            Database::run("UPDATE ships SET {$shipCol} = {$shipCol} + ? WHERE id = ?", [$qty, $ship['id']]);
        } else {
            if ($qty > (int) $ship[$shipCol]) {
                return ['ok' => false, 'error' => 'Carico insufficiente.'];
            }
            Database::run("UPDATE ships SET {$shipCol} = {$shipCol} - ? WHERE id = ?", [$qty, $ship['id']]);
            Database::run("UPDATE planets SET {$stockCol} = {$stockCol} + ? WHERE id = ?", [$qty, $planetId]);
        }
        return ['ok' => true, 'moved' => $qty];
    }

    /** Tesoreria Citadel (serve Citadel >= 1). $dir: deposit|withdraw */
    public static function treasury(array $player, int $planetId, int $amount, string $dir): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $p['citadel_level'] < 1) {
            return ['ok' => false, 'error' => 'Serve una Citadel di livello 1.'];
        }
        if ($amount <= 0 || (int) $player['sector_id'] !== (int) $p['sector_id']) {
            return ['ok' => false, 'error' => 'Non sei sul pianeta o importo non valido.'];
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pc = (int) Database::first('SELECT credits FROM players WHERE id = ? FOR UPDATE', [$player['id']])['credits'];
            $tc = (int) Database::first('SELECT credits FROM planets WHERE id = ? FOR UPDATE', [$planetId])['credits'];
            if ($dir === 'deposit') {
                if ($pc < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Crediti a bordo insufficienti.'];
                }
                $pc -= $amount; $tc += $amount;
            } else {
                if ($tc < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Tesoreria insufficiente.'];
                }
                $pc += $amount; $tc -= $amount;
            }
            Database::run('UPDATE players SET credits = ? WHERE id = ?', [$pc, $player['id']]);
            Database::run('UPDATE planets SET credits = ? WHERE id = ?', [$tc, $planetId]);
            $pdo->commit();
            return ['ok' => true, 'credits' => $pc, 'treasury' => $tc];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // --- Citadel / Quasar / garrison -------------------------------

    /** @return array{level:int, costs:array{col:int,ore:int,equ:int,cr:int,hours:int}}|null */
    public static function nextCitadel(array $p): ?array
    {
        $next = (int) $p['citadel_level'] + 1;
        if ($next > 6 || !isset(self::CITADEL_COSTS[$next]) || !(int) $p['citadel_ok']) {
            return null;
        }
        $m = GameConfig::float('planet.citadel_cost_mult', 1.0);
        [$col, $ore, $equ, $cr, $hours] = self::CITADEL_COSTS[$next];
        return ['level' => $next, 'costs' => [
            'col' => (int) round($col * $m), 'ore' => (int) round($ore * $m),
            'equ' => (int) round($equ * $m), 'cr' => (int) round($cr * $m), 'hours' => $hours,
        ]];
    }

    public static function upgradeCitadel(array $player, int $planetId): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $player['sector_id'] !== (int) $p['sector_id']) {
            return ['ok' => false, 'error' => 'Devi essere nel settore del pianeta.'];
        }
        if ($p['citadel_upgrade_to'] !== null) {
            return ['ok' => false, 'error' => 'Un potenziamento della Citadel e\' gia\' in corso.'];
        }
        $nx = self::nextCitadel($p);
        if ($nx === null) {
            return ['ok' => false, 'error' => 'Citadel al livello massimo o non costruibile su questo pianeta.'];
        }
        $c = $nx['costs'];
        $totalCol = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
        if ($totalCol < $c['col']) {
            return ['ok' => false, 'error' => "Servono {$c['col']} coloni sul pianeta."];
        }
        if ((int) $p['stock_ore'] < $c['ore'] || (int) $p['stock_equ'] < $c['equ']) {
            return ['ok' => false, 'error' => "Servono {$c['ore']} minerale e {$c['equ']} equipaggiamento in magazzino."];
        }
        $treasury = (int) $p['credits'];
        $fromShip = 0;
        if ($treasury < $c['cr']) {
            $fromShip = $c['cr'] - $treasury;
            if ((int) $player['credits'] < $fromShip) {
                return ['ok' => false, 'error' => "Servono {$c['cr']} cr (tesoreria + a bordo)."];
            }
        }

        Database::run('UPDATE planets SET stock_ore = stock_ore - ?, stock_equ = stock_equ - ?, credits = GREATEST(0, credits - ?), citadel_upgrade_to = ?, citadel_ready_at = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?',
            [$c['ore'], $c['equ'], min($treasury, $c['cr']), $nx['level'], $c['hours'], $planetId]);
        if ($fromShip > 0) {
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$fromShip, $player['id']]);
        }
        return ['ok' => true, 'level' => $nx['level'], 'hours' => $c['hours']];
    }

    public static function buildQuasar(array $player, int $planetId): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $p['citadel_level'] < 3) {
            return ['ok' => false, 'error' => 'Serve una Citadel di livello 3.'];
        }
        $cr = GameConfig::int('planet.quasar_cost_credits', 250000);
        $equ = GameConfig::int('planet.quasar_cost_equ', 4000);
        if ((int) $p['stock_equ'] < $equ) {
            return ['ok' => false, 'error' => "Servono {$equ} equipaggiamento in magazzino."];
        }
        if ((int) $p['credits'] + (int) $player['credits'] < $cr) {
            return ['ok' => false, 'error' => "Servono {$cr} cr."];
        }
        $fromT = min((int) $p['credits'], $cr);
        $fromS = $cr - $fromT;
        Database::run('UPDATE planets SET stock_equ = stock_equ - ?, credits = credits - ?, quasar_level = quasar_level + 1 WHERE id = ?', [$equ, $fromT, $planetId]);
        if ($fromS > 0) {
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$fromS, $player['id']]);
        }
        return ['ok' => true, 'quasar_level' => (int) $p['quasar_level'] + 1];
    }

    /** Sposta caccia fra nave e guarnigione del pianeta. $dir: garrison|recall */
    public static function garrison(array $player, array $ship, int $planetId, int $qty, string $dir): array
    {
        $p = self::requireOwn($player, $planetId);
        if (!is_array($p)) {
            return $p;
        }
        if ((int) $player['sector_id'] !== (int) $p['sector_id'] || $qty <= 0) {
            return ['ok' => false, 'error' => 'Non sei sul pianeta o quantita\' non valida.'];
        }
        if ($dir === 'garrison') {
            if ($qty > (int) $ship['fighters']) {
                return ['ok' => false, 'error' => 'Caccia a bordo insufficienti.'];
            }
            $maxG = GameConfig::int('planet.garrison_per_citadel', 3000) * max(1, (int) $p['citadel_level'] + 1);
            if ((int) $p['fighters'] + $qty > $maxG) {
                return ['ok' => false, 'error' => "Guarnigione massima {$maxG} (dipende dalla Citadel)."];
            }
            Database::run('UPDATE ships SET fighters = fighters - ? WHERE id = ?', [$qty, $ship['id']]);
            Database::run('UPDATE planets SET fighters = fighters + ? WHERE id = ?', [$qty, $planetId]);
        } else {
            if ($qty > (int) $p['fighters']) {
                return ['ok' => false, 'error' => 'Guarnigione insufficiente.'];
            }
            Database::run('UPDATE planets SET fighters = fighters - ? WHERE id = ?', [$qty, $planetId]);
            Database::run('UPDATE ships SET fighters = fighters + ? WHERE id = ?', [$qty, $ship['id']]);
        }
        return ['ok' => true, 'moved' => $qty, 'dir' => $dir];
    }

    // --- coloni dallo StarDock (Terra) ---------------------------

    /** @param array<string,mixed> $player @param array<string,mixed> $ship */
    public static function pickupColonists(array $player, array $ship, int $qty): array
    {
        if (!Database::first('SELECT 1 x FROM sectors WHERE id = ? AND is_stardock = 1', [(int) $player['sector_id']])) {
            return ['ok' => false, 'error' => 'I coloni si imbarcano solo allo StarDock.'];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantita\' non valida.'];
        }
        $perDay = GameConfig::int('planet.colonist_pickup_per_day', 5000);
        $day = TurnManager::gameDay();
        $taken = (int) (Database::first(
            "SELECT COALESCE(SUM(qty),0) s FROM trade_log WHERE player_id = ? AND commodity = 'organics' AND action = 'buy' AND port_id = 0 AND DATE(created_at) = ?",
            [$player['id'], $day]
        )['s'] ?? 0);
        // usa un marcatore semplice: righe trade_log con port_id=0 = ritiri coloni
        $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
        $qty = min($qty, $room, max(0, $perDay - $taken));
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Nessuno spazio o quota giornaliera coloni esaurita.'];
        }
        Database::run('UPDATE ships SET hold_colonists = hold_colonists + ? WHERE id = ?', [$qty, $ship['id']]);
        Database::run(
            "INSERT INTO trade_log (player_id, port_id, sector_id, commodity, action, qty, unit_price, total, fair_total)
             VALUES (?, 0, ?, 'organics', 'buy', ?, 0, 0, 0)",
            [$player['id'], (int) $player['sector_id'], $qty]
        );
        return ['ok' => true, 'loaded' => $qty, 'remaining_today' => max(0, $perDay - $taken - $qty)];
    }

    // --- helper ---------------------------------------------------

    /**
     * @param array<string,mixed> $player
     * @return array<string,mixed>|array{ok:false,error:string}
     */
    private static function requireOwn(array $player, int $planetId): array
    {
        $p = self::get($planetId);
        if ($p === null) {
            return ['ok' => false, 'error' => 'Pianeta inesistente.'];
        }
        if (!self::isOwn($p, $player)) {
            return ['ok' => false, 'error' => 'Il pianeta non e\' tuo (ne\' della tua corporazione).'];
        }
        return $p;
    }

    /** Passaggio dal tick: aggiorna i pianeti "vivi" o con Citadel in coda. */
    public static function tickDue(int $limit = 500): int
    {
        $rows = Database::all(
            'SELECT id FROM planets
             WHERE destroyed = 0 AND ((col_ore + col_org + col_equ + col_idle) > 0 OR citadel_upgrade_to IS NOT NULL)
             ORDER BY last_prod_at ASC LIMIT ?',
            [$limit]
        );
        foreach ($rows as $r) {
            self::get((int) $r['id']);
        }
        return count($rows);
    }
}
