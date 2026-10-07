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
            'SELECT pi.id, pi.item_key, pi.rolled, pi.broken_at, pi.source, pi.acquired_at,
                    it.name, it.category, it.family, it.rarity, it.effects, it.base_salvage, it.descr
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
            'SELECT sm.id, sm.slot, sm.item_key, sm.rolled, sm.broken_at,
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
            'SELECT pi.id, pi.item_key, pi.rolled, pi.broken_at, it.name, it.category
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.id = ? AND pi.player_id = ?',
            [$itemId, (int) $player['id']]
        );
        if ($it === null) {
            return ['ok' => false, 'error' => 'Modulo non trovato nell\'inventario.'];
        }
        $cat = (string) $it['category'];
        $slots = ShipStats::slots((string) $ship['type_key']);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Il conteggio degli slot si fa con la nave sotto lucchetto: due
            // installazioni parallele di moduli diversi riempivano lo stesso slot.
            Database::first('SELECT id FROM ships WHERE id = ? FOR UPDATE', [(int) $ship['id']]);
            $used = (int) (Database::first(
                'SELECT COUNT(*) c FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
                 WHERE sm.ship_id = ? AND it.category = ?',
                [(int) $ship['id'], $cat]
            )['c'] ?? 0);
            if ($used >= ($slots[$cat] ?? 0)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Nessuno slot ' . self::catLabel($cat) . ' libero su questo scafo.'];
            }
            // Prima si toglie dall'inventario, e si installa solo se c'era: due
            // installazioni parallele dello stesso modulo ne montavano due copie.
            if (Database::run('DELETE FROM player_items WHERE id = ? AND player_id = ?', [$itemId, (int) $player['id']])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Modulo non trovato nell\'inventario.'];
            }
            // Il guasto viaggia col modulo: un modulo guasto rimontato resta guasto.
            Database::run(
                'INSERT INTO ship_modules (ship_id, slot, item_key, rolled, broken_at) VALUES (?, ?, ?, ?, ?)',
                [(int) $ship['id'], $cat, $it['item_key'], $it['rolled'], $it['broken_at']]
            );
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
            'SELECT sm.id, sm.item_key, sm.rolled, sm.broken_at, it.name FROM ship_modules sm
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
            if (Database::run('DELETE FROM ship_modules WHERE id = ? AND ship_id = ?', [$modId, (int) $ship['id']])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Modulo non installato su questa nave.'];
            }
            // Un modulo di stiva non si toglie con le sue stive piene: il
            // carico resterebbe a bordo senza posto.
            $nave = Database::first('SELECT * FROM ships WHERE id = ? FOR UPDATE', [(int) $ship['id']]);
            $eccesso = Economy::holdsUsed($nave) - Economy::capacita($nave);
            if ($eccesso > 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Senza questo modulo il carico non ci sta: scarica prima {$eccesso} unita'."];
            }
            Database::run(
                'INSERT INTO player_items (player_id, item_key, rolled, broken_at, source) VALUES (?, ?, ?, ?, ?)',
                [(int) $player['id'], $m['item_key'], $m['rolled'], $m['broken_at'], 'shop']
            );
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
        // L'aiuto dice «allo StarDock», e cosi' e' per installare e smontare.
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'L\'officina moduli è solo allo StarDock.'];
        }
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
            if (Database::run('DELETE FROM player_items WHERE id = ? AND player_id = ?', [$itemId, (int) $player['id']])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Modulo non trovato.'];
            }
            Wallet::credit((int) $player['id'], ['salvage' => $gain]);
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
            'SELECT pi.id, pi.rolled, it.ckey, it.name, it.category, it.family, it.rarity, it.effects
             FROM player_items pi JOIN item_types it ON it.ckey = pi.item_key
             WHERE pi.id = ? AND pi.player_id = ?',
            [$itemId, (int) $player['id']]
        );
        if ($it === null) {
            return ['ok' => false, 'error' => 'Modulo non trovato nell\'inventario.'];
        }
        $target = self::prossimoDellaFamiglia($it);
        if ($target === null) {
            return ['ok' => false, 'error' => "{$it['name']} è già il modello più avanzato della sua famiglia."];
        }
        $next = (string) $target['rarity'];

        [$costCr, $costMat] = self::costoPotenziamento((string) $it['rarity'], $next);
        if ((int) $player['credits'] < $costCr) {
            return ['ok' => false, 'error' => "Servono {$costCr} cr."];
        }
        if ((int) ($player['salvage'] ?? 0) < $costMat) {
            return ['ok' => false, 'error' => "Servono {$costMat} Leghe di recupero."];
        }

        // Gli affissi restano, riscalati sulla nuova rarita'.
        $vecchi = (array) ((ShipStats::decode($it['rolled']) ?? [])['_affissi'] ?? []);
        $nuovi = [];
        foreach ($vecchi as $af) {
            if (isset(Loot::AFFISSI[$af['a'] ?? ''])) {
                $rapporto = (Loot::MOLT_RARITA[$next] ?? 1.0) / (Loot::MOLT_RARITA[$it['rarity']] ?? 1.0);
                $nuovi[] = ['a' => $af['a'], 'k' => Loot::AFFISSI[$af['a']][1], 'v' => max(1, (int) round(round((float) $af['v'] * $rapporto, 6)))];
            }
        }
        $rolled = json_encode(Loot::conAffissi(ShipStats::decode($target['effects']) ?? [], $nuovi), JSON_UNESCAPED_UNICODE);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge((int) $player['id'], ['credits' => $costCr, 'salvage' => $costMat])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Servono {$costCr} cr e {$costMat} Leghe di recupero."];
            }
            Database::run('UPDATE player_items SET item_key = ?, rolled = ? WHERE id = ?',
                [$target['ckey'], $rolled, $itemId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => Loot::nomeConAffissi((string) $target['name'], $rolled), 'rarity' => $next,
                'label' => Loot::RARITY_LABEL[$next] ?? $next, 'cost' => $costCr, 'mat' => $costMat];
    }

    /**
     * Il modello che segue nella stessa famiglia: la prima rarita' superiore
     * che la famiglia possiede. Prima si sceglieva a caso nella stessa
     * categoria, e una Stiva ausiliaria poteva diventare un Braccio
     * recuperatore.
     *
     * @param array<string,mixed> $it riga con family e rarity
     * @return array<string,mixed>|null
     */
    public static function prossimoDellaFamiglia(array $it): ?array
    {
        if (empty($it['family'])) {
            return null;
        }
        $order = Loot::RARITIES;
        $ci = array_search($it['rarity'], $order, true);
        if (!is_int($ci)) {
            return null;
        }
        foreach (array_slice($order, $ci + 1) as $r) {
            $t = Database::first(
                'SELECT ckey, name, rarity, effects FROM item_types WHERE family = ? AND rarity = ? ORDER BY ckey LIMIT 1',
                [$it['family'], $r]
            );
            if ($t !== null) {
                return $t;
            }
        }
        return null;
    }

    /**
     * Crediti e Leghe per salire da una rarita' a un'altra: se la famiglia
     * salta un gradino, si pagano tutti quelli attraversati.
     *
     * @return array{0:int,1:int}
     */
    public static function costoPotenziamento(string $da, string $a): array
    {
        $order = Loot::RARITIES;
        $cr = 0;
        $mat = 0;
        $i0 = array_search($da, $order, true);
        $i1 = array_search($a, $order, true);
        for ($i = is_int($i0) ? $i0 : 0; is_int($i1) && $i < $i1; $i++) {
            $cr  += self::tierCost('loot.upgrade_cost_credits', $order[$i]);
            $mat += self::tierCost('loot.upgrade_cost_salvage', $order[$i]);
        }
        return [$cr, $mat];
    }

    /** Nome del modulo con i suoi affissi. */
    public static function nome(array $row): string
    {
        return Loot::nomeConAffissi((string) $row['name'], $row['rolled'] ?? null);
    }

    /** Effetti leggibili di un modulo (valori trovati, o quelli del catalogo). */
    public static function descriviEffetti(mixed $rolled, mixed $effects = null): string
    {
        $e = (is_array($rolled) ? $rolled : ShipStats::decode($rolled)) ?: (is_array($effects) ? $effects : ShipStats::decode($effects)) ?: [];
        $out = [];
        foreach ($e as $k => $v) {
            if (str_starts_with((string) $k, '_')) {
                continue;
            }
            $n = is_numeric($v) ? (int) round((float) $v) : 0;
            $out[] = match ($k) {
                'combat_pct'           => "+{$n}% combattimento",
                'max_shields_pct'      => "+{$n}% scudi max",
                'max_fighters_pct'     => "+{$n}% caccia max",
                'shield_regen'         => "+{$n} rigen. scudi/salto",
                'warp_turn_reduction'  => "−{$n} turno/i per warp",
                'cargo_bonus'          => "+{$n} stive",
                'scanner'              => 'scanner ' . ($v === 'holo' ? 'olografico' : 'di densità'),
                'scan_range'           => "+{$n} raggio scansione",
                'cloak'                => 'occultamento',
                'salvage_bonus_pct'    => "+{$n}% Leghe",
                'drop_luck_pct'        => "+{$n}% fortuna bottino",
                'interdict_pct'        => "−{$n}% fuga dei bersagli",
                'armor_pct'            => "−{$n}% danni subiti",
                'ecm_pct'              => "−{$n}% danni da NPC e caccia",
                'hazard_resist_pct'    => "−{$n}% danni ambientali",
                'evade_pct'            => "{$n}% elusione",
                'notoriety_reduce_pct' => "−{$n}% notorietà dei crimini",
                'fighter_regen'        => "+{$n} caccia/ora",
                default                => $k . ' ' . (is_scalar($v) ? $v : ''),
            };
        }
        return implode(' · ', $out);
    }

    /**
     * Fabbriche di caccia a bordo (fighter_regen, caccia all'ora): a ogni
     * battito del clock ogni nave ne riceve un sessantesimo, con
     * arrotondamento casuale imparziale, fino al tetto della nave.
     *
     * @return int navi rifornite
     */
    public static function tickFabbriche(): int
    {
        $perNave = [];
        foreach (Database::all(
            "SELECT sm.ship_id, sm.rolled, it.effects FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
              WHERE sm.broken_at IS NULL AND (sm.rolled LIKE '%fighter_regen%' OR it.effects LIKE '%fighter_regen%')"
        ) as $m) {
            $eff = ShipStats::decode($m['rolled']) ?: ShipStats::decode($m['effects']) ?: [];
            $perNave[(int) $m['ship_id']] = ($perNave[(int) $m['ship_id']] ?? 0) + (float) ($eff['fighter_regen'] ?? 0);
        }
        $n = 0;
        foreach ($perNave as $shipId => $allOra) {
            $ship = PlayerService::ship($shipId);
            if ($ship === null || $ship['type_key'] === 'escape_pod') {
                continue;
            }
            $q = Economy::arrotonda($allOra / 60);
            if ($q > 0 && Database::run(
                'UPDATE ships SET fighters = LEAST(?, fighters + ?) WHERE id = ? AND fighters < ?',
                [(int) $ship['max_fighters'], $q, $shipId, (int) $ship['max_fighters']]
            )->rowCount() > 0) {
                $n++;
            }
        }
        return $n;
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
