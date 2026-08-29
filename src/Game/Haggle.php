<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Contrattazione a offerta / controproposta. Lo stato della trattativa vive
 * nella sessione (una per giocatore alla volta); il prezzo concordato non
 * arriva mai dal client come valore fidato: Economy::settle lo rivalida.
 */
final class Haggle
{
    private const KEY = 'haggle';
    private const CD  = 'haggle_cd';

    /** @return array<string,mixed>|null */
    public static function active(): ?array
    {
        $s = $_SESSION[self::KEY] ?? null;
        if (!is_array($s)) {
            return null;
        }
        if (($s['expires'] ?? 0) < time()) {
            unset($_SESSION[self::KEY]);
            return null;
        }
        return $s;
    }

    public static function abort(): void
    {
        unset($_SESSION[self::KEY]);
    }

    private static function expBonus(array $player): float
    {
        $factor = GameConfig::float('economy.haggle.exp_factor', 0.00002);
        return min(0.05, max(0.0, (int) ($player['experience'] ?? 0) * $factor));
    }

    private static function cooldownLeft(int $portId): int
    {
        $until = $_SESSION[self::CD][$portId] ?? 0;
        return max(0, (int) $until - time());
    }

    /**
     * Apre una trattativa. Ritorna la prima offerta del porto.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{ok:bool, error?:string, ...}
     */
    public static function open(array $player, array $ship, string $commodity, string $action, int $qty): array
    {
        if (!in_array($commodity, Economy::COMMODITIES, true) || !in_array($action, ['buy', 'sell'], true)) {
            return ['ok' => false, 'error' => 'Parametri non validi.'];
        }
        $sectorId = (int) $player['sector_id'];
        $port = Economy::portAt($sectorId);
        if ($port === null) {
            return ['ok' => false, 'error' => 'Nessun porto in questo settore.'];
        }
        $cd = self::cooldownLeft((int) $port['id']);
        if ($cd > 0) {
            return ['ok' => false, 'error' => "Il mercante ti ignora ancora per {$cd}s."];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantita\' non valida.'];
        }
        $max = Economy::maxQty($port, $player, $ship, $commodity, $action);
        if ($max <= 0) {
            return ['ok' => false, 'error' => 'Scambio non possibile ora (scorte, stive o crediti).'];
        }
        if ($qty > $max) {
            return ['ok' => false, 'error' => "Massimo trattabile ora: {$max}.", 'max' => $max];
        }

        $fairTotal = Economy::fairTotal($port, $commodity, $action, $qty);
        $openMargin = GameConfig::float('economy.haggle.open_margin', 0.15);
        $maxRounds = max(1, GameConfig::int('economy.haggle.max_rounds', 5));

        $portOffer = $action === 'buy'
            ? (int) round($fairTotal * (1 + $openMargin))
            : (int) round($fairTotal * (1 - $openMargin));

        $sess = [
            'token'      => bin2hex(random_bytes(16)),
            'sector_id'  => $sectorId,
            'port_id'    => (int) $port['id'],
            'commodity'  => $commodity,
            'action'     => $action,
            'qty'        => $qty,
            'fair_total' => $fairTotal,
            'port_offer' => $portOffer,
            'round'      => 1,
            'max_rounds' => $maxRounds,
            'final'      => false,
            'expires'    => time() + 300,
        ];
        $_SESSION[self::KEY] = $sess;

        return self::view($sess, 'open');
    }

    /**
     * Controproposta del giocatore.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function counter(array $player, array $ship, string $token, int $playerOffer): array
    {
        $s = self::active();
        if ($s === null || !hash_equals((string) $s['token'], $token)) {
            return ['ok' => false, 'error' => 'Trattativa non attiva. Riapri la contrattazione.'];
        }
        if ($s['sector_id'] !== (int) $player['sector_id']) {
            self::abort();
            return ['ok' => false, 'error' => 'Hai lasciato il settore del porto.'];
        }
        if ($playerOffer <= 0) {
            return ['ok' => false, 'error' => 'Offerta non valida.'];
        }

        $fair = (int) $s['fair_total'];
        $eb = self::expBonus($player);
        $acceptBand = GameConfig::float('economy.haggle.accept_band', 0.012) + $eb;
        $walkBand   = max(0.05, GameConfig::float('economy.haggle.walk_band', 0.14) - $eb);
        $concession = GameConfig::float('economy.haggle.concession', 0.28);
        $minMargin  = GameConfig::float('economy.haggle.min_margin', 0.05);

        // limiti oltre i quali il porto non va: tiene sempre un margine sul prezzo equo
        $floor = (int) ceil($fair * (1 + $minMargin));   // per un acquisto del giocatore
        $ceil  = (int) floor($fair * (1 - $minMargin));  // per una vendita del giocatore

        $accepted = false;
        $walked = false;
        $dealTotal = null;
        $wasFinal = !empty($s['final']);

        if ($s['action'] === 'buy') {
            $near = max($floor, (int) round($s['port_offer'] * (1 - $acceptBand)));
            if ($playerOffer < $fair * (1 - $walkBand)) {
                $walked = true;
            } elseif ($playerOffer >= (int) $s['port_offer']) {
                $accepted = true;
                $dealTotal = (int) $s['port_offer'];
            } elseif ($playerOffer >= $near) {
                $accepted = true;
                $dealTotal = $playerOffer;
            } else {
                $gap = $s['port_offer'] - $playerOffer;
                $new = (int) round($s['port_offer'] - $concession * $gap);
                $s['port_offer'] = max($new, $floor, $playerOffer + 1);
            }
        } else { // sell
            $near = min($ceil, (int) round($s['port_offer'] * (1 + $acceptBand)));
            if ($playerOffer > $fair * (1 + $walkBand)) {
                $walked = true;
            } elseif ($playerOffer <= (int) $s['port_offer']) {
                $accepted = true;
                $dealTotal = (int) $s['port_offer'];
            } elseif ($playerOffer <= $near) {
                $accepted = true;
                $dealTotal = $playerOffer;
            } else {
                $gap = $playerOffer - $s['port_offer'];
                $new = (int) round($s['port_offer'] + $concession * $gap);
                $s['port_offer'] = min($new, $ceil, $playerOffer - 1);
            }
        }

        // dopo l'offerta finale il giocatore ottiene comunque quel prezzo
        // (a meno di un lowball da walk, gia' gestito sopra): niente rilanci infiniti
        if (!$accepted && !$walked && $wasFinal) {
            $accepted = true;
            $dealTotal = (int) $s['port_offer'];
        }

        if ($walked) {
            $cd = GameConfig::int('economy.haggle.cooldown_s', 20);
            $_SESSION[self::CD][$s['port_id']] = time() + $cd;
            self::abort();
            return ['ok' => true, 'result' => 'walk',
                'message' => 'Il mercante scuote la testa e taglia corto. Riprova piu\' tardi.'];
        }

        if ($accepted) {
            return self::finalize($player, $ship, $s, (int) $dealTotal);
        }

        $s['round']++;
        if ($s['round'] > $s['max_rounds']) {
            $s['final'] = true;
            $s['port_offer'] = $s['action'] === 'buy' ? $floor : $ceil;
        }
        $_SESSION[self::KEY] = $s;

        return self::view($s, $s['final'] ? 'final' : 'counter');
    }

    /**
     * Il giocatore accetta l'offerta corrente del porto.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function accept(array $player, array $ship, string $token): array
    {
        $s = self::active();
        if ($s === null || !hash_equals((string) $s['token'], $token)) {
            return ['ok' => false, 'error' => 'Trattativa non attiva.'];
        }
        return self::finalize($player, $ship, $s, (int) $s['port_offer']);
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @param array<string,mixed> $s
     */
    private static function finalize(array $player, array $ship, array $s, int $total): array
    {
        $res = Economy::settle(
            $player,
            $ship,
            (int) $s['sector_id'],
            (string) $s['commodity'],
            (string) $s['action'],
            (int) $s['qty'],
            $total,
            (int) $s['round'],
        );
        self::abort();
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Scambio non riuscito.'];
        }
        return [
            'ok'       => true,
            'result'   => 'accepted',
            'total'    => $res['total'],
            'unit'     => $res['unit'],
            'fair_total' => $res['fair_total'],
            'qty'      => (int) $s['qty'],
            'commodity' => (string) $s['commodity'],
            'action'   => (string) $s['action'],
            'rounds'   => (int) $s['round'],
            'player'   => $res['player'],
            'ship'     => $res['ship'],
            'port'     => $res['port'],
        ];
    }

    /** @param array<string,mixed> $s */
    private static function view(array $s, string $result): array
    {
        return [
            'ok'         => true,
            'result'     => $result,           // open | counter | final
            'token'      => $s['token'],
            'commodity'  => $s['commodity'],
            'action'     => $s['action'],
            'qty'        => $s['qty'],
            'round'      => $s['round'],
            'max_rounds' => $s['max_rounds'],
            'final'      => $s['final'],
            'port_offer' => $s['port_offer'],
            'port_unit'  => round($s['port_offer'] / max(1, $s['qty']), 2),
            'fair_total' => $s['fair_total'],
        ];
    }
}
