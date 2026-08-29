<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Contenitore per-richiesta dello stato di gioco corrente,
 * popolato dal middleware 'player'.
 */
final class Ctx
{
    /** @var array<string,mixed> */
    public static array $player = [];
    /** @var array<string,mixed> */
    public static array $ship = [];
    public static bool $created = false;

    public static function has(): bool
    {
        return self::$player !== [];
    }
}
