<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Configurazione di gioco persistita nella tabella game_config
 * (modificabile a runtime dall'admin). Cache per-richiesta.
 */
final class GameConfig
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT ckey, cvalue FROM game_config') as $row) {
                self::$cache[(string) $row['ckey']] = (string) $row['cvalue'];
            }
        }
        return self::$cache;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::load()[$key] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::load()[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::load()[$key] ?? null;
        return is_numeric($v) ? (float) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::load()[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::load();
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO game_config (ckey, cvalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue)',
            [$key, $value]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }
}
