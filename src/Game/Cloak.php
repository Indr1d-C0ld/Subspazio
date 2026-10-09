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

    // --- riserva di energia ------------------------------------------------

    public static function caricaMax(): int
    {
        return max(1, GameConfig::int('cloak.carica_max', 8));
    }

    public static function ricaricaMin(): int
    {
        return max(1, GameConfig::int('cloak.ricarica_min', 10));
    }

    /**
     * Cariche disponibili: il valore in tabella piu' una carica ogni
     * cloak.ricarica_min minuti da quando e' stato scritto, fino al massimo.
     *
     * @param array<string,mixed> $ship riga con cloak_carica e cloak_carica_at
     */
    public static function carica(array $ship): int
    {
        $v = (int) ($ship['cloak_carica'] ?? self::caricaMax());
        if (!empty($ship['cloak_carica_at'])) {
            $v += intdiv(max(0, time() - (int) strtotime((string) $ship['cloak_carica_at'])), self::ricaricaMin() * 60);
        }
        return max(0, min(self::caricaMax(), $v));
    }

    /**
     * Un salto occultato consuma una carica. Falso se la riserva e' vuota.
     * La scrittura e' vincolata al valore letto: due salti insieme non
     * consumano una carica sola.
     */
    public static function consuma(int $shipId): bool
    {
        // Due salti insieme (due schede) leggono la stessa riserva: il secondo
        // trova la riga cambiata e rilegge, invece di dichiararla esaurita.
        for ($tentativo = 0; $tentativo < 3; $tentativo++) {
            $s = Database::first('SELECT cloak_carica, cloak_carica_at FROM ships WHERE id = ?', [$shipId]);
            if ($s === null) {
                return false;
            }
            $ora = self::carica($s);
            if ($ora < 1) {
                return false;
            }
            if (Database::run(
                'UPDATE ships SET cloak_carica = ?, cloak_carica_at = ? WHERE id = ? AND cloak_carica = ? AND cloak_carica_at <=> ?',
                [$ora - 1, self::orologioDopo($s, $ora), $shipId, $s['cloak_carica'], $s['cloak_carica_at']]
            )->rowCount() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Da quando contare la prossima carica. Si avanza dei soli periodi interi
     * gia' maturati: riportare l'orologio a adesso buttava la ricarica in
     * corso, e chi saltava occultato ogni nove minuti non ricaricava mai.
     * A riserva piena l'orologio riparte da adesso.
     *
     * @param array<string,mixed> $s
     */
    private static function orologioDopo(array $s, int $ora): string
    {
        $adesso = date('Y-m-d H:i:s');
        if ($ora >= self::caricaMax() || empty($s['cloak_carica_at'])) {
            return $adesso;
        }
        $da = (int) strtotime((string) $s['cloak_carica_at']);
        $periodo = self::ricaricaMin() * 60;
        $maturati = intdiv(max(0, time() - $da), $periodo);
        return date('Y-m-d H:i:s', min(time(), $da + $maturati * $periodo));
    }

    // --- rilevamento ------------------------------------------------------------

    /** Probabilita' (%) che un aggancio scopra una nave occultata. */
    public static function rilevamentoPct(array $ship, int $band, bool $vigile): float
    {
        $v = array_map('floatval', explode(',', GameConfig::str('cloak.rilevamento_pct', '5,10,20,30,40')));
        $base = $band <= 0 ? 0.0 : ($v[max(1, min(count($v), $band)) - 1] ?? 0.0);
        $pct = $base + ($vigile ? GameConfig::int('cloak.rilevamento_vigili', 20) : 0)
            - (float) (($ship['mod_effects'] ?? [])['cloak_stealth_pct'] ?? 0);
        return max(0.0, min(95.0, $pct));
    }

    /**
     * L'aggancio di un NPC scopre la nave occultata? Pattuglie ed elite
     * (vigili) hanno sensori migliori.
     *
     * @param array<string,mixed> $ship nave effettiva
     * @param array<string,mixed> $npc
     */
    public static function scoperta(array $ship, array $npc): bool
    {
        $vigile = $npc['kind'] === 'patrol' || !empty($npc['elite']);
        $p = self::rilevamentoPct($ship, Fasce::diSettore((int) $npc['sector_id']), $vigile);
        return $p > 0 && mt_rand(1, 10000) <= $p * 100;
    }

    /**
     * Attiva/disattiva. @return array{ok:bool, error?:string, cloaked?:bool}
     * @param array<string,mixed> $player @param array<string,mixed> $ship
     */
    public static function toggle(array $player, array $ship, bool $on): array
    {
        // Spegnere si puo' sempre, anche su una nave che il dispositivo non
        // l'ha piu' (cambiata al Cantiere mentre si accendeva): prima restava
        // occultata senza alcun modo di farla riapparire.
        if ($on && !self::has($ship)) {
            return ['ok' => false, 'error' => 'Nessun dispositivo di occultamento installato.'];
        }
        if ($on === self::isActive($ship)) {
            return ['ok' => true, 'cloaked' => $on];
        }
        if ($on && self::carica(Database::first('SELECT cloak_carica, cloak_carica_at FROM ships WHERE id = ?', [(int) $ship['id']]) ?? []) < 1) {
            return ['ok' => false, 'error' => 'Riserva del dispositivo esaurita: una carica torna ogni ' . self::ricaricaMin() . ' minuti.'];
        }
        if ($on && self::fedspaceForbidden()) {
            $sec = Universe::sector((int) $player['sector_id']);
            if ($sec !== null && (bool) $sec['is_fedspace']) {
                return ['ok' => false, 'error' => 'I sensori della Federazione impediscono l\'occultamento in questo spazio.'];
            }
        }
        // Dispositivo e settore si verificano anche nella scrittura: un
        // acquisto al Cantiere o un salto in Fedspace arrivati nel frattempo
        // lasciavano accesa una nave senza dispositivo, o occultata in Fedspace.
        $acceso = $on
            ? Database::run(
                "UPDATE ships SET cloaked = 1 WHERE id = ? AND dev_cloak = 1 AND type_key <> 'escape_pod'
                   AND (? = 0 OR NOT EXISTS (SELECT 1 FROM sectors s WHERE s.id = ships.sector_id AND s.is_fedspace = 1))",
                [(int) $ship['id'], self::fedspaceForbidden() ? 1 : 0]
            )->rowCount() > 0
            : Database::run('UPDATE ships SET cloaked = 0 WHERE id = ?', [(int) $ship['id']])->rowCount() >= 0;
        // rowCount conta le righe cambiate: un doppio «accendi» trova la nave
        // gia' occultata e non cambia nulla, ma e' un successo, non un errore
        if (!$acceso && !($on && (int) (Database::first('SELECT cloaked FROM ships WHERE id = ?', [(int) $ship['id']])['cloaked'] ?? 0) === 1)) {
            return ['ok' => false, 'error' => 'La nave e\' cambiata nel frattempo: ricarica la plancia e riprova.'];
        }
        ShipLog::write((int) $player['id'], 'system', 'info',
            $on ? 'Occultamento attivato' : 'Occultamento disattivato',
            $on
                ? 'La nave sparisce dai sensori. Ogni salto consuma una carica della riserva; ogni aggancio puo\' scoprirti, '
                  . 'piu\' facilmente lontano da Sol e contro pattuglie ed elite. Cade se apri il fuoco, commerci, attracchi a un '
                  . 'pianeta, spogli un relitto, estrai o scansioni. Non funziona in Fedspace, non ferma mine e Quasar. +'
                  . self::warpPenalty() . ' turno per warp.'
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
