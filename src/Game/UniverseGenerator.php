<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Genera un universo di gioco: settori con coordinate per la mappa, regioni,
 * e un grafo di warp connesso (con alcuni collegamenti a senso unico e
 * vicoli ciechi, nello spirito di TradeWars). Fedspace (settori 1..N) e'
 * fortemente interconnesso e ospita lo StarDock.
 */
final class UniverseGenerator
{
    private const REGION_NAMES = [
        'Distese di Vega', 'Fascia di Orione', 'Marca di Kepler', 'Abisso di Cygnus',
        'Contado di Lyra', 'Solco di Perseo', 'Deriva di Eridano', 'Confini di Draco',
        'Landa di Bootes',
    ];

    private const STAR_NAMES = [
        'Sol', 'Alpha Centauri', 'Sirio', 'Procione', 'Altair', 'Vega', 'Arturo',
        'Aldebaran', 'Rigel', 'Betelgeuse', 'Antares', 'Deneb', 'Fomalhaut', 'Capella',
        'Polluce', 'Regolo', 'Spica', 'Bellatrix', 'Mizar', 'Alcor', 'Castore', 'Canopo',
        'Achernar', 'Hadar', 'Acrux', 'Mimosa', 'Dubhe', 'Merak', 'Alkaid', 'Polaris',
        'Elnath', 'Alnilam', 'Saiph', 'Menkar', 'Diphda', 'Sadr', 'Nunki', 'Rasalhague',
        'Alphecca', 'Cor Caroli', 'Wezen', 'Adhara', 'Naos', 'Avior', 'Atria', 'Peacock',
    ];

    private const NEBULAE = [
        'Nebulosa del Velo', 'Nebulosa Fiamma', 'Nube Cremisi', 'Velo di Iperione',
        'Nebulosa Spettro', 'Fornace di Tycho', 'Nube Cerulea',
    ];

    private int $count;
    private int $fedspaceMax;
    private int $stardock;
    private float $density;

    /** @var array<int, array{x:float,y:float,region:int,fed:bool}> */
    private array $nodes = [];
    /** @var array<int, array<int,bool>> archi diretti from => {to: true} */
    private array $edges = [];

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $this->count       = max(20, (int) ($cfg['sectors'] ?? 1000));
        $this->fedspaceMax = max(3, min(20, (int) ($cfg['fedspace_max'] ?? 10)));
        $this->stardock    = max(1, (int) ($cfg['stardock_sector'] ?? 1));
        $this->density     = max(2.0, min(6.0, (float) ($cfg['warp_density'] ?? 3.2)));
    }

    /**
     * @return array<string,mixed> statistiche di generazione
     */
    public function generate(bool $force): array
    {
        if (Universe::exists() && !$force) {
            throw new \RuntimeException(
                'Un universo esiste gia\'. Usa --force per rigenerarlo (i giocatori verranno riportati allo StarDock).'
            );
        }

        $t0 = microtime(true);
        mt_srand();

        $this->layout();
        $this->connect();
        $this->hardenFedspace();
        $this->repairReachability();

        $pdo = Database::pdo();
        $hadPlayers = (int) (Database::first('SELECT COUNT(*) AS c FROM players')['c'] ?? 0);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['move_log', 'player_visited_sectors', 'warps', 'sectors', 'regions'] as $t) {
            $pdo->exec("TRUNCATE TABLE {$t}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $regionIds = $this->insertRegions();
        $this->insertSectors($regionIds);
        $directed = $this->insertWarps();

        if ($hadPlayers > 0) {
            Database::run('UPDATE players SET sector_id = ?, total_warps = total_warps', [$this->stardock]);
            Database::run('UPDATE ships SET sector_id = ?', [$this->stardock]);
            Database::run(
                'INSERT IGNORE INTO player_visited_sectors (player_id, sector_id)
                 SELECT id, ? FROM players',
                [$this->stardock]
            );
        }

        $now = date('Y-m-d H:i:s');
        $this->setConfig('universe.generated_at', $now);
        $this->setConfig('universe.sectors', (string) $this->count);
        $this->setConfig('game.status', 'active');

        $oneWay = 0;
        foreach ($this->edges as $from => $tos) {
            foreach ($tos as $to => $_) {
                if (!isset($this->edges[$to][$from])) {
                    $oneWay++;
                }
            }
        }
        $deadEnds = 0;
        for ($i = 1; $i <= $this->count; $i++) {
            if (count($this->edges[$i] ?? []) <= 1) {
                $deadEnds++;
            }
        }

        return [
            'sectors'        => $this->count,
            'warps_directed' => $directed,
            'avg_degree'     => round($directed / $this->count, 2),
            'one_way'        => $oneWay,
            'dead_ends'      => $deadEnds,
            'fedspace'       => $this->fedspaceMax,
            'stardock'       => $this->stardock,
            'players_moved'  => $hadPlayers,
            'seconds'        => round(microtime(true) - $t0, 2),
        ];
    }

    // --- geometria ---------------------------------------------------------

    private function layout(): void
    {
        $R = 1000.0;
        for ($i = 1; $i <= $this->count; $i++) {
            $fed = $i <= $this->fedspaceMax;
            if ($fed) {
                $r = $this->frand(0, 70);
                $a = $this->frand(0, 2 * M_PI);
            } else {
                $r = $R * (0.12 + 0.88 * sqrt($this->frand(0, 1)));
                $a = $this->frand(0, 2 * M_PI);
            }
            $this->nodes[$i] = [
                'x'      => cos($a) * $r,
                'y'      => sin($a) * $r,
                'region' => 0,
                'fed'    => $fed,
            ];
        }

        // assegnazione regione per settore non-fed: spicchio angolare
        $wedges = count(self::REGION_NAMES);
        for ($i = $this->fedspaceMax + 1; $i <= $this->count; $i++) {
            $n = $this->nodes[$i];
            $ang = atan2($n['y'], $n['x']) + M_PI; // 0..2pi
            $this->nodes[$i]['region'] = (int) floor($ang / (2 * M_PI) * $wedges) % $wedges;
        }
    }

    private function dist(int $a, int $b): float
    {
        $dx = $this->nodes[$a]['x'] - $this->nodes[$b]['x'];
        $dy = $this->nodes[$a]['y'] - $this->nodes[$b]['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @param list<int> $pool @return list<int> i $k piu' vicini a $target */
    private function nearest(int $target, array $pool, int $k): array
    {
        usort($pool, fn ($a, $b) => $this->dist($target, $a) <=> $this->dist($target, $b));
        return array_slice($pool, 0, $k);
    }

    // --- grafo -----------------------------------------------------------

    private function addEdge(int $a, int $b, bool $bidir = true): void
    {
        if ($a === $b) {
            return;
        }
        $this->edges[$a][$b] = true;
        if ($bidir) {
            $this->edges[$b][$a] = true;
        }
    }

    private function connect(): void
    {
        // 1) albero ricoprente geometricamente plausibile -> connettivita' garantita
        $order = range(1, $this->count);
        // teniamo fedspace in testa, mescoliamo il resto
        $rest = array_slice($order, $this->fedspaceMax);
        shuffle($rest);
        $order = array_merge(range(1, $this->fedspaceMax), $rest);

        $placed = [$order[0]];
        for ($idx = 1; $idx < count($order); $idx++) {
            $s = $order[$idx];
            $candidates = $this->nearest($s, $placed, min(6, count($placed)));
            $parent = $candidates[mt_rand(0, count($candidates) - 1)];
            $this->addEdge($s, $parent, true);
            $placed[] = $s;
        }

        // 2) archi extra fino alla densita' obiettivo
        $targetDirected = (int) round($this->count * $this->density);
        $current = $this->countDirected();
        $guard = $targetDirected * 4;

        while ($current < $targetDirected && $guard-- > 0) {
            $a = mt_rand(1, $this->count);
            $pool = [];
            for ($j = 0; $j < 14; $j++) {
                $b = mt_rand(1, $this->count);
                if ($b !== $a && !isset($this->edges[$a][$b])) {
                    $pool[] = $b;
                }
            }
            if ($pool === []) {
                continue;
            }
            $b = $this->nearest($a, $pool, 1)[0];
            $bidir = mt_rand(1, 100) > 15; // ~15% a senso unico
            $this->addEdge($a, $b, $bidir);
            $current = $this->countDirected();
        }
    }

    private function hardenFedspace(): void
    {
        // mesh completa fra i settori di fedspace
        for ($i = 1; $i <= $this->fedspaceMax; $i++) {
            for ($j = $i + 1; $j <= $this->fedspaceMax; $j++) {
                $this->addEdge($i, $j, true);
            }
        }
        // 3 gateway bidirezionali verso la frontiera vicina
        $frontier = range($this->fedspaceMax + 1, $this->count);
        $gateways = $this->nearest($this->stardock, $frontier, 3);
        foreach ($gateways as $g) {
            $hub = mt_rand(1, $this->fedspaceMax);
            $this->addEdge($hub, $g, true);
        }
    }

    /**
     * Con i warp a senso unico si possono creare settori-trappola: garantiamo
     * che da ogni settore si possa raggiungere lo StarDock e viceversa.
     */
    private function repairReachability(): void
    {
        // raggiungibilita' VERSO lo stardock: BFS sul grafo invertito
        $toStardock = $this->bfs($this->stardock, true);
        for ($s = 1; $s <= $this->count; $s++) {
            if (isset($toStardock[$s])) {
                continue;
            }
            // collega $s a un vicino che sa raggiungere lo stardock
            $ok = array_keys($toStardock);
            $near = $this->nearest($s, $ok, 1)[0] ?? $this->stardock;
            $this->addEdge($s, $near, true);
            $toStardock = $this->bfs($this->stardock, true);
        }

        // raggiungibilita' DALLO stardock in avanti
        $fromStardock = $this->bfs($this->stardock, false);
        for ($s = 1; $s <= $this->count; $s++) {
            if (isset($fromStardock[$s])) {
                continue;
            }
            $ok = array_keys($fromStardock);
            $near = $this->nearest($s, $ok, 1)[0] ?? $this->stardock;
            $this->addEdge($near, $s, true);
            $fromStardock = $this->bfs($this->stardock, false);
        }
    }

    /** @return array<int,bool> insieme dei settori raggiunti */
    private function bfs(int $start, bool $reverse): array
    {
        $seen = [$start => true];
        $q = [$start];
        while ($q !== []) {
            $cur = array_shift($q);
            if ($reverse) {
                // archi entranti: chi punta a $cur
                foreach ($this->edges as $from => $tos) {
                    if (isset($tos[$cur]) && !isset($seen[$from])) {
                        $seen[$from] = true;
                        $q[] = $from;
                    }
                }
            } else {
                foreach ($this->edges[$cur] ?? [] as $to => $_) {
                    if (!isset($seen[$to])) {
                        $seen[$to] = true;
                        $q[] = $to;
                    }
                }
            }
        }
        return $seen;
    }

    private function countDirected(): int
    {
        $n = 0;
        foreach ($this->edges as $tos) {
            $n += count($tos);
        }
        return $n;
    }

    // --- persistenza ----------------------------------------------------

    /** @return list<int> id regione per indice (0 = Federazione) */
    private function insertRegions(): array
    {
        Database::run(
            "INSERT INTO regions (name, kind, color) VALUES ('Federazione', 'federation', '#4bb4ea')"
        );
        $ids = [(int) Database::lastInsertId()];

        $palette = ['#7a5cff', '#e0693e', '#3ea88a', '#c94f8a', '#c8b45a', '#5a7fc8', '#b06a3a', '#6a9a4a', '#9a5ac8'];
        foreach (self::REGION_NAMES as $i => $name) {
            $kind = $i % 3 === 2 ? 'deep' : 'frontier';
            Database::run(
                'INSERT INTO regions (name, kind, color) VALUES (?, ?, ?)',
                [$name, $kind, $palette[$i % count($palette)]]
            );
            $ids[] = (int) Database::lastInsertId();
        }
        return $ids;
    }

    /** @param list<int> $regionIds */
    private function insertSectors(array $regionIds): void
    {
        $namedNonFed = [];
        $starPool = self::STAR_NAMES;
        shuffle($starPool);

        $rows = [];
        $params = [];
        $flush = function () use (&$rows, &$params): void {
            if ($rows === []) {
                return;
            }
            $sql = 'INSERT INTO sectors (id, name, region_id, is_fedspace, is_stardock, nebula, x, y) VALUES '
                . implode(',', $rows);
            Database::run($sql, $params);
            $rows = [];
            $params = [];
        };

        for ($i = 1; $i <= $this->count; $i++) {
            $node = $this->nodes[$i];
            $isFed = $node['fed'];
            $isDock = $i === $this->stardock;

            if ($isFed) {
                $name = self::STAR_NAMES[$i - 1] ?? "Settore {$i}";
                if ($isDock) {
                    $name = 'Sol';
                }
                $regionId = $regionIds[0];
            } else {
                $regionId = $regionIds[($node['region'] % (count($regionIds) - 1)) + 1];
                if (mt_rand(1, 100) <= 6) {
                    $name = array_shift($starPool) ?? "Settore {$i}";
                    $namedNonFed[] = $i;
                } else {
                    $name = "Settore {$i}";
                }
            }

            $nebula = (!$isFed && mt_rand(1, 100) <= 8)
                ? self::NEBULAE[array_rand(self::NEBULAE)]
                : null;

            $rows[] = '(?,?,?,?,?,?,?,?)';
            array_push(
                $params,
                $i,
                $name,
                $regionId,
                $isFed ? 1 : 0,
                $isDock ? 1 : 0,
                $nebula,
                round($node['x'], 2),
                round($node['y'], 2),
            );

            if (count($rows) >= 200) {
                $flush();
            }
        }
        $flush();
    }

    private function insertWarps(): int
    {
        $rows = [];
        $params = [];
        $count = 0;
        $flush = function () use (&$rows, &$params): void {
            if ($rows === []) {
                return;
            }
            Database::run(
                'INSERT IGNORE INTO warps (from_sector, to_sector) VALUES ' . implode(',', $rows),
                $params
            );
            $rows = [];
            $params = [];
        };

        foreach ($this->edges as $from => $tos) {
            foreach ($tos as $to => $_) {
                $rows[] = '(?,?)';
                array_push($params, $from, $to);
                $count++;
                if (count($rows) >= 400) {
                    $flush();
                }
            }
        }
        $flush();
        return $count;
    }

    private function setConfig(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO game_config (ckey, cvalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue)',
            [$key, $value]
        );
    }

    private function frand(float $min, float $max): float
    {
        return $min + ($max - $min) * (mt_rand() / mt_getrandmax());
    }
}
