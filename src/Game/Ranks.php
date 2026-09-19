<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Titoli di grado (dall'esperienza) ed etichette di allineamento.
 */
final class Ranks
{
    /** @var list<array{0:int,1:string}> soglia minima => titolo */
    private const TITLES = [
        [0,      'Recluta'],
        [250,    'Mercante'],
        [1000,   'Navigatore'],
        [3000,   'Capitano'],
        [8000,   'Comandante'],
        [20000,  'Ammiraglio'],
        [50000,  'Signore dello Spazio'],
        [120000, 'Leggenda'],
    ];

    public static function title(int $experience): string
    {
        $t = self::TITLES[0][1];
        foreach (self::TITLES as [$min, $name]) {
            if ($experience >= $min) {
                $t = $name;
            }
        }
        return $t;
    }

    /**
     * Le quattro soglie della scala di allineamento, in ordine garantito.
     *
     * Le due interne sono configurabili: `ranks.evil_threshold` segna dove si
     * diventa fuorilegge, `ranks.good_threshold` dove si diventa benefattore.
     * Prima soltanto la prima esisteva davvero, e per giunta comandava solo il
     * COMPORTAMENTO (isEvil) e non l'etichetta, che aveva le soglie scritte a
     * mano: chi spostava la manopola otteneva un comandante chiamato
     * «Fuorilegge» che i cannoni planetari e i Ferrengi trattavano da neutrale.
     *
     * I due gradini esterni restano fissi ma vengono schiacciati contro quelli
     * interni, cosi' una manopola messa male puo' al massimo svuotare una
     * fascia — mai invertirne due, che e' il modo in cui una scala smette di
     * voler dire qualcosa.
     *
     * @return array{0:int,1:int,2:int,3:int} [corsaro, fuorilegge, benefattore, eroe]
     */
    private static function alignmentBands(): array
    {
        $evil = GameConfig::int('ranks.evil_threshold', -100);
        $good = GameConfig::int('ranks.good_threshold', 100);
        return [min(-500, $evil), $evil, $good, max(500, $good)];
    }

    public static function alignmentLabel(int $alignment): string
    {
        [$corsaro, $fuorilegge, $benefattore, $eroe] = self::alignmentBands();
        return match (true) {
            $alignment <= $corsaro    => 'Corsaro',
            $alignment <= $fuorilegge => 'Fuorilegge',
            $alignment < $benefattore => 'Neutrale',
            $alignment < $eroe        => 'Benefattore',
            default                   => 'Eroe della Federazione',
        };
    }

    public static function isEvil(int $alignment): bool
    {
        return $alignment <= GameConfig::int('ranks.evil_threshold', -100);
    }

    public static function isProtected(array $player): bool
    {
        $until = $player['protected_until'] ?? null;
        return $until !== null && strtotime((string) $until) > time();
    }
}
