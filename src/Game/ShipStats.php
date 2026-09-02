<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Statistiche "effettive" della nave = valori di base dello scafo + effetti
 * dei moduli installati (Fase 7). Unico punto d'applicazione: la overlay in
 * PlayerService::ship(). Combat/Navigation leggono già da lì.
 */
final class ShipStats
{
    /** ordine di forza degli scanner */
    private const SCANNER_RANK = ['none' => 0, 'density' => 1, 'holo' => 2];

    /**
     * @param array<string,mixed> $ship  riga ships + join ship_types (come da PlayerService::ship)
     * @return array<string,mixed>        stessa riga con i valori effettivi + chiavi mod_*
     */
    public static function effective(array $ship): array
    {
        if (!isset($ship['id'])) {
            return $ship;
        }

        try {
            $mods = Database::all(
                'SELECT sm.slot, sm.item_key, sm.rolled, it.effects, it.name, it.rarity
                 FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key
                 WHERE sm.ship_id = ?',
                [(int) $ship['id']]
            );
        } catch (\Throwable) {
            // tabelle non ancora migrate: comportamento identico a prima
            $ship['mod_effects'] = [];
            $ship['mod_count'] = 0;
            $ship['mod_list'] = [];
            return $ship;
        }

        $sum = [];        // effetti numerici sommati
        $scanner = null;  // 'density' | 'holo'
        $cloak = 0;
        $list = [];

        foreach ($mods as $m) {
            $eff = self::decode($m['rolled']) ?: self::decode($m['effects']) ?: [];
            foreach ($eff as $k => $v) {
                if ($k === 'scanner') {
                    if ($scanner === null || (self::SCANNER_RANK[$v] ?? 0) > (self::SCANNER_RANK[$scanner] ?? 0)) {
                        $scanner = (string) $v;
                    }
                    continue;
                }
                if ($k === 'cloak') {
                    $cloak = max($cloak, (int) $v);
                    continue;
                }
                if (is_numeric($v)) {
                    $sum[$k] = ($sum[$k] ?? 0) + (float) $v;
                }
            }
            $list[] = [
                'slot'   => $m['slot'],
                'key'    => $m['item_key'],
                'name'   => $m['name'],
                'rarity' => $m['rarity'],
                'eff'    => $eff,
            ];
        }

        // --- applica ----------------------------------------------------------
        $baseCr = (float) ($ship['combat_rating'] ?? 1.0);
        if (!empty($sum['combat_pct'])) {
            $ship['combat_rating'] = round($baseCr * (1 + $sum['combat_pct'] / 100), 4);
        }
        if (!empty($sum['warp_turn_reduction'])) {
            $ship['turns_per_warp'] = max(1, (int) $ship['turns_per_warp'] - (int) $sum['warp_turn_reduction']);
        }
        if (!empty($sum['cargo_bonus'])) {
            $ship['holds_total'] = (int) $ship['holds_total'] + (int) $sum['cargo_bonus'];
        }
        if (!empty($sum['max_shields_pct']) && isset($ship['max_shields'])) {
            $ship['max_shields'] = (int) round((int) $ship['max_shields'] * (1 + $sum['max_shields_pct'] / 100));
        }
        if ($scanner !== null) {
            $cur = (string) ($ship['dev_scanner'] ?? 'none');
            if ((self::SCANNER_RANK[$scanner] ?? 0) > (self::SCANNER_RANK[$cur] ?? 0)) {
                $ship['dev_scanner'] = $scanner;
            }
        }
        if ($cloak > 0) {
            $ship['dev_cloak'] = 1;
        }

        // --- equipaggio (Fase 8): bonus passivi degli ufficiali imbarcati ---
        $crew = ['count' => 0, 'combat_pct' => 0.0, 'shield_regen' => 0.0, 'scan_range' => 0.0,
                 'drop_luck_pct' => 0.0, 'warp_discount_pct' => 0.0, 'align_shield_pct' => 0.0, 'away_medicine' => 0.0];
        if (!empty($ship['player_id']) && class_exists(Crew::class)) {
            try {
                $crew = Crew::passiveBonuses((int) $ship['player_id']) + $crew;
            } catch (\Throwable) {
            }
        }
        if (!empty($crew['combat_pct'])) {
            $ship['combat_rating'] = round((float) $ship['combat_rating'] * (1 + $crew['combat_pct'] / 100), 4);
        }

        $ship['mod_effects']  = $sum + ($scanner ? ['scanner' => $scanner] : []) + ($cloak ? ['cloak' => 1] : []);
        $ship['mod_shield_regen'] = (int) ($sum['shield_regen'] ?? 0) + (int) round($crew['shield_regen']);
        $ship['mod_salvage_pct']  = (float) ($sum['salvage_bonus_pct'] ?? 0);
        $ship['mod_drop_luck']    = (float) ($sum['drop_luck_pct'] ?? 0) + (float) $crew['drop_luck_pct'];
        $ship['mod_count'] = count($list);
        $ship['mod_list']  = $list;
        $ship['crew_count']            = (int) $crew['count'];
        $ship['crew_warp_discount_pct'] = (float) $crew['warp_discount_pct'];
        $ship['crew_align_shield_pct']  = (float) $crew['align_shield_pct'];
        $ship['crew_scan_range']        = (int) round($crew['scan_range']);
        $ship['crew_away_medicine']     = (float) $crew['away_medicine'];

        return $ship;
    }

    /** slot totali dello scafo per categoria */
    public static function slots(string $typeKey): array
    {
        $t = Database::first(
            'SELECT slot_weapon, slot_defense, slot_drive, slot_computer, slot_utility FROM ship_types WHERE ckey = ?',
            [$typeKey]
        ) ?? [];
        return [
            'weapon'   => (int) ($t['slot_weapon'] ?? 0),
            'defense'  => (int) ($t['slot_defense'] ?? 0),
            'drive'    => (int) ($t['slot_drive'] ?? 0),
            'computer' => (int) ($t['slot_computer'] ?? 0),
            'utility'  => (int) ($t['slot_utility'] ?? 0),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function decode(mixed $json): ?array
    {
        if (is_array($json)) {
            return $json;
        }
        if (!is_string($json) || $json === '') {
            return null;
        }
        $d = json_decode($json, true);
        return is_array($d) ? $d : null;
    }
}
