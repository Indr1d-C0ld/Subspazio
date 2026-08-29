<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Classifiche: punteggio combinato di comandanti e corporazioni.
 */
final class Leaderboard
{
    /** @param array<string,mixed> $p riga player (con eventuale bank/ship info gia' unita) */
    public static function ratingFor(array $p): int
    {
        $exp     = (int) ($p['experience'] ?? 0);
        $kills   = (int) ($p['kills'] ?? 0);
        $deaths  = (int) ($p['deaths'] ?? 0);
        $busts   = (int) ($p['port_busts'] ?? 0);
        $align   = (int) ($p['alignment'] ?? 0);
        $credits = (int) ($p['credits'] ?? 0);
        $bank    = (int) ($p['bank_balance'] ?? 0);
        $shipVal = (int) ($p['ship_value'] ?? 0);
        $planets = (int) ($p['planet_count'] ?? 0);
        $planetCr = (int) ($p['planet_treasury'] ?? 0);

        $net = $credits + $bank + $shipVal + $planetCr;

        return $exp
            + $kills * 200
            - $deaths * 120
            + $busts * 60
            + intdiv($net, 1000)
            + $planets * 500
            + max(-2000, min(2000, $align));
    }

    public static function recalcAll(): int
    {
        $rows = Database::all(
            "SELECT p.*, COALESCE(b.balance,0) AS bank_balance,
                    COALESCE(FLOOR(st.base_cost * 0.4),0) AS ship_value,
                    (SELECT COUNT(*) FROM planets pl WHERE pl.owner_player_id = p.id AND pl.destroyed = 0) AS planet_count,
                    (SELECT COALESCE(SUM(pl.credits),0) FROM planets pl WHERE pl.owner_player_id = p.id AND pl.destroyed = 0) AS planet_treasury
             FROM players p
             LEFT JOIN bank_accounts b ON b.player_id = p.id
             LEFT JOIN ships s ON s.id = p.ship_id
             LEFT JOIN ship_types st ON st.ckey = s.type_key"
        );
        foreach ($rows as $p) {
            Database::run('UPDATE players SET rating = ? WHERE id = ?', [self::ratingFor($p), $p['id']]);
        }
        return count($rows);
    }

    /** @return list<array<string,mixed>> */
    public static function topPlayers(int $limit = 25): array
    {
        return array_map(static fn ($r) => [
            'handle'     => $r['handle'],
            'rating'     => (int) $r['rating'],
            'rank'       => Ranks::title((int) $r['experience']),
            'experience' => (int) $r['experience'],
            'kills'      => (int) $r['kills'],
            'deaths'     => (int) $r['deaths'],
            'alignment'  => Ranks::alignmentLabel((int) $r['alignment']),
            'planets'    => (int) $r['planet_count'],
            'corp'       => $r['corp_tag'],
        ], Database::all(
            "SELECT p.handle, p.rating, p.experience, p.kills, p.deaths, p.alignment,
                    c.tag AS corp_tag,
                    (SELECT COUNT(*) FROM planets pl WHERE pl.owner_player_id = p.id AND pl.destroyed = 0) AS planet_count
             FROM players p
             LEFT JOIN corp_members m ON m.player_id = p.id
             LEFT JOIN corporations c ON c.id = m.corp_id
             ORDER BY p.rating DESC, p.experience DESC
             LIMIT ?",
            [$limit]
        ));
    }

    /** @return list<array<string,mixed>> */
    public static function topCorps(int $limit = 15): array
    {
        return array_map(static fn ($r) => [
            'name'     => $r['name'],
            'tag'      => $r['tag'],
            'members'  => (int) $r['members'],
            'rating'   => (int) $r['crating'],
            'treasury' => (int) $r['treasury'],
            'planets'  => (int) $r['planets'],
        ], Database::all(
            "SELECT c.name, c.tag, c.treasury,
                    (SELECT COUNT(*) FROM corp_members m WHERE m.corp_id = c.id) AS members,
                    (SELECT COALESCE(SUM(p.rating),0) FROM corp_members m JOIN players p ON p.id = m.player_id WHERE m.corp_id = c.id)
                      + FLOOR(c.treasury/1000)
                      + (SELECT COUNT(*) FROM planets pl WHERE pl.corp_id = c.id AND pl.destroyed = 0) * 500 AS crating,
                    (SELECT COUNT(*) FROM planets pl WHERE pl.corp_id = c.id AND pl.destroyed = 0) AS planets
             FROM corporations c
             ORDER BY crating DESC
             LIMIT ?",
            [$limit]
        ));
    }
}
