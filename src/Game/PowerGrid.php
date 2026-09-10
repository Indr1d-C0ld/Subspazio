<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Griglia di potenza (EPS, slice B2). Il reattore dà un budget fisso di tacche
 * da ripartire su 4 canali. Potenziarne uno significa a digiuno un altro:
 * la somma è sempre pips_total. Ogni tacca di scostamento dal nominale sposta
 * la stat del canale di step_pct; il moltiplicatore risultante è applicato in
 * coda a ShipStats::effective(). Ri-allocare costa realloc_turn_cost turni e
 * adegua subito la carica degli scudi (il "deviare potenza agli scudi").
 */
final class PowerGrid
{
    /** @var list<string> */
    public const CHANNELS = ['shields', 'weapons', 'engines', 'sensors'];

    public const LABEL = [
        'shields' => 'Scudi', 'weapons' => 'Armi', 'engines' => 'Motori', 'sensors' => 'Sensori',
    ];
    public const ICON = [
        'shields' => '🛡', 'weapons' => '⚔', 'engines' => '🚀', 'sensors' => '📡',
    ];
    private const COL = [
        'shields' => 'eps_shields', 'weapons' => 'eps_weapons',
        'engines' => 'eps_engines', 'sensors' => 'eps_sensors',
    ];

    public static function pipsTotal(): int
    {
        return max(4, GameConfig::int('eps.pips_total', 8));
    }

    /** Tacche "nominali" per canale (nessun effetto). */
    public static function nominal(): int
    {
        return max(1, intdiv(self::pipsTotal(), count(self::CHANNELS)));
    }

    /** Massimo per canale = doppio del nominale (→ +step_pct·nominal in su). */
    public static function maxPerChannel(): int
    {
        return self::nominal() * 2;
    }

    public static function stepPct(): float
    {
        return GameConfig::float('eps.step_pct', 12.5);
    }

    public static function reallocTurnCost(): int
    {
        return max(0, GameConfig::int('eps.realloc_turn_cost', 1));
    }

    public static function mult(int $pips): float
    {
        return round(1 + ($pips - self::nominal()) * self::stepPct() / 100, 4);
    }

    /** @param array<string,mixed> $ship @return array<string,int> canale => tacche */
    public static function read(array $ship): array
    {
        $nom = self::nominal();
        $max = self::maxPerChannel();
        $out = [];
        foreach (self::CHANNELS as $c) {
            $out[$c] = max(0, min($max, (int) ($ship[self::COL[$c]] ?? $nom)));
        }
        return $out;
    }

    /** @param array<string,mixed> $ship @return array<string,float> canale => moltiplicatore */
    public static function mults(array $ship): array
    {
        $out = [];
        foreach (self::read($ship) as $c => $p) {
            $out[$c] = self::mult($p);
        }
        return $out;
    }

    /** @param array<string,int> $alloc */
    public static function isNominal(array $alloc): bool
    {
        $nom = self::nominal();
        foreach (self::CHANNELS as $c) {
            if ((int) ($alloc[$c] ?? $nom) !== $nom) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $in
     * @return array{ok:bool, alloc?:array<string,int>, error?:string}
     */
    public static function validate(array $in): array
    {
        $max = self::maxPerChannel();
        $alloc = [];
        $sum = 0;
        foreach (self::CHANNELS as $c) {
            if (!isset($in[$c]) || !is_numeric($in[$c])) {
                return ['ok' => false, 'error' => 'Allocazione incompleta.'];
            }
            $v = (int) $in[$c];
            if ($v < 0 || $v > $max) {
                return ['ok' => false, 'error' => "Ogni canale va da 0 a {$max} tacche."];
            }
            $alloc[$c] = $v;
            $sum += $v;
        }
        if ($sum !== self::pipsTotal()) {
            return ['ok' => false, 'error' => 'Vanno distribuite esattamente ' . self::pipsTotal() . ' tacche (usate: ' . $sum . ').'];
        }
        return ['ok' => true, 'alloc' => $alloc];
    }

    /**
     * Persiste una nuova allocazione. Se identica all'attuale non fa nulla e
     * non costa turni. Altrimenti costa realloc_turn_cost turni e adegua
     * subito capacità e carica degli scudi al nuovo canale.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @param array<string,mixed> $in
     * @return array{ok:bool, error?:string, cost?:int}
     */
    public static function save(array $player, array $ship, array $in): array
    {
        if (($ship['type_key'] ?? '') === 'escape_pod') {
            return ['ok' => false, 'error' => 'La capsula di salvataggio non ha una griglia di potenza.'];
        }

        $v = self::validate($in);
        if (!$v['ok']) {
            return $v;
        }
        $new = $v['alloc'];
        $cur = self::read($ship);
        if ($new === $cur) {
            return ['ok' => true, 'cost' => 0];
        }

        $cost = self::reallocTurnCost();
        $player = TurnManager::sync($player);
        if ((int) $player['turns'] < $cost) {
            return ['ok' => false, 'error' => "Turni insufficienti per ri-tarare la griglia (servono {$cost})."];
        }

        // Adeguamento immediato degli scudi se cambia il canale Scudi.
        $oldSm = self::mult($cur['shields']);
        $newSm = self::mult($new['shields']);
        $shieldSql = '';
        $shieldParams = [];
        if ($newSm !== $oldSm) {
            $eff = PlayerService::ship((int) $ship['id']);
            $maxNominal = ((int) ($eff['max_shields'] ?? 0)) / max(0.01, $oldSm);
            $newMax = (int) round($maxNominal * $newSm);
            $rawShields = (int) ($ship['shields'] ?? 0);
            $newShields = (int) min($newMax, round($rawShields * $newSm / max(0.01, $oldSm)));
            $shieldSql = ', shields = ?';
            $shieldParams = [max(0, $newShields)];
        }

        $sets = [];
        $params = [];
        foreach (self::CHANNELS as $c) {
            $sets[] = self::COL[$c] . ' = ?';
            $params[] = $new[$c];
        }
        $params = array_merge($params, $shieldParams, [(int) $ship['id']]);
        Database::run('UPDATE ships SET ' . implode(', ', $sets) . $shieldSql . ' WHERE id = ?', $params);

        if ($cost > 0) {
            Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$cost, (int) $player['id']]);
        }
        return ['ok' => true, 'cost' => $cost];
    }

    /**
     * Descrittore per la UI.
     * @param array<string,mixed> $ship
     * @return list<array{key:string,label:string,icon:string,pips:int,mult:float,pct:int,effect:string}>
     */
    public static function describe(array $ship): array
    {
        $alloc = self::read($ship);
        $rows = [];
        foreach (self::CHANNELS as $c) {
            $m = self::mult($alloc[$c]);
            $pct = (int) round(($m - 1) * 100);
            $rows[] = [
                'key'    => $c,
                'label'  => self::LABEL[$c],
                'icon'   => self::ICON[$c],
                'pips'   => $alloc[$c],
                'mult'   => $m,
                'pct'    => $pct,
                'strain' => $alloc[$c] >= self::maxPerChannel(),
                'effect' => self::effectText($c, $m, $pct),
            ];
        }
        return $rows;
    }

    private static function effectText(string $channel, float $mult, int $pct): string
    {
        if ($pct === 0) {
            return 'nominale';
        }
        $s = ($pct > 0 ? '+' : '') . $pct . '%';
        return match ($channel) {
            'shields' => "capacità e carica scudi {$s}",
            'weapons' => "potenza di fuoco {$s}",
            'engines' => $mult >= 1.2 ? 'salti −1 turno (scafi da 2+ turni/warp)'
                : ($mult <= 0.8 ? 'salti +1 turno' : 'nessun effetto sui salti (serve ±2 tacche)'),
            'sensors' => $mult >= 1.2 ? 'scanner potenziato di un livello'
                : ($mult <= 0.8 ? 'scanner declassato di un livello' : 'nessun effetto sui sensori (serve ±2 tacche)'),
            default   => $s,
        };
    }
}
