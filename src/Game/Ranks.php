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

    public static function alignmentLabel(int $alignment): string
    {
        return match (true) {
            $alignment <= -500 => 'Corsaro',
            $alignment <= -100 => 'Fuorilegge',
            $alignment < 100   => 'Neutrale',
            $alignment < 500   => 'Benefattore',
            default            => 'Eroe della Federazione',
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
