<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fase 10 — fazioni & reputazione. Quattro potenze; la reputazione per
 * giocatore (-100..+100) muove col commercio, i kill, le missioni e il
 * lavoro nel profondo, e sblocca sconti/moduli/passaggio.
 */
final class Faction
{
    public const KEYS = ['fed', 'ferrengi', 'hegemony', 'frontier'];

    public const TIER_LABEL = [
        'hostile' => 'ostile', 'wary' => 'diffidente', 'neutral' => 'neutrale',
        'friendly' => 'amichevole', 'allied' => 'alleato',
    ];
    private const TIER_ORDER = ['hostile' => 0, 'wary' => 1, 'neutral' => 2, 'friendly' => 3, 'allied' => 4];

    // --- lettura --------------------------------------------------------

    public static function value(int $playerId, string $faction): int
    {
        try {
            return (int) (Database::first(
                'SELECT value FROM player_reputation WHERE player_id = ? AND faction = ?',
                [$playerId, $faction]
            )['value'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string,int> */
    public static function all(int $playerId): array
    {
        $out = array_fill_keys(self::KEYS, 0);
        try {
            foreach (Database::all('SELECT faction, value FROM player_reputation WHERE player_id = ?', [$playerId]) as $r) {
                if (isset($out[$r['faction']])) {
                    $out[$r['faction']] = (int) $r['value'];
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    public static function tier(int $value): string
    {
        return match (true) {
            $value <= GameConfig::int('faction.tier_hostile', -60)  => 'hostile',
            $value <= GameConfig::int('faction.tier_wary', -20)     => 'wary',
            $value <  GameConfig::int('faction.tier_friendly', 20)  => 'neutral',
            $value <  GameConfig::int('faction.tier_allied', 60)    => 'friendly',
            default => 'allied',
        };
    }

    public static function tierAtLeast(int $playerId, string $faction, string $tier): bool
    {
        return (self::TIER_ORDER[self::tier(self::value($playerId, $faction))] ?? 0)
            >= (self::TIER_ORDER[$tier] ?? 99);
    }

    // --- scrittura -----------------------------------------------------

    public static function adjust(int $playerId, string $faction, int $delta, string $reason = '', bool $spill = true): void
    {
        if ($delta === 0 || !in_array($faction, self::KEYS, true)) {
            return;
        }
        try {
            $min = GameConfig::int('faction.min', -100);
            $max = GameConfig::int('faction.max', 100);
            $cur = self::value($playerId, $faction);
            $new = max($min, min($max, $cur + $delta));
            if ($new === $cur) {
                return;
            }
            Database::run(
                'INSERT INTO player_reputation (player_id, faction, value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$playerId, $faction, $new]
            );
            Database::run(
                'INSERT INTO faction_log (player_id, faction, delta, reason) VALUES (?, ?, ?, ?)',
                [$playerId, $faction, $new - $cur, mb_substr($reason, 0, 48)]
            );
            // rivalità: se sali con una fazione, la rivale scende un po'
            if ($spill && $delta > 0) {
                $rival = (string) (Database::first('SELECT rival FROM factions WHERE ckey = ?', [$faction])['rival'] ?? '');
                if ($rival !== '') {
                    $spillDelta = (int) round(-$delta * GameConfig::float('faction.rivalry', 0.35));
                    if ($spillDelta !== 0) {
                        self::adjust($playerId, $rival, $spillDelta, "attrito con {$faction}", false);
                    }
                }
            }
        } catch (\Throwable) {
            // tabelle non migrate
        }
    }

    // --- eventi di gioco ---------------------------------------------

    public static function onTrade(int $playerId, int $regionId, int $total): void
    {
        $f = self::controllerOf($regionId);
        if ($f !== null && $total > 0) {
            self::adjust($playerId, $f, GameConfig::int('faction.trade_gain', 1), 'commercio');
        }
    }

    public static function onKillNpc(int $playerId, string $npcKind): void
    {
        $g = GameConfig::int('faction.kill_gain', 6);
        match ($npcKind) {
            'ferrengi' => (function () use ($playerId, $g) {
                self::adjust($playerId, 'fed', $g, 'colpito un Ferrengi');
                self::adjust($playerId, 'ferrengi', -(int) round($g * 1.5), 'aggressione', false);
                self::adjust($playerId, 'frontier', (int) round($g / 2), 'colpito un Ferrengi');
            })(),
            'pirate' => (function () use ($playerId, $g) {
                self::adjust($playerId, 'fed', $g, 'colpito un pirata');
                self::adjust($playerId, 'frontier', $g, 'colpito un pirata');
                self::adjust($playerId, 'hegemony', (int) round($g / 2), 'colpito un pirata');
            })(),
            default => (function () use ($playerId, $g) { // trader / merchant: attacco a civili
                self::adjust($playerId, 'fed', -$g, 'attacco a un civile', false);
                self::adjust($playerId, 'ferrengi', -$g, 'attacco a un civile', false);
                self::adjust($playerId, 'frontier', -(int) round($g / 2), 'attacco a un civile', false);
            })(),
        };
    }

    public static function onKillPlayer(int $playerId, int $victimAlignment): void
    {
        $g = GameConfig::int('faction.kill_gain', 6);
        if ($victimAlignment >= 0) {
            self::adjust($playerId, 'fed', -$g, 'omicidio', false);
            self::adjust($playerId, 'hegemony', (int) round($g / 2), 'un rivale in meno');
        } else {
            self::adjust($playerId, 'fed', (int) round($g / 2), 'eliminato un fuorilegge');
        }
    }

    public static function onPortBust(int $playerId, int $regionId): void
    {
        $l = GameConfig::int('faction.bust_loss', 12);
        $f = self::controllerOf($regionId);
        if ($f !== null) {
            self::adjust($playerId, $f, -$l, 'assalto a un porto', false);
        }
        self::adjust($playerId, 'fed', -(int) round($l / 2), 'assalto a un porto', false);
        self::adjust($playerId, 'ferrengi', (int) round($l / 3), 'caos commerciale');
    }

    public static function onPlanetBomb(int $playerId): void
    {
        $l = GameConfig::int('faction.bomb_loss', 25);
        self::adjust($playerId, 'frontier', -$l, 'bombardamento planetario', false);
        self::adjust($playerId, 'fed', -(int) round($l / 2), 'bombardamento planetario', false);
    }

    public static function onDeepWork(int $playerId): void
    {
        self::adjust($playerId, 'frontier', GameConfig::int('faction.deep_gain', 2), 'lavoro nel profondo');
    }

    // --- benefici / vincoli ----------------------------------------

    /** Se la Federazione è ostile, i servizi StarDock sono revocati. */
    public static function stardockBlocked(int $playerId): ?string
    {
        if (self::value($playerId, 'fed') <= GameConfig::int('faction.tier_hostile', -60)) {
            return 'La Federazione ti ha revocato l\'accesso ai servizi dello StarDock. '
                . 'Rialza la reputazione o paga un\'ammenda dalla pagina Fazioni.';
        }
        return null;
    }

    public static function amnesty(array $player): array
    {
        $cost = GameConfig::int('faction.amnesty_cost', 15000);
        if (self::value((int) $player['id'], 'fed') > GameConfig::int('faction.tier_hostile', -60)) {
            return ['ok' => false, 'error' => 'Non ti serve un\'ammenda: la Federazione non ti ha bandito.'];
        }
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr per l'ammenda."];
        }
        $target = GameConfig::int('faction.amnesty_target', -30);
        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, (int) $player['id']]);
        Database::run(
            'INSERT INTO player_reputation (player_id, faction, value) VALUES (?, "fed", ?)
             ON DUPLICATE KEY UPDATE value = ?',
            [(int) $player['id'], $target, $target]
        );
        Database::run('INSERT INTO faction_log (player_id, faction, delta, reason) VALUES (?, "fed", 0, "ammenda pagata")',
            [(int) $player['id']]);
        return ['ok' => true, 'cost' => $cost];
    }

    /** @return list<array<string,mixed>> offerte visibili al giocatore (con flag sbloccata) */
    public static function offers(int $playerId): array
    {
        try {
            $rows = Database::all(
                'SELECT fo.*, it.name AS item_name, it.rarity, it.effects, f.name AS faction_name
                 FROM faction_offers fo
                 JOIN item_types it ON it.ckey = fo.ref
                 JOIN factions f ON f.ckey = fo.faction
                 ORDER BY fo.faction, fo.sort',
                []
            );
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['unlocked'] = self::tierAtLeast($playerId, $r['faction'], $r['min_tier']);
        }
        return $rows;
    }

    public static function buyOffer(array $player, int $offerId): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Gli empori di fazione operano solo allo StarDock.'];
        }
        $o = Database::first(
            'SELECT fo.*, it.name AS item_name FROM faction_offers fo JOIN item_types it ON it.ckey = fo.ref WHERE fo.id = ?',
            [$offerId]
        );
        if ($o === null) {
            return ['ok' => false, 'error' => 'Offerta inesistente.'];
        }
        if (!self::tierAtLeast((int) $player['id'], $o['faction'], $o['min_tier'])) {
            return ['ok' => false, 'error' => 'Reputazione insufficiente con questa fazione.'];
        }
        if ((int) $player['credits'] < (int) $o['price']) {
            return ['ok' => false, 'error' => "Servono {$o['price']} cr."];
        }
        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [(int) $o['price'], (int) $player['id']]);
        Database::run(
            "INSERT INTO player_items (player_id, item_key, source) VALUES (?, ?, 'shop')",
            [(int) $player['id'], $o['ref']]
        );
        return ['ok' => true, 'name' => $o['item_name'], 'cost' => (int) $o['price']];
    }

    public static function controllerOf(int $regionId): ?string
    {
        try {
            $f = Database::first('SELECT faction FROM regions WHERE id = ?', [$regionId])['faction'] ?? null;
            return $f !== null && $f !== '' ? (string) $f : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // --- tick -------------------------------------------------------

    public static function tick(): array
    {
        $out = ['decayed' => 0, 'bounty_hunters' => 0];
        try {
            // decadimento verso lo zero, una volta al giorno
            $every = 86400;
            $last = GameConfig::str('faction.decay_last_run', '');
            if ($last === '' || (time() - strtotime($last)) >= $every) {
                $d = GameConfig::int('faction.decay_per_day', 2);
                if ($d > 0) {
                    $out['decayed'] = Database::run(
                        'UPDATE player_reputation
                         SET value = value - SIGN(value) * LEAST(ABS(value), ?)
                         WHERE value <> 0',
                        [$d]
                    )->rowCount();
                }
                GameConfig::set('faction.decay_last_run', date('Y-m-d H:i:s'));
            }

            // cacciatori di taglie per chi è in rotta di collisione con la Federazione
            $wary = GameConfig::int('faction.tier_wary', -20);
            $minB = GameConfig::int('faction.bh_min_bounty', 2000);
            $chance = GameConfig::int('faction.bh_chance_pct', 25);
            foreach (Database::all(
                "SELECT p.id, p.sector_id, p.handle, p.bounty
                 FROM players p
                 JOIN player_reputation r ON r.player_id = p.id AND r.faction = 'fed'
                 JOIN sectors s ON s.id = p.sector_id
                 WHERE r.value <= ? AND p.bounty >= ? AND s.is_fedspace = 0",
                [$wary, $minB]
            ) as $pl) {
                if (mt_rand(1, 100) > $chance) {
                    continue;
                }
                $rating = 1.3 + (int) $pl['bounty'] / 40000;
                Database::run(
                    'INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, fighters, shields, combat_rating, credits, cargo_ore, cargo_org, cargo_equ, aggression)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?)',
                    [
                        'pirate', 'Cacciatore di taglie', 'missile_frigate', (int) $pl['sector_id'], (int) $pl['sector_id'],
                        mt_rand(2500, 6000), mt_rand(600, 1500), round($rating, 2),
                        (int) round((int) $pl['bounty'] * 0.3), 1,
                    ]
                );
                $out['bounty_hunters']++;
                Live::player((int) $pl['id'], 'alert', 'Cacciatore di taglie', 'Un cacciatore di taglie della Federazione ti ha trovato.');
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
    }
}
