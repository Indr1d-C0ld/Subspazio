<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * "Primi passi": una checklist non invasiva per i nuovi comandanti.
 * Il progresso è dedotto dai dati esistenti, non memorizzato; su players
 * si tiene solo lo stato (attivo / nascosto / completato+premiato).
 */
final class Onboarding
{
    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return list<array{key:string,label:string,done:bool,link:string}>
     */
    public static function steps(array $player, array $ship): array
    {
        $pid = (int) $player['id'];
        $has = static function (string $sql, array $args) : bool {
            try {
                return Database::first($sql, $args) !== null;
            } catch (\Throwable) {
                return false;
            }
        };

        return [
            [
                'key' => 'warp', 'label' => 'Fai il tuo primo salto di warp',
                'done' => (int) ($player['total_warps'] ?? 0) >= 1, 'link' => url('/gioco'),
            ],
            [
                'key' => 'trade', 'label' => 'Compra o vendi merce in un porto',
                'done' => $has('SELECT 1 FROM trade_log WHERE player_id = ? LIMIT 1', [$pid]), 'link' => url('/gioco/porto'),
            ],
            [
                'key' => 'bank', 'label' => 'Deposita crediti alla Banca IGB (StarDock)',
                'done' => $has('SELECT 1 FROM bank_accounts WHERE player_id = ? AND balance > 0 LIMIT 1', [$pid]), 'link' => url('/gioco/banca'),
            ],
            [
                'key' => 'kill', 'label' => 'Abbatti un NPC ostile (pirata o Ferrengi)',
                'done' => (int) ($player['kills'] ?? 0) >= 1, 'link' => url('/gioco'),
            ],
            [
                'key' => 'scan', 'label' => 'Scansiona un settore di frontiera',
                'done' => $has('SELECT 1 FROM player_feature_state WHERE player_id = ? LIMIT 1', [$pid]), 'link' => url('/gioco'),
            ],
            [
                'key' => 'module', 'label' => 'Installa un modulo sulla nave (Officina)',
                'done' => $has('SELECT 1 FROM ship_modules WHERE ship_id = ? LIMIT 1', [(int) ($ship['id'] ?? 0)]), 'link' => url('/gioco/moduli'),
            ],
            [
                'key' => 'crew', 'label' => 'Assumi un ufficiale (Equipaggio, StarDock)',
                'done' => $has('SELECT 1 FROM officers WHERE player_id = ? LIMIT 1', [$pid]), 'link' => url('/gioco/equipaggio'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{steps:list<array<string,mixed>>,done:int,total:int}|null  null = non mostrare
     */
    public static function forView(array $player, array $ship): ?array
    {
        if ((int) ($player['onboarding_state'] ?? 0) !== 0) {
            return null;
        }
        $steps = self::steps($player, $ship);
        $done = count(array_filter($steps, static fn ($s) => $s['done']));
        if ($done >= count($steps)) {
            return null; // tutto fatto: il premio è già stato dato in maybeReward()
        }
        return ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }

    /**
     * Se la checklist è completa e non ancora premiata, accredita la ricompensa
     * e chiude l'onboarding. Da chiamare nel controller della plancia.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function maybeReward(array $player, array $ship): ?int
    {
        if ((int) ($player['onboarding_state'] ?? 0) !== 0) {
            return null;
        }
        foreach (self::steps($player, $ship) as $s) {
            if (!$s['done']) {
                return null;
            }
        }
        $reward = GameConfig::int('onboarding.reward_credits', 5000);
        Database::run(
            'UPDATE players SET onboarding_state = 2, credits = credits + ? WHERE id = ? AND onboarding_state = 0',
            [$reward, (int) $player['id']]
        );
        return $reward;
    }

    public static function dismiss(int $playerId): void
    {
        Database::run('UPDATE players SET onboarding_state = 1 WHERE id = ? AND onboarding_state = 0', [$playerId]);
    }
}
