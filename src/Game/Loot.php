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

    /**
     * Affissi: modificatori casuali dei moduli trovati come bottino. Ognuno
     * aggiunge un effetto, con un valore che cresce con la rarita' del modulo,
     * e un epiteto al nome («Railgun a massa dell'Assalto e della Fortuna»).
     * Due moduli dello stesso modello non sono mai identici.
     */
    public const AFFISSI = [
        'bastione'  => ['del Bastione', 'max_shields_pct', 3.0],
        'assalto'   => ['dell\'Assalto', 'combat_pct', 2.0],
        'vento'     => ['del Vento', 'evade_pct', 2.0],
        'corazza'   => ['della Corazza', 'armor_pct', 1.5],
        'stiva'     => ['della Stiva', 'cargo_bonus', 2.0],
        'schermo'   => ['dello Schermo', 'hazard_resist_pct', 6.0],
        'ombra'     => ['dell\'Ombra', 'notoriety_reduce_pct', 3.0],
        'fenice'    => ['della Fenice', 'shield_regen', 12.0],
        'predone'   => ['del Predone', 'interdict_pct', 5.0],
        'sciacallo' => ['dello Sciacallo', 'salvage_bonus_pct', 6.0],
        'fortuna'   => ['della Fortuna', 'drop_luck_pct', 2.0],
        'sciame'    => ['dello Sciame', 'max_fighters_pct', 3.0],
        'silenzio'  => ['del Silenzio', 'ecm_pct', 2.0],
        'forgia'    => ['della Forgia', 'fighter_regen', 15.0],
    ];

    /** Quanto pesa un affisso secondo la rarita' del modulo. */
    public const MOLT_RARITA = ['civ' => 1.0, 'mil' => 1.6, 'exp' => 2.4, 'xeno' => 3.4, 'precursor' => 4.6];

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
            // I ripieghi rispecchiano i valori di bilanciamento in tabella: un
            // default diverso da quello voluto e' una regola che cambia da sola
            // il giorno in cui la riga non c'e'.
            $chance = GameConfig::float("loot.drop_chance_{$source}", match ($source) {
                'npc'    => 0.22,
                'pvp'    => 0.40,
                'port'   => 0.15,
                'planet' => 0.10,
                default  => 0.15,
            });
            $band = Fasce::diSettore($sectorId);
            $chance *= Fasce::bottinoMult($band);
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
                $item = self::pickItem($source, $band);
                if ($item === null) {
                    continue;
                }
                $rolled = self::conAffissi(self::rollEffects(ShipStats::decode($item['effects']) ?? []), self::tiraAffissi((string) $item['rarity']));
                Database::run(
                    'INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, ?, ?, ?)',
                    [$killerId, $item['ckey'], json_encode($rolled, JSON_UNESCAPED_UNICODE), $source]
                );
                self::contaModulo($killerId, (string) $item['rarity'], $rolled);
                $out['items'][] = [
                    'key'    => $item['ckey'],
                    'name'   => self::nomeConAffissi((string) $item['name'], $rolled),
                    'rarity' => $item['rarity'],
                    'label'  => self::RARITY_LABEL[$item['rarity']] ?? $item['rarity'],
                ];
            }
            // e forse un consumabile, un reperto, un progetto
            if (($c = Consumabili::tira($killerId, $band)) !== null) {
                $out['consumabili'][] = $c;
            }
            if (($rp = Reperti::tira($killerId, $band)) !== null) {
                $out['reperti'][] = $rp;
            }
            if (($pg = Reperti::tiraProgetto($killerId, $band)) !== null) {
                $out['progetti'][] = $pg;
            }
        } catch (\Throwable $e) {
            \App\Core\Database::rilanciaSeAnnullata($e);
            // catalogo non migrato: solo materiale
        }

        return $out;
    }

    /**
     * Assegna un modulo al giocatore (usato da relitti / anomalie / missioni).
     * Con $deepBias le fasce alte pesano molto di più.
     *
     * @return array{key:string,name:string,rarity:string,label:string}|null
     */
    public static function grant(int $playerId, string $source, bool $deepBias = false, ?string $minRarity = null): ?array
    {
        try {
            $weights = self::rarityWeights();
            if ($deepBias) {
                $weights = ['civ' => 20, 'mil' => 45, 'exp' => 40, 'xeno' => 16, 'precursor' => 4];
            }
            if ($minRarity !== null) {
                $fi = array_search($minRarity, self::RARITIES, true);
                if ($fi !== false) {
                    foreach (self::RARITIES as $idx => $r) {
                        if ($idx < $fi) {
                            unset($weights[$r]);
                        }
                    }
                }
            }
            $rarity = self::weightedPick($weights) ?? 'civ';
            $row = null;
            for ($t = 0; $t < 5 && $row === null; $t++) {
                $row = Database::first('SELECT ckey, name, rarity, effects FROM item_types WHERE rarity = ? ORDER BY RAND() LIMIT 1', [$rarity]);
                if ($row === null) {
                    $ri = array_search($rarity, self::RARITIES, true);
                    $rarity = is_int($ri) && $ri > 0 ? self::RARITIES[$ri - 1] : 'civ';
                }
            }
            if ($row === null) {
                return null;
            }
            $rolled = self::conAffissi(self::rollEffects(ShipStats::decode($row['effects']) ?? []), self::tiraAffissi((string) $row['rarity']));
            Database::run(
                'INSERT INTO player_items (player_id, item_key, rolled, source) VALUES (?, ?, ?, ?)',
                [$playerId, $row['ckey'], json_encode($rolled, JSON_UNESCAPED_UNICODE), $source]
            );
            self::contaModulo($playerId, (string) $row['rarity'], $rolled);
            return ['key' => $row['ckey'], 'name' => self::nomeConAffissi((string) $row['name'], $rolled), 'rarity' => $row['rarity'],
                    'label' => self::RARITY_LABEL[$row['rarity']] ?? $row['rarity']];
        } catch (\Throwable $e) {
            Database::rilanciaSeAnnullata($e);
            return null;
        }
    }

    /** Contatori di carriera per un modulo trovato. @param array<string,mixed> $rolled */
    private static function contaModulo(int $playerId, string $rarity, array $rolled): void
    {
        Stats::add($playerId, 'moduli_trovati');
        Stats::add($playerId, 'moduli_' . $rarity);
        Stats::max($playerId, 'affissi_max', count((array) ($rolled['_affissi'] ?? [])));
    }

    // --- affissi ---------------------------------------------------------------

    /**
     * Affissi per un modulo appena trovato: Civile 0-1, Militare 0-1 (piu'
     * spesso), Sperimentale 1-2, Xeno 2, Precursore 2-3; tutti diversi.
     *
     * @return list<array{a:string, k:string, v:float|int}>
     */
    public static function tiraAffissi(string $rarity): array
    {
        $quanti = match ($rarity) {
            'civ'       => self::frand() < 0.3 ? 1 : 0,
            'mil'       => self::frand() < 0.6 ? 1 : 0,
            'exp'       => 1 + (self::frand() < 0.4 ? 1 : 0),
            'xeno'      => 2,
            'precursor' => 2 + (self::frand() < 0.4 ? 1 : 0),
            default     => 0,
        };
        $chiavi = array_keys(self::AFFISSI);
        shuffle($chiavi);
        $out = [];
        foreach (array_slice($chiavi, 0, $quanti) as $a) {
            [, $k, $base] = self::AFFISSI[$a];
            $var = 0.8 + self::frand() * 0.4;
            // «x» e' il valore esatto, prima di arrotondare: i potenziamenti
            // riscalano quello, e non un intero gia' arrotondato a ogni gradino.
            $out[] = ['a' => $a, 'k' => $k, 'v' => self::valoreAffisso($base, $rarity, $var),
                      'x' => round($base * (self::MOLT_RARITA[$rarity] ?? 1.0) * $var, 4)];
        }
        return $out;
    }

    /** Valore di un affisso: base x peso della rarita' x variazione; interi, almeno 1. */
    public static function valoreAffisso(float $base, string $rarity, float $variazione = 1.0): int
    {
        return max(1, (int) round($base * (self::MOLT_RARITA[$rarity] ?? 1.0) * $variazione));
    }

    /**
     * Somma gli affissi agli effetti e li annota sotto «_affissi» (ShipStats
     * ignora le chiavi non numeriche, il nome li legge da li').
     *
     * @param array<string,mixed> $rolled
     * @param list<array{a:string, k:string, v:float|int}> $affissi
     * @return array<string,mixed>
     */
    public static function conAffissi(array $rolled, array $affissi): array
    {
        if ($affissi === []) {
            return $rolled;
        }
        foreach ($affissi as $af) {
            $rolled[$af['k']] = ($rolled[$af['k']] ?? 0) + $af['v'];
        }
        $rolled['_affissi'] = $affissi;
        return $rolled;
    }

    /** «Railgun a massa dell'Assalto e della Fortuna». */
    public static function nomeConAffissi(string $nome, mixed $rolled): string
    {
        $r = is_array($rolled) ? $rolled : (ShipStats::decode($rolled) ?? []);
        $epiteti = [];
        foreach ((array) ($r['_affissi'] ?? []) as $af) {
            if (isset(self::AFFISSI[$af['a'] ?? ''])) {
                $epiteti[] = self::AFFISSI[$af['a']][0];
            }
        }
        if ($epiteti === []) {
            return $nome;
        }
        $ultimo = array_pop($epiteti);
        return $nome . ' ' . ($epiteti === [] ? $ultimo : implode(', ', $epiteti) . ' e ' . $ultimo);
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
        foreach (array_merge($drops['consumabili'] ?? [], $drops['reperti'] ?? [], $drops['progetti'] ?? []) as $c) {
            $bits[] = $c['name'];
        }
        return $bits === [] ? '' : ' Recuperato: ' . implode(' · ', $bits) . '.';
    }

    // --- interni --------------------------------------------------------------

    /**
     * Fuori dal PvP la rarita' segue la fascia (Fasce::pesiRarita): vicino a
     * Sol quasi solo moduli civili, nell'Orlo sperimentali, xeno e precursori.
     * Il PvP tiene la tabella generale col suo pavimento: li' il valore sta
     * nel bersaglio, non nel luogo.
     *
     * @return array<string,mixed>|null
     */
    private static function pickItem(string $source, int $band = 0): ?array
    {
        $weights = $source !== 'pvp' && $band >= 1 && ($pf = Fasce::pesiRarita($band)) !== []
            ? $pf
            : self::rarityWeights();

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

    private static function eventLuck(): float
    {
        try {
            $has = Database::first(
                "SELECT 1 x FROM events WHERE kind = 'bounty_season' AND reverted = 0 AND (ends_at IS NULL OR ends_at > NOW()) LIMIT 1"
            );
            return $has ? GameConfig::float('loot.event_bounty_luck', 1.4) : 1.0;
        } catch (\Throwable $e) {
            \App\Core\Database::rilanciaSeAnnullata($e);
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
