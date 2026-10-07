<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Fasce di rischio concentriche attorno a Sol (lo StarDock).
 *
 * La fascia 0 e' lo spazio federale; fuori, cinque anelli per distanza sulla
 * mappa galattica. Piu' ci si allontana, piu' le minacce sono forti e
 * frequenti, e piu' rendono commercio, bottino, anomalie e missioni. E'
 * l'unica fonte della «distanza»: anche la vecchia distinzione fra frontiera e
 * frontiera profonda ora discende da qui (tipo()).
 *
 * I valori per fascia stanno in chiavi di configurazione a cinque voci, una
 * per fascia dalla I alla V, separate da virgole ("15,30,45,60,75"); gli
 * intervalli si scrivono "min-max".
 */
final class Fasce
{
    public const MAX = 5;

    public const NOMI = [
        0 => 'Spazio federale',
        1 => 'Cintura di Sol',
        2 => 'Anello dei Coloni',
        3 => 'Frontiera',
        4 => 'Marche Remote',
        5 => 'Orlo del Buio',
    ];

    public const ROMANI = [0 => '0', 1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    /** Una riga per fascia, per la plancia e la guida. */
    public const RIASSUNTI = [
        0 => 'Protetto dalla Federazione: niente combattimenti, niente NPC ostili.',
        1 => 'Predoni isolati e deboli: una nave appena uscita dal Cantiere li respinge. Chi perde viene razziato, non distrutto.',
        2 => 'Predoni piu\' agguerriti, ma ancora razzie e non distruzioni. Margini e bottino modesti.',
        3 => 'Da qui chi perde contro un NPC perde la nave. Rotte e bottino cominciano a valere il rischio.',
        4 => 'Territorio dei Ferrengi. Minacce pesanti e frequenti, porti generosi, moduli rari.',
        5 => 'Il limite dello spazio conosciuto: le flotte piu\' forti e le ricompense piu\' ricche.',
    ];

    /** @var array<string,list<string>> */
    private static array $cacheListe = [];

    // --- geografia --------------------------------------------------------

    public static function nome(int $band): string
    {
        return self::NOMI[max(0, min(self::MAX, $band))];
    }

    public static function etichetta(int $band): string
    {
        return $band <= 0 ? self::NOMI[0] : 'Fascia ' . self::ROMANI[min(self::MAX, $band)] . ' · ' . self::nome($band);
    }

    /** Fascia di un settore (0 per lo spazio federale). */
    public static function diSettore(int $sectorId): int
    {
        $s = Universe::sector($sectorId);
        if ($s === null) {
            return 0;
        }
        if (isset($s['band'])) {
            return (int) $s['band'];
        }
        // colonna non ancora calcolata: la si ricava al volo
        return (bool) $s['is_fedspace'] ? 0 : self::daCoordinate((float) $s['x'], (float) $s['y']);
    }

    /**
     * Il vecchio «tipo di regione», ora derivato dalla distanza: federale,
     * frontiera (I-III) o frontiera profonda (IV-V).
     */
    public static function tipo(int $band): string
    {
        return match (true) {
            $band <= 0 => 'federation',
            $band >= 4 => 'deep',
            default    => 'frontier',
        };
    }

    /** @return list<float> soglie I|II, II|III, III|IV, IV|V come frazioni del raggio */
    public static function soglie(): array
    {
        $out = [];
        foreach (explode(',', GameConfig::str('fasce.soglie', '0.36,0.53,0.69,0.84')) as $v) {
            $out[] = max(0.0, min(1.0, (float) trim($v)));
        }
        sort($out);
        return count($out) === self::MAX - 1 ? $out : [0.36, 0.53, 0.69, 0.84];
    }

    /** @return array{cx:float, cy:float, rmax:float} */
    public static function centro(): array
    {
        $c = Database::first('SELECT x, y FROM sectors WHERE is_stardock = 1 ORDER BY id LIMIT 1')
            ?? ['x' => 0, 'y' => 0];
        $m = Database::first(
            'SELECT MAX(SQRT(POW(x - ?, 2) + POW(y - ?, 2))) r FROM sectors',
            [(float) $c['x'], (float) $c['y']]
        );
        return ['cx' => (float) $c['x'], 'cy' => (float) $c['y'], 'rmax' => max(1.0, (float) ($m['r'] ?? 1))];
    }

    private static function daCoordinate(float $x, float $y): int
    {
        $c = self::centro();
        $r = hypot($x - $c['cx'], $y - $c['cy']) / $c['rmax'];
        $band = 1;
        foreach (self::soglie() as $s) {
            if ($r >= $s) {
                $band++;
            }
        }
        return $band;
    }

    /**
     * Riassegna la fascia a ogni settore. Lo fa il clock quando le soglie nel
     * pannello cambiano, e il generatore dopo un Big Bang.
     */
    public static function ricalcola(): int
    {
        $c = self::centro();
        $s = self::soglie();
        $d = 'SQRT(POW(x - ?, 2) + POW(y - ?, 2)) / ?';
        $n = Database::run(
            "UPDATE sectors SET band = CASE
                WHEN is_fedspace = 1 THEN 0
                WHEN {$d} < ? THEN 1
                WHEN {$d} < ? THEN 2
                WHEN {$d} < ? THEN 3
                WHEN {$d} < ? THEN 4
                ELSE 5 END",
            [
                $c['cx'], $c['cy'], $c['rmax'], $s[0],
                $c['cx'], $c['cy'], $c['rmax'], $s[1],
                $c['cx'], $c['cy'], $c['rmax'], $s[2],
                $c['cx'], $c['cy'], $c['rmax'], $s[3],
            ]
        )->rowCount();
        GameConfig::set('fasce.soglie_applicate', GameConfig::str('fasce.soglie', '0.36,0.53,0.69,0.84'));
        Universe::forget();
        return $n;
    }

    /** Dal clock: ricalcola solo se le soglie sono cambiate o mai applicate. */
    public static function allinea(): int
    {
        $voluto = GameConfig::str('fasce.soglie', '0.36,0.53,0.69,0.84');
        $senza = Database::first('SELECT 1 x FROM sectors WHERE band IS NULL LIMIT 1') !== null;
        if (!$senza && GameConfig::str('fasce.soglie_applicate', '') === $voluto) {
            return 0;
        }
        return self::ricalcola();
    }

    // --- valori per fascia ------------------------------------------------

    /** Probabilita' (%) che un NPC ostile ingaggi chi e' nel suo settore. */
    public static function ingaggioPct(int $band): int
    {
        return $band <= 0 ? 0 : (int) self::voce(GameConfig::str('fasce.ingaggio_pct', '15,30,45,60,75'), $band);
    }

    /** Premio (%) che i porti pagano su cio' che comprano dal giocatore. */
    public static function commercioPct(int $band): float
    {
        return $band <= 0 ? 0.0 : (float) self::voce(GameConfig::str('fasce.commercio_pct', '0,5,10,15,20'), $band);
    }

    public static function xpMult(int $band): float
    {
        return $band <= 0 ? 1.0 : (float) self::voce(GameConfig::str('fasce.xp_mult', '0.5,0.75,1,1.5,2'), $band);
    }

    /** Moltiplicatore della probabilita' di recuperare un modulo. */
    public static function bottinoMult(int $band): float
    {
        return $band <= 0 ? 1.0 : (float) self::voce(GameConfig::str('fasce.bottino_mult', '0.7,0.85,1,1.3,1.7'), $band);
    }

    public static function predoniVoluti(int $band): int
    {
        return $band <= 0 ? 0 : max(0, (int) self::voce(GameConfig::str('fasce.predoni', '6,8,9,9,8'), $band));
    }

    /** @return array{0:int,1:int} */
    public static function predoniCaccia(int $band): array
    {
        return self::intervallo(GameConfig::str('fasce.predoni_caccia', '80-250,250-650,700-1800,1800-4500,4500-11000'), max(1, $band));
    }

    public static function predoniRating(int $band): float
    {
        return (float) self::voce(GameConfig::str('fasce.predoni_rating', '0.7,0.85,1.0,1.2,1.4'), max(1, $band));
    }

    /** Prima fascia in cui nascono e si muovono i Ferrengi. */
    public static function ferrengiDa(): int
    {
        return max(1, min(self::MAX, GameConfig::int('fasce.ferrengi_da', 4)));
    }

    /** @return array{0:int,1:int} */
    public static function ferrengiCaccia(int $band): array
    {
        return self::intervallo(GameConfig::str('fasce.ferrengi_caccia', '1500-3500,2000-4500,2500-6000,3500-8000,6000-14000'), max(1, $band));
    }

    /** @return array{0:int,1:int} crediti a bordo di un predone di questa fascia */
    public static function creditiNpc(int $band): array
    {
        return self::intervallo(GameConfig::str('fasce.crediti_npc', '800-3000,2500-8000,6000-20000,15000-50000,40000-120000'), max(1, $band));
    }

    /** @return array{0:int,1:int} ricchezza di relitti, depositi, anomalie e giacimenti */
    public static function ricchezza(int $band): array
    {
        return self::intervallo(GameConfig::str('fasce.ricchezza', '1-2,1-3,2-3,2-4,3-5'), max(1, $band));
    }

    public static function difficoltaMissioni(int $band): int
    {
        return $band <= 0 ? 7 : (int) self::voce(GameConfig::str('fasce.missioni_diff', '8,10,12,15,18'), $band);
    }

    /** Prima fascia in cui compaiono i pericoli ambientali. */
    public static function pericoliDa(): int
    {
        return max(1, GameConfig::int('fasce.pericoli_da', 2));
    }

    /** Fino a questa fascia chi perde contro un NPC viene razziato, non distrutto. */
    public static function razziaFinoA(): int
    {
        return GameConfig::int('fasce.razzia_fino_a', 2);
    }

    public static function razziaCreditiPct(): int
    {
        return max(0, min(100, GameConfig::int('fasce.razzia_crediti_pct', 10)));
    }

    public static function treguaMin(): int
    {
        return max(0, GameConfig::int('fasce.tregua_min', 30));
    }

    /** Salire di almeno tante fasce in un salto chiede conferma. */
    public static function avvisoSalto(): int
    {
        return max(1, GameConfig::int('fasce.avviso_salto', 2));
    }

    /**
     * Pesi di rarita' del bottino in questa fascia.
     *
     * @return array<string,int>
     */
    public static function pesiRarita(int $band): array
    {
        $righe = explode('|', GameConfig::str('fasce.rarita', 'civ:100,mil:25,exp:4,xeno:1,precursor:0|civ:100,mil:40,exp:10,xeno:2,precursor:0|civ:80,mil:50,exp:20,xeno:6,precursor:1|civ:50,mil:50,exp:30,xeno:12,precursor:3|civ:25,mil:45,exp:40,xeno:20,precursor:6'));
        $riga = $righe[max(1, min(count($righe), $band)) - 1] ?? '';
        $out = [];
        foreach (explode(',', $riga) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2 && in_array($p[0], Loot::RARITIES, true)) {
                $out[$p[0]] = max(0, (int) $p[1]);
            }
        }
        return $out;
    }

    /** @return array{0:int,1:int} caccia della scorta di un mercantile */
    public static function scortaMercanti(int $band): array
    {
        return self::intervallo(GameConfig::str('mercanti.scorta_caccia', '300-800,800-2500,2500-7000,7000-20000,20000-60000'), max(1, $band));
    }

    public static function scortaRating(int $band): float
    {
        return (float) self::voce(GameConfig::str('mercanti.scorta_rating', '0.9,1.0,1.2,1.4,1.6'), max(1, $band));
    }

    /** Probabilita' (%) che una richiesta di soccorso trovi una pattuglia. */
    public static function soccorsoPct(int $band): int
    {
        return $band <= 0 ? 100 : (int) self::voce(GameConfig::str('mercanti.soccorso_pct', '100,90,75,50,30'), $band);
    }

    /** La tregua dalle razzie protegge ancora questo comandante? */
    public static function inTregua(array $player): bool
    {
        return !empty($player['tregua_npc_until']) && strtotime((string) $player['tregua_npc_until']) > time();
    }

    // --- interni -----------------------------------------------------------

    /** La voce della fascia $band (1..5) in una lista a cinque voci. */
    private static function voce(string $lista, int $band): string
    {
        $v = self::$cacheListe[$lista] ??= array_map('trim', explode(',', $lista));
        return $v[max(1, min(count($v), $band)) - 1] ?? '0';
    }

    /** @return array{0:int,1:int} */
    private static function intervallo(string $lista, int $band): array
    {
        $p = array_map('intval', explode('-', self::voce($lista, $band), 2));
        $a = max(0, $p[0] ?? 0);
        $b = max($a, $p[1] ?? $a);
        return [$a, $b];
    }
}
