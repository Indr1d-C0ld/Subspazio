<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Traguardi: alcuni verificati sullo stato (evaluate), altri assegnati da
 * un evento (award). Persistono attraverso i reset di stagione.
 */
final class Achievements
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::all('SELECT * FROM achievements ORDER BY sort_order');
    }

    /** @return array<string,string> ckey => earned_at */
    public static function earned(int $playerId): array
    {
        $out = [];
        foreach (Database::all('SELECT ckey, earned_at FROM player_achievements WHERE player_id = ?', [$playerId]) as $r) {
            $out[(string) $r['ckey']] = (string) $r['earned_at'];
        }
        return $out;
    }

    public static function points(int $playerId): int
    {
        return (int) (Database::first(
            'SELECT COALESCE(SUM(a.points),0) p FROM player_achievements pa JOIN achievements a ON a.ckey = pa.ckey WHERE pa.player_id = ?',
            [$playerId]
        )['p'] ?? 0);
    }

    /** Assegna un traguardo per evento (idempotente). */
    public static function award(int $playerId, string $ckey): bool
    {
        $ach = Database::first('SELECT name FROM achievements WHERE ckey = ?', [$ckey]);
        if ($ach === null) {
            return false;
        }
        $n = Database::run(
            'INSERT IGNORE INTO player_achievements (player_id, ckey) VALUES (?, ?)',
            [$playerId, $ckey]
        )->rowCount();
        if ($n > 0) {
            Live::alert($playerId, 'achievement', 'Traguardo sbloccato', $ach['name'], '/gioco/traguardi');
        }
        return $n > 0;
    }

    /**
     * Verifica i traguardi "di stato" non ancora ottenuti.
     *
     * @return list<string> ckey appena sbloccati
     */
    public static function evaluate(int $playerId): array
    {
        $have = self::earned($playerId);
        $checks = self::stateChecks();
        $todo = array_diff(array_keys($checks), array_keys($have));
        if ($todo === []) {
            return [];
        }

        $p = Database::first('SELECT credits, kills, deaths, port_busts, experience FROM players WHERE id = ?', [$playerId]);
        if ($p === null) {
            return [];
        }
        $bank = (int) (Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [$playerId])['balance'] ?? 0);
        $ctx = [
            'wealth'    => (int) $p['credits'] + $bank,
            'kills'     => (int) $p['kills'],
            'deaths'    => (int) $p['deaths'],
            'busts'     => (int) $p['port_busts'],
            'visited'   => (int) (Database::first('SELECT COUNT(*) c FROM player_visited_sectors WHERE player_id = ?', [$playerId])['c'] ?? 0),
            'planets'   => (int) (Database::first('SELECT COUNT(*) c FROM planets WHERE owner_player_id = ? AND destroyed = 0', [$playerId])['c'] ?? 0),
            'cit6'      => (int) (Database::first('SELECT COUNT(*) c FROM planets WHERE owner_player_id = ? AND citadel_level >= 6', [$playerId])['c'] ?? 0),
            'quasar'    => (int) (Database::first('SELECT COUNT(*) c FROM planets WHERE owner_player_id = ? AND quasar_level >= 1', [$playerId])['c'] ?? 0),
            'any_planet' => (int) (Database::first('SELECT COUNT(*) c FROM planets WHERE created_by = ?', [$playerId])['c'] ?? 0),
            'trades'    => (int) (Database::first('SELECT COUNT(*) c FROM trade_log WHERE player_id = ? AND port_id > 0', [$playerId])['c'] ?? 0),
            'corp_ceo'  => (int) (Database::first('SELECT COUNT(*) c FROM corporations WHERE ceo_player_id = ?', [$playerId])['c'] ?? 0),
            'ferrengi'  => (int) (Database::first("SELECT COUNT(*) c FROM combat_log WHERE attacker_player_id = ? AND kind = 'npc' AND outcome = 'def_destroyed' AND detail LIKE '%ferrengi%'", [$playerId])['c'] ?? 0),
        ];

        $new = [];
        foreach ($todo as $ckey) {
            if (($checks[$ckey])($ctx)) {
                if (self::award($playerId, $ckey)) {
                    $new[] = $ckey;
                }
            }
        }
        return $new;
    }

    /** @return array<string, callable(array):bool> */
    private static function stateChecks(): array
    {
        return [
            'first_trade'     => static fn ($c) => $c['trades'] >= 1,
            'millionaire'     => static fn ($c) => $c['wealth'] >= 1_000_000,
            'tycoon'          => static fn ($c) => $c['wealth'] >= 10_000_000,
            'first_kill'      => static fn ($c) => $c['kills'] >= 1,
            'warlord'         => static fn ($c) => $c['kills'] >= 25,
            'pod_survivor'    => static fn ($c) => $c['deaths'] >= 1,
            'port_buster'     => static fn ($c) => $c['busts'] >= 1,
            'ferrengi_hunter' => static fn ($c) => $c['ferrengi'] >= 10,
            'explorer_100'    => static fn ($c) => $c['visited'] >= 100,
            'explorer_500'    => static fn ($c) => $c['visited'] >= 500,
            'first_planet'    => static fn ($c) => $c['any_planet'] >= 1,
            'colonizer'       => static fn ($c) => $c['planets'] >= 5,
            'citadel_master'  => static fn ($c) => $c['cit6'] >= 1,
            'quasar_builder'  => static fn ($c) => $c['quasar'] >= 1,
            'corp_founder'    => static fn ($c) => $c['corp_ceo'] >= 1,
        ];
    }
}
