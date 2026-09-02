<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 7 — bottino: alla distruzione di un bersaglio si tira su una tabella
 * di rarità (config-driven) per un eventuale modulo, e si accumulano sempre
 * "Leghe di recupero" (players.salvage). Chiamata da Combat dentro la
 * transazione del combattimento.
 */
final class Loot
{
    public const RARITIES = ['civ', 'mil', 'exp', 'xeno', 'precursor'];

    public const RARITY_LABEL = [
        'civ'       => 'Civile',
        'mil'       => 'Militare',
        'exp'       => 'Sperimentale',
        'xeno'      => 'Xeno',
        'precursor' => 'Precursore',
    ];

    /**
     * @param 'npc'|'pvp'|'port'|'planet' $source
     * @param array<string,mixed>|null    $victim  riga players (solo per pvp)
     * @return array{items:list<array{key:string,name:string,rarity:string,label:string}>, salvage:int}
     */
    public static function rollKill(
        int $killerId,
        string $source,
        int $sectorId,
        float $targetRating,
        ?string $npcKind = null,
        ?array $victim = null,
        float $dropLuckPct = 0.0
    ): array {
        $out = ['items' => [], 'salvage' => 0];

        // materiale: sempre, in funzione della "stazza" del bersaglio
        $salPerR = GameConfig::float('loot.salvage_per_rating', 6.0);
        $salBonus = 1.0;
        $sh = Database::first('SELECT s.id FROM ships s JOIN players p ON p.ship_id = s.id WHERE p.id = ?', [$killerId]);
        if ($sh !== null) {
            $ship = PlayerService::ship((int) $sh['id']);
            $salBonus += (float) ($ship['mod_salvage_pct'] ?? 0) / 100;
            $dropLuckPct += (float) ($ship['mod_drop_luck'] ?? 0);
        }
        $mat = (int) round(max(0.2, $targetRating) * $salPerR * $salBonus * self::jitter());
        if ($mat > 0) {
            Database::run('UPDATE players SET salvage = salvage + ? WHERE id = ?', [$mat, $killerId]);
            $out['salvage'] = $mat;
        }

        // --- eventuale drop di modulo ---------------------------------------
        try {
            $chance = GameConfig::float("loot.drop_chance_{$source}", $source === 'npc' ? 0.35 : 0.15);
            $chance *= self::regionMult($sectorId);
            $chance *= self::eventLuck();
            $chance *= 1 + $dropLuckPct / 100;
            if ($npcKind === 'ferrengi') {
                $chance *= 1.25;
            } elseif ($npcKind === 'trader') {
                $chance *= 0.7;
            }

            if ($source === 'pvp') {
                if ($victim !== null && (int) ($victim['rating'] ?? 0) < GameConfig::int('loot.min_victim_rating_pvp', 50)) {
                    return $out;
                }
                if ($victim !== null && !empty($victim['protected_until']) && strtotime((string) $victim['protected_until']) > time()) {
                    return $out;
                }
                $cap = GameConfig::int('loot.pvp_drops_per_day', 1);
                if ($victim !== null && $cap > 0) {
                    $today = (int) (Database::first(
                        "SELECT COUNT(*) c FROM combat_log
                         WHERE kind = 'ship' AND outcome = 'def_destroyed'
                           AND attacker_player_id = ? AND defender_player_id = ? AND created_at >= CURDATE()",
                        [$killerId, (int) $victim['id']]
                    )['c'] ?? 0);
                    if ($today >= $cap) {
                        return $out;
                    }
                }
            }

            $guaranteed = Crew::consumePending($killerId, 'guaranteed_drop') !== null;
            $n = 1 + (self::frand() < GameConfig::float('loot.double_drop_pct', 0.08) ? 1 : 0);
            for ($i = 0; $i < $n; $i++) {
                if (!($guaranteed && $i === 0) && self::frand() >= min(0.95, $chance)) {
                    continue;
                }
                $item = self::pickItem($source);
                if ($item === null) {
                    continue;
                }
                $rolled = self::rollEffects(ShipStats::decode($item['effects']) ?? []);
                Database::run(
                    'INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, ?, ?, ?)',
                    [$killerId, $item['ckey'], json_encode($rolled, JSON_UNESCAPED_UNICODE), $source]
                );
                $out['items'][] = [
                    'key'    => $item['ckey'],
                    'name'   => $item['name'],
                    'rarity' => $item['rarity'],
                    'label'  => self::RARITY_LABEL[$item['rarity']] ?? $item['rarity'],
                ];
            }
        } catch (\Throwable) {
            // catalogo non migrato: solo materiale
        }

        return $out;
    }

    /** Riga di testo per i messaggi di combattimento. */
    public static function describe(array $drops): string
    {
        $bits = [];
        if (!empty($drops['salvage'])) {
            $bits[] = '+' . number_format((int) $drops['salvage'], 0, ',', '.') . ' Leghe';
        }
        foreach ($drops['items'] ?? [] as $it) {
            $bits[] = "{$it['name']} [{$it['label']}]";
        }
        return $bits === [] ? '' : ' Recuperato: ' . implode(' · ', $bits) . '.';
    }

    // --- interni --------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private static function pickItem(string $source): ?array
    {
        $weights = self::rarityWeights();

        // pavimento di fascia in PvP
        if ($source === 'pvp') {
            $floor = GameConfig::str('loot.pvp_tier_floor', 'mil');
            $fi = array_search($floor, self::RARITIES, true);
            if ($fi !== false) {
                foreach (self::RARITIES as $idx => $r) {
                    if ($idx < $fi) {
                        unset($weights[$r]);
                    }
                }
            }
        }

        $rarity = self::weightedPick($weights);
        if ($rarity === null) {
            return null;
        }
        // sorteggio dell'item nella fascia; fallback a fasce più basse se vuota
        for ($tries = 0; $tries < 5; $tries++) {
            $row = Database::first(
                'SELECT ckey, name, category, rarity, effects FROM item_types WHERE rarity = ? ORDER BY RAND() LIMIT 1',
                [$rarity]
            );
            if ($row !== null) {
                return $row;
            }
            $ri = array_search($rarity, self::RARITIES, true);
            if (!is_int($ri) || $ri <= 0) {
                break;
            }
            $rarity = self::RARITIES[$ri - 1];
        }
        return null;
    }

    /** @return array<string,int> */
    private static function rarityWeights(): array
    {
        $out = [];
        foreach (explode(',', GameConfig::str('loot.rarity_weights', 'civ:100,mil:45,exp:16,xeno:5,precursor:1')) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2 && in_array($p[0], self::RARITIES, true)) {
                $out[$p[0]] = max(0, (int) $p[1]);
            }
        }
        return $out === [] ? ['civ' => 100, 'mil' => 45, 'exp' => 16, 'xeno' => 5, 'precursor' => 1] : $out;
    }

    /** @param array<string,int> $weights */
    private static function weightedPick(array $weights): ?string
    {
        $tot = array_sum($weights);
        if ($tot <= 0) {
            return null;
        }
        $roll = self::frand() * $tot;
        foreach ($weights as $key => $w) {
            $roll -= $w;
            if ($roll < 0) {
                return $key;
            }
        }
        return array_key_first($weights);
    }

    /** @param array<string,mixed> $eff @return array<string,mixed> */
    private static function rollEffects(array $eff): array
    {
        $var = GameConfig::float('loot.effect_roll_variance', 0.15);
        $out = [];
        foreach ($eff as $k => $v) {
            if (is_numeric($v)) {
                $f = 1 + ((mt_rand() / mt_getrandmax()) * 2 - 1) * $var;
                $out[$k] = is_int($v) || $v == (int) $v ? max(1, (int) round($v * $f)) : round($v * $f, 3);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function regionMult(int $sectorId): float
    {
        $kind = (string) (Database::first(
            'SELECT r.kind FROM sectors s LEFT JOIN regions r ON r.id = s.region_id WHERE s.id = ?',
            [$sectorId]
        )['kind'] ?? 'core');
        return match ($kind) {
            'deep'     => GameConfig::float('loot.region_bonus_deep', 1.5),
            'frontier' => GameConfig::float('loot.region_bonus_frontier', 1.15),
            default    => 1.0,
        };
    }

    private static function eventLuck(): float
    {
        try {
            $has = Database::first(
                "SELECT 1 x FROM events WHERE kind = 'bounty_season' AND reverted = 0 AND (ends_at IS NULL OR ends_at > NOW()) LIMIT 1"
            );
            return $has ? GameConfig::float('loot.event_bounty_luck', 1.4) : 1.0;
        } catch (\Throwable) {
            return 1.0;
        }
    }

    private static function jitter(): float
    {
        return 0.75 + (mt_rand() / mt_getrandmax()) * 0.6;   // 0.75..1.35
    }

    private static function frand(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
