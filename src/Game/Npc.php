<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * NPC: Ferrengi (alieni ostili con regione natale), pirati (predoni di
 * frontiera), mercanti (civili da depredare). Movimento, ingaggio e
 * respawn sono gestiti dal tick.
 *
 * Ogni NPC nasce in una fascia di distanza da Sol (Fasce) che ne fissa forza,
 * crediti a bordo e raggio d'azione: un predone resta nella sua fascia o in
 * quelle adiacenti, i Ferrengi solo nelle fasce esterne. Prima nascevano e
 * vagavano ovunque fuori dalla Federazione, e chi usciva da Sol con la nave
 * iniziale incontrava navi dieci volte piu' forti a un salto di distanza.
 */
final class Npc
{
    private const FERRENGI_NAMES = ['Grubnash', 'Vek Tarr', 'Ssora', 'Krul', 'Nix Ferro', 'Ombra di Cygnus', 'Draak', 'Vorlok'];
    private const PIRATE_NAMES   = ['Sciacallo', 'Lama Nera', 'Corvo', 'Randagio', 'Cicatrice', 'Fantasma', 'Avvoltoio'];
    private const TRADER_NAMES   = ['Mercuria', 'Buon Affare', 'Via della Seta', 'Peregrina', 'Fortuna', 'Rotta d\'Oro'];
    /** Comandanti d'elite: uno o due per fascia, molto piu' forti, con bottino garantito. */
    private const ELITE_PIRATI   = ['Kragg il Macellaio', 'la Vedova Rossa', 'il Conte di Sirio', 'Mastino di Orione', 'Sette Lame', 'la Cicogna Nera'];
    private const ELITE_FERRENGI = ['Daimon Grokk', 'Nagus Vorlath', 'il Liquidatore Brisk', 'Daimon Tarvek', 'Ombra di Ferenginar'];

    /** @return list<array<string,mixed>> */
    public static function inSector(int $sectorId): array
    {
        return array_map(static fn ($n) => [
            'id'       => (int) $n['id'],
            'kind'     => $n['kind'],
            'name'     => $n['name'],
            'ship'     => $n['ship_type'],
            'fighters' => (int) $n['fighters'],
            'scorta'   => (int) ($n['scorta'] ?? 0),
            'elite'    => (bool) ($n['elite'] ?? false),
            'flotta'   => (int) ($n['flotta_id'] ?? 0) ?: (int) $n['id'],
            'hostile'  => (int) $n['aggression'] > 0,
        ], Database::all('SELECT * FROM npcs WHERE sector_id = ? ORDER BY id', [$sectorId]));
    }

    /** @return array<string,mixed>|null */
    public static function get(int $id): ?array
    {
        return Database::first('SELECT * FROM npcs WHERE id = ?', [$id]);
    }

    // --- tick -------------------------------------------------------

    /** @return array{moved:int, engaged:int, spawned:int, despawned:int} */
    public static function tick(): array
    {
        $moved = self::move();
        $engaged = self::engage();
        $spawned = self::spawn();
        $despawned = self::despawn();
        return compact('moved', 'engaged', 'spawned', 'despawned');
    }

    private static function move(): int
    {
        $interval = GameConfig::int('npc.move_interval_min', 3);
        // Una flotta senza capo si scioglie: ognuno torna a muoversi da se'.
        Database::run(
            'UPDATE npcs f LEFT JOIN npcs l ON l.id = f.flotta_id SET f.flotta_id = NULL
             WHERE f.flotta_id IS NOT NULL AND l.id IS NULL'
        );
        // Chi e' in flotta non si muove da solo: segue il capo (sotto).
        $due = Database::all(
            'SELECT * FROM npcs WHERE last_move_at < DATE_SUB(NOW(), INTERVAL ? MINUTE) AND flotta_id IS NULL LIMIT 200',
            [$interval]
        );
        if ($due === []) {
            return 0;
        }

        // Due letture per l'intero gruppo, non due per ogni NPC: prima le
        // rotte uscenti dai settori di partenza, poi in un colpo solo i
        // settori di destinazione, che restano in cache per le scelte sotto.
        $rotte = Universe::warpsFromMany(array_map(static fn ($n) => (int) $n['sector_id'], $due));
        $destinazioni = $rotte === [] ? [] : array_merge(...array_values($rotte));
        if ($destinazioni !== []) {
            Universe::sectorsMany($destinazioni);
        }

        $mosse = [];
        foreach ($due as $npc) {
            // Una squadra d'intercettazione insegue il suo ricercato, un salto
            // alla volta lungo la rotta piu' breve.
            if ($npc['kind'] === 'patrol' && $npc['target_player_id'] !== null) {
                $dove = (int) (Database::first('SELECT sector_id FROM players WHERE id = ?', [(int) $npc['target_player_id']])['sector_id'] ?? 0);
                $rotta = $dove > 0 ? Universe::shortestPath((int) $npc['sector_id'], $dove) : null;
                $mosse[(int) $npc['id']] = $rotta !== null && count($rotta) > 1 ? (int) $rotta[1] : (int) $npc['sector_id'];
                continue;
            }
            // Universe::sector() qui non tocca il database: la cache e' gia' calda.
            $adj = self::destinazioniAmmesse($npc, $rotte[(int) $npc['sector_id']] ?? []);
            if ($adj === []) {
                // Nessuna uscita nel suo territorio: resta, ma il turno passa.
                // Altrimenti tornava in testa alla coda a ogni battito.
                $mosse[(int) $npc['id']] = (int) $npc['sector_id'];
                continue;
            }
            // i mercanti preferiscono i settori con porto
            $pick = $adj[array_rand($adj)];
            if ($npc['kind'] === 'trader') {
                $ports = array_values(array_filter($adj, static fn ($s) => (int) (Universe::sector($s)['has_port'] ?? 0) === 1));
                if ($ports !== [] && mt_rand(0, 1)) {
                    $pick = $ports[array_rand($ports)];
                }
            }
            $mosse[(int) $npc['id']] = $pick;
        }

        $n = self::applicaMosse($mosse);
        // le flotte viaggiano insieme
        Database::run(
            'UPDATE npcs f JOIN npcs l ON l.id = f.flotta_id
                SET f.sector_id = l.sector_id, f.last_move_at = l.last_move_at
              WHERE f.sector_id <> l.sector_id'
        );
        return $n;
    }

    /**
     * Fra i settori adiacenti, quelli in cui questo NPC puo' andare.
     *
     * Nessun ostile entra nello spazio federale. Un predone resta nella sua
     * fascia di nascita o si spinge al massimo una fascia piu' in fuori, mai
     * verso Sol: cosi' la minaccia piu' forte di una fascia e' quella della
     * fascia stessa, e chi resta vicino a casa sa cosa aspettarsi. I Ferrengi
     * restano dalla loro prima fascia in su. I cacciatori di taglie (senza fascia) inseguono il ricercato ovunque
     * fuori dalla Federazione; i mercanti vanno dove vogliono.
     *
     * @param array<string,mixed> $npc
     * @param list<int>           $adj
     * @return list<int>
     */
    public static function destinazioniAmmesse(array $npc, array $adj): array
    {
        $ostile = (int) $npc['aggression'] > 0;
        $nascita = $npc['band'] !== null ? (int) $npc['band'] : null;
        return array_values(array_filter($adj, static function (int $s) use ($npc, $ostile, $nascita): bool {
            $sec = Universe::sector($s);
            if ($sec === null) {
                return false;
            }
            $b = Fasce::diSettore($s);
            if ($ostile && ((bool) $sec['is_fedspace'] || $b <= 0)) {
                return false;
            }
            if ($npc['kind'] === 'ferrengi') {
                return $b >= Fasce::ferrengiDa();
            }
            if ($npc['kind'] === 'patrol') {
                // le ronde restano nelle fasce vicine a Sol
                return $b <= Legge::rondeFinoA();
            }
            if ($npc['kind'] === 'pirate' && $nascita !== null) {
                return $b >= $nascita && $b <= $nascita + 1;
            }
            return true;
        }));
    }

    /**
     * Scrive tutti gli spostamenti in una sola istruzione: con un centinaio di
     * NPC, cento UPDATE separati erano la seconda meta' del costo del tick.
     *
     * @param array<int,int> $mosse id NPC => settore di destinazione
     */
    private static function applicaMosse(array $mosse): int
    {
        if ($mosse === []) {
            return 0;
        }

        $casi = '';
        $params = [];
        foreach ($mosse as $npcId => $sectorId) {
            $casi .= ' WHEN ? THEN ?';
            $params[] = $npcId;
            $params[] = $sectorId;
        }
        $in = implode(',', array_fill(0, count($mosse), '?'));
        $params = [...$params, ...array_keys($mosse)];

        Database::run(
            "UPDATE npcs SET sector_id = CASE id{$casi} END, last_move_at = NOW() WHERE id IN ({$in})",
            $params
        );
        return count($mosse);
    }

    private static function engage(): int
    {
        $rows = Database::all(
            "SELECT n.*, p.id AS player_id, s.band AS settore_band FROM npcs n
             JOIN players p ON p.sector_id = n.sector_id
             JOIN sectors s ON s.id = n.sector_id
             WHERE (n.aggression > 0 OR n.kind = 'patrol') AND s.is_fedspace = 0"
        );
        $seen = [];
        $n = 0;
        foreach ($rows as $r) {
            if (isset($seen[$r['id']])) {
                continue; // un ingaggio per NPC per tick
            }
            // Vicino a Sol gli ostili attaccano di rado, lontano quasi sempre.
            // Prima la probabilita' era una sola, il 65%, ovunque.
            $band = $r['settore_band'] !== null ? (int) $r['settore_band'] : Fasce::diSettore((int) $r['sector_id']);
            $pattuglia = $r['kind'] === 'patrol';
            if (mt_rand(1, 100) > ($pattuglia ? GameConfig::int('legge.ingaggio_pct', 70) : Fasce::ingaggioPct($band))) {
                continue;
            }
            $player = Database::first('SELECT * FROM players WHERE id = ?', [$r['player_id']]);
            // le pattuglie non razziano: la tregua dalle razzie non le ferma
            if (!$pattuglia && Combat::treguaVale($player, $band)) {
                continue;
            }
            $ship = PlayerService::ship((int) $player['ship_id']);
            if ($ship === null || $ship['type_key'] === 'escape_pod') {
                continue;
            }
            // Stesse regole dell'ingresso nel settore: chi e' occultato non si
            // vede, e le amicizie di fazione valgono anche fermi. Prima qui non
            // c'era ne' l'una ne' l'altra cosa.
            if (!empty($ship['cloaked']) || Combat::npcLasciaStare($r, $player)) {
                continue;
            }
            Combat::npcEngagePlayer($r, $player, $ship, true);
            $seen[$r['id']] = true;
            $n++;
        }
        return $n;
    }

    private static function spawn(): int
    {
        $perTick = GameConfig::int('npc.spawn_per_tick', 4);
        $n = 0;

        // Predoni: una quota per fascia, riempita cominciando dalla piu' scoperta.
        $perFascia = [];
        foreach (Database::all(
            "SELECT band, COUNT(*) c FROM npcs WHERE kind = 'pirate' AND band IS NOT NULL GROUP BY band"
        ) as $r) {
            $perFascia[(int) $r['band']] = (int) $r['c'];
        }
        for ($i = 0; $i < $perTick; $i++) {
            $scelta = null;
            $scopertura = 0;
            for ($b = 1; $b <= Fasce::MAX; $b++) {
                $manca = Fasce::predoniVoluti($b) - ($perFascia[$b] ?? 0);
                if ($manca > $scopertura) {
                    [$scelta, $scopertura] = [$b, $manca];
                }
            }
            if ($scelta === null) {
                break;
            }
            if (self::spawnOne('pirate', $scelta) === null) {
                break;
            }
            $perFascia[$scelta] = ($perFascia[$scelta] ?? 0) + 1;
            $n++;
        }

        // Comandanti d'elite: quanti ne vuole ogni fascia, uno alla volta e con
        // calma (elite.rinascita_pct per battito), cosi' abbatterne uno lascia
        // la fascia tranquilla per un po'.
        for ($b = 1; $b <= Fasce::MAX; $b++) {
            $vivi = (int) (Database::first('SELECT COUNT(*) c FROM npcs WHERE elite = 1 AND band = ? AND flotta_id IS NULL', [$b])['c'] ?? 0);
            if ($vivi < Fasce::elitePerFascia($b) && mt_rand(1, 100) <= GameConfig::int('elite.rinascita_pct', 2)) {
                if (self::spawnElite($b) !== null) {
                    $n++;
                }
            }
        }

        foreach ([
            ['ferrengi', GameConfig::int('npc.ferrengi_target', 40)],
            ['trader', GameConfig::int('npc.trader_target', 30)],
        ] as [$kind, $target]) {
            $have = (int) (Database::first('SELECT COUNT(*) c FROM npcs WHERE kind = ?', [$kind])['c'] ?? 0);
            $deficit = min($perTick, max(0, $target - $have));
            for ($i = 0; $i < $deficit; $i++) {
                if (self::spawnOne($kind) !== null) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /**
     * Fa nascere un NPC. Senza fascia la sceglie il tipo: i predoni la meno
     * presidiata, i Ferrengi una delle esterne, i mercanti una qualunque.
     *
     * @return int|null id del nuovo NPC, null se non c'era un settore adatto
     */
    public static function spawnOne(string $kind, ?int $band = null): ?int
    {
        [$sector, $home, $band] = self::spawnSector($kind, $band);
        if ($sector === null) {
            return null;
        }
        [$cMin, $cMax] = Fasce::creditiNpc(max(1, $band));
        $creds = mt_rand($cMin, $cMax);
        $pb = max(1, $band);
        [$name, $type, $ftr, $rating, $creds, $aggr] = match ($kind) {
            'ferrengi' => (static function () use ($pb, $creds): array {
                [$a, $z] = Fasce::ferrengiCaccia($pb);
                return [
                    'Ferrengi ' . self::FERRENGI_NAMES[array_rand(self::FERRENGI_NAMES)],
                    $pb >= 5 || mt_rand(0, 1) ? 'havoc_gunstar' : 'missile_frigate',
                    mt_rand($a, $z), Fasce::predoniRating($pb) + 0.4 + mt_rand(0, 30) / 100,
                    (int) round($creds * 1.6), 100,
                ];
            })(),
            'pirate' => (static function () use ($pb, $creds): array {
                [$a, $z] = Fasce::predoniCaccia($pb);
                return [
                    'Predone ' . self::PIRATE_NAMES[array_rand(self::PIRATE_NAMES)],
                    $pb <= 2 ? 'scout_marauder' : ($pb >= 4 || mt_rand(0, 1) ? 'missile_frigate' : 'scout_marauder'),
                    mt_rand($a, $z), Fasce::predoniRating($pb) + mt_rand(0, 15) / 100,
                    $creds, 100,
                ];
            })(),
            // Un mercantile viaggia scortato, con la scorta proporzionata alla
            // fascia, e porta pochi contanti e molta merce: prima aveva in
            // media 322 caccia, non reagiva e valeva circa 58.000 cr in
            // contanti, un bottino senza rischio.
            default => [
                'Mercantile ' . self::TRADER_NAMES[array_rand(self::TRADER_NAMES)],
                mt_rand(0, 1) ? 'merchant_freighter' : 'cargo_transport',
                mt_rand(50, 700), Fasce::scortaRating($pb) + mt_rand(0, 15) / 100,
                (int) round($creds * GameConfig::float('mercanti.contanti', 0.3)), 0,
            ],
        };
        $scorta = 0;
        if ($kind === 'trader') {
            [$ea, $ez] = Fasce::scortaMercanti($pb);
            $scorta = mt_rand($ea, $ez);
            $ftr += $scorta;
        }
        // scudi in proporzione ai caccia: un quinto, con un po' di varieta'
        $shd = (int) round($ftr * mt_rand(12, 28) / 100);
        // i mercanti lontani viaggiano piu' carichi
        $stiva = $kind === 'trader' ? 300 + 200 * $pb : 20 + 10 * $pb;

        Database::run(
            'INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, band, fighters, scorta, shields, combat_rating, credits, cargo_ore, cargo_org, cargo_equ, aggression)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $kind, $name, $type, $sector, $home, $band, $ftr, $scorta, $shd, round($rating, 2), $creds,
                mt_rand(0, $stiva), mt_rand(0, $stiva), mt_rand(0, $stiva),
                $aggr,
            ]
        );
        $id = Database::lastInsertId();
        // Dalla Frontiera in fuori gli ostili possono viaggiare in flotta.
        if ($kind !== 'trader' && mt_rand(1, 100) <= Fasce::flottaPct($pb)) {
            self::gregari($id, mt_rand(1, 3));
        }
        return $id;
    }

    /**
     * Gregari di una flotta: navi piu' piccole del capo (40-70% dei suoi
     * caccia), nello stesso settore, che lo seguono e combattono con lui.
     */
    public static function gregari(int $capoId, int $quanti): int
    {
        $c = Database::first('SELECT * FROM npcs WHERE id = ?', [$capoId]);
        if ($c === null) {
            return 0;
        }
        for ($i = 0; $i < $quanti; $i++) {
            $f = (int) round((int) $c['fighters'] * mt_rand(40, 70) / 100);
            $nome = $c['kind'] === 'ferrengi'
                ? 'Ferrengi ' . self::FERRENGI_NAMES[array_rand(self::FERRENGI_NAMES)]
                : 'Predone ' . self::PIRATE_NAMES[array_rand(self::PIRATE_NAMES)];
            Database::run(
                'INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, band, flotta_id, fighters, shields, combat_rating, credits, cargo_ore, cargo_org, cargo_equ, aggression, last_move_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?)',
                [$c['kind'], $nome, 'missile_frigate', (int) $c['sector_id'], $c['home_sector'], $c['band'], $capoId, $f,
                 (int) round($f * mt_rand(12, 28) / 100), round((float) $c['combat_rating'] - 0.1, 2),
                 (int) round((int) $c['credits'] * 0.4), (int) $c['aggression'], $c['last_move_at']]
            );
        }
        return $quanti;
    }

    /**
     * Un comandante d'elite: il massimo della fascia per elite.molt_caccia,
     * rating piu' alto, cassa ricca, e dalla Frontiera in fuori due gregari
     * (vicino a Sol nessuno viaggia in flotta). Annunciato via radio: si sa che
     * c'e', e dove comincia a cacciare.
     */
    public static function spawnElite(int $band, bool $annuncia = true): ?int
    {
        $kind = $band >= Fasce::ferrengiDa() ? 'ferrengi' : 'pirate';
        [$sector, $home, $band] = self::spawnSector($kind, $band);
        if ($sector === null) {
            return null;
        }
        [, $z] = $kind === 'ferrengi' ? Fasce::ferrengiCaccia($band) : Fasce::predoniCaccia($band);
        [, $cz] = Fasce::creditiNpc($band);
        $ftr = (int) round($z * GameConfig::float('elite.molt_caccia', 1.5));
        $nomi = $kind === 'ferrengi' ? self::ELITE_FERRENGI : self::ELITE_PIRATI;
        $nome = $nomi[array_rand($nomi)];
        Database::run(
            'INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, band, elite, fighters, shields, combat_rating, credits, cargo_ore, cargo_org, cargo_equ, aggression)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, 100)',
            [$kind, $nome, 'havoc_gunstar', $sector, $home, $band, $ftr, (int) round($ftr * 0.3),
             round(Fasce::predoniRating($band) + 0.6, 2), (int) round($cz * GameConfig::float('elite.molt_crediti', 4)),
             mt_rand(50, 200) * $band, mt_rand(50, 200) * $band, mt_rand(50, 200) * $band]
        );
        $id = Database::lastInsertId();
        if (Fasce::flottaPct($band) > 0) {
            self::gregari($id, 2);
        }
        if ($annuncia) {
            Radio::system("AVVISTAMENTO — {$nome} caccia in " . Fasce::etichetta($band)
            . ' con la sua flotta (' . number_format($ftr, 0, ',', '.') . ' caccia). Bottino garantito a chi lo abbatte.');
        }
        return $id;
    }

    /** @return array{0:?int,1:?int,2:int} settore di spawn, settore natale, fascia */
    private static function spawnSector(string $kind, ?int $band): array
    {
        if ($kind === 'ferrengi') {
            $da = Fasce::ferrengiDa();
            $band = $band !== null ? max($da, min(Fasce::MAX, $band)) : mt_rand($da, Fasce::MAX);
            // Preferiscono la regione natale, ma solo dove questa tocca le fasce
            // esterne: l'Abisso di Cygnus arriva fin quasi a Sol.
            $row = Database::first(
                'SELECT s.id FROM sectors s JOIN regions r ON r.id = s.region_id
                 WHERE r.name = ? AND s.is_fedspace = 0 AND s.band = ? ORDER BY RAND() LIMIT 1',
                [GameConfig::str('npc.ferrengi_home_region', 'Abisso di Cygnus'), $band]
            ) ?? Database::first(
                'SELECT id FROM sectors WHERE is_fedspace = 0 AND band = ? ORDER BY RAND() LIMIT 1',
                [$band]
            );
            return [$row ? (int) $row['id'] : null, $row ? (int) $row['id'] : null, $band];
        }
        if ($band === null) {
            $band = $kind === 'pirate' ? mt_rand(1, Fasce::MAX) : null;
        }
        $row = $band !== null
            ? Database::first('SELECT id, band FROM sectors WHERE is_fedspace = 0 AND band = ? ORDER BY RAND() LIMIT 1', [$band])
            : Database::first('SELECT id, band FROM sectors WHERE is_fedspace = 0 AND band IS NOT NULL ORDER BY RAND() LIMIT 1');
        return [$row ? (int) $row['id'] : null, null, $row ? (int) $row['band'] : (int) $band];
    }

    private static function despawn(): int
    {
        // NPC finiti in Fedspace (Ferrengi/pirati), fuori dal loro territorio
        // (le soglie delle fasce possono cambiare dal pannello), o troppo
        // vecchi e inerti.
        return Database::run(
            "DELETE n FROM npcs n JOIN sectors s ON s.id = n.sector_id
             WHERE (n.kind IN ('ferrengi','pirate') AND s.is_fedspace = 1)
                OR (n.kind = 'ferrengi' AND s.band < ?)
                OR (n.kind = 'pirate' AND n.band IS NOT NULL AND (s.band < n.band OR s.band > n.band + 1))
                OR (n.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))",
            [Fasce::ferrengiDa()]
        )->rowCount();
    }

    public static function remove(int $id): void
    {
        Database::run('DELETE FROM npcs WHERE id = ?', [$id]);
    }
}
