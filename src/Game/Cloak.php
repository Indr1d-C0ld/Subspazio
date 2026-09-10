<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Dispositivo di occultamento (slice dispositivi-fantasma). Prerequisito:
 * `ships.dev_cloak` (hardware Cantiere). Lo stato ON/OFF è `ships.cloaked`.
 */
final class Cloak
{
    public static function warpPenalty(): int
    {
        return max(0, GameConfig::int('cloak.warp_turn_penalty', 1));
    }

    public static function fedspaceForbidden(): bool
    {
        return GameConfig::bool('cloak.fedspace_forbidden', true);
    }

    /** @param array<string,mixed> $ship */
    public static function has(array $ship): bool
    {
        return !empty($ship['dev_cloak']) && ($ship['type_key'] ?? '') !== 'escape_pod';
    }

    /** @param array<string,mixed> $ship */
    public static function isActive(array $ship): bool
    {
        return !empty($ship['cloaked']);
    }

    /** Un osservatore con scanner olografico vede i cloaked nel proprio settore. */
    public static function seesCloaked(?string $scannerLevel): bool
    {
        return $scannerLevel === 'holo';
    }

    /**
     * Attiva/disattiva. @return array{ok:bool, error?:string, cloaked?:bool}
     * @param array<string,mixed> $player @param array<string,mixed> $ship
     */
    public static function toggle(array $player, array $ship, bool $on): array
    {
        if (!self::has($ship)) {
            return ['ok' => false, 'error' => 'Nessun dispositivo di occultamento installato.'];
        }
        if ($on === self::isActive($ship)) {
            return ['ok' => true, 'cloaked' => $on];
        }
        if ($on && self::fedspaceForbidden()) {
            $sec = Universe::sector((int) $player['sector_id']);
            if ($sec !== null && (bool) $sec['is_fedspace']) {
                return ['ok' => false, 'error' => 'I sensori della Federazione impediscono l\'occultamento in questo spazio.'];
            }
        }
        Database::run('UPDATE ships SET cloaked = ? WHERE id = ?', [$on ? 1 : 0, (int) $ship['id']]);
        ShipLog::write((int) $player['id'], 'system', 'info',
            $on ? 'Occultamento attivato' : 'Occultamento disattivato',
            $on
                ? 'La nave sparisce dai sensori. Non funziona in Fedspace, non ti protegge da mine e Quasar, e cade se apri il fuoco o attracchi. +' . self::warpPenalty() . ' turno per warp.'
                : 'La nave torna visibile.',
            (int) $player['sector_id']);
        return ['ok' => true, 'cloaked' => $on];
    }

    /**
     * Forza il decloak (fuoco aperto / Fedspace / StarDock / transwarp).
     * @return bool true se era occultata
     */
    public static function drop(int $shipId, string $reason): bool
    {
        $s = Database::first('SELECT cloaked, player_id FROM ships WHERE id = ?', [$shipId]);
        if ($s === null || (int) $s['cloaked'] !== 1) {
            return false;
        }
        Database::run('UPDATE ships SET cloaked = 0 WHERE id = ?', [$shipId]);
        if (!empty($s['player_id'])) {
            ShipLog::write((int) $s['player_id'], 'system', 'warning', 'Occultamento caduto',
                "La nave è tornata visibile ({$reason}).");
        }
        return true;
    }
}
