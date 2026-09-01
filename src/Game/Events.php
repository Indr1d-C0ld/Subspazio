<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Eventi globali: shock di mercato, incursioni Ferrengi, brillamenti solari,
 * stagione delle taglie. Throttlati dal tick; annunciati via radio.
 */
final class Events
{
    /** @return list<array<string,mixed>> */
    public static function active(): array
    {
        return Database::all(
            'SELECT id, kind, title, body, ends_at FROM events
             WHERE ends_at IS NULL OR ends_at > NOW()
             ORDER BY id DESC LIMIT 6'
        );
    }

    public static function tick(): ?string
    {
        self::expireDue();

        $interval = GameConfig::int('events.interval_min', 240);
        $last = GameConfig::str('events.last_run', '');
        if ($last !== '' && (time() - strtotime($last)) < $interval * 60) {
            return null;
        }
        GameConfig::set('events.last_run', date('Y-m-d H:i:s'));

        if (mt_rand(1, 100) > GameConfig::int('events.chance_pct', 40)) {
            return null;
        }

        return match (mt_rand(1, 5)) {
            1 => self::marketShock(),
            2 => self::ferrengiIncursion(),
            3 => self::solarFlare(),
            4 => self::bountySeason(),
            default => self::pirateSurge(),
        };
    }

    /** Forza un evento specifico (pannello admin). */
    public static function force(string $kind): ?string
    {
        return match ($kind) {
            'market_shock'      => self::marketShock(),
            'ferrengi_incursion' => self::ferrengiIncursion(),
            'solar_flare'       => self::solarFlare(),
            'bounty_season'     => self::bountySeason(),
            'pirate_surge'      => self::pirateSurge(),
            default             => null,
        };
    }

    private static function record(string $kind, string $title, string $body, ?int $durationHours, array $payload = []): string
    {
        Database::run(
            'INSERT INTO events (kind, title, body, payload, ends_at) VALUES (?, ?, ?, ?, ?)',
            [$kind, $title, $body, $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
             $durationHours === null ? null : date('Y-m-d H:i:s', time() + $durationHours * 3600)]
        );
        Radio::system("EVENTO — {$title}: {$body}");
        return $kind;
    }

    private static function marketShock(): string
    {
        $c = ['ore', 'organics', 'equipment'][mt_rand(0, 2)];
        $region = Database::first('SELECT r.id, r.name FROM regions r ORDER BY RAND() LIMIT 1');
        $dir = mt_rand(0, 1) ? 1 : -1;
        $factor = 1 + $dir * (0.25 + mt_rand(0, 20) / 100);
        Database::run(
            'UPDATE commodity_market SET base_value = LEAST(anchor*2, GREATEST(anchor*0.4, base_value * ?)) WHERE region_id = ? AND commodity = ?',
            [$factor, $region['id'], $c]
        );
        $lbl = Economy::label($c);
        $word = $dir > 0 ? 'impennata' : 'crollo';
        return self::record('market_shock', "Shock di mercato: {$lbl}",
            "Nella regione {$region['name']} il prezzo di {$lbl} subisce un {$word}.", 6,
            ['region' => (int) $region['id'], 'commodity' => $c, 'factor' => $factor]);
    }

    private static function solarFlare(): string
    {
        $region = Database::first('SELECT r.id, r.name FROM regions r WHERE r.kind <> \'federation\' ORDER BY RAND() LIMIT 1');
        Database::run(
            'UPDATE ports p JOIN sectors s ON s.id = p.sector_id
             SET p.ore_stock = FLOOR(p.ore_stock*0.7), p.org_stock = FLOOR(p.org_stock*0.7), p.equ_stock = FLOOR(p.equ_stock*0.7)
             WHERE s.region_id = ?',
            [$region['id']]
        );
        return self::record('solar_flare', 'Brillamento solare',
            "Un violento brillamento investe la regione {$region['name']}: le scorte dei porti sono decimate.", 3,
            ['region' => (int) $region['id']]);
    }

    private static function ferrengiIncursion(): string
    {
        $wave = mt_rand(6, 14);
        for ($i = 0; $i < $wave; $i++) {
            Npc::spawnOne('ferrengi');
        }
        return self::record('ferrengi_incursion', 'Incursione Ferrengi',
            "Una flotta Ferrengi di {$wave} navi si riversa nello spazio conosciuto. Comandanti, alla larga dai settori profondi.", 4,
            ['wave' => $wave]);
    }

    private static function pirateSurge(): string
    {
        $wave = mt_rand(5, 10);
        for ($i = 0; $i < $wave; $i++) {
            Npc::spawnOne('pirate');
        }
        return self::record('pirate_surge', 'Ondata di pirateria',
            "Bande di predoni ({$wave}) infestano le rotte di frontiera.", 4, ['wave' => $wave]);
    }

    private static function bountySeason(): string
    {
        GameConfig::set('combat.bounty_mult', '2');
        return self::record('bounty_season', 'Stagione delle taglie',
            'La Federazione raddoppia le taglie: cacciare fuorilegge non e\' mai stato cosi\' redditizio.', 12,
            ['mult' => 2]);
    }

    public static function expireDue(): void
    {
        $done = Database::all(
            "SELECT * FROM events WHERE reverted = 0 AND ends_at IS NOT NULL AND ends_at <= NOW()"
        );
        foreach ($done as $e) {
            if ($e['kind'] === 'bounty_season') {
                GameConfig::set('combat.bounty_mult', '1');
            }
            Database::run('UPDATE events SET reverted = 1 WHERE id = ?', [$e['id']]);
            Radio::system("EVENTO concluso — {$e['title']}.");
        }
    }
}
