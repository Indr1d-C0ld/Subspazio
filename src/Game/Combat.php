<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Motore di combattimento: duello a caccia con scudi, assalto ai porti,
 * intercettazioni all'ingresso in un settore (mine, caccia dispiegati),
 * distruzione della nave e capsula di salvataggio.
 */
final class Combat
{
    // --- motore ---------------------------------------------------------

    /**
     * Scambio di volee fra attaccante e difensore. Gli scudi assorbono
     * i colpi 1:1 prima dei caccia.
     *
     * @return array{rounds:int, att_ftr:int, att_shd:int, att_lost:int, def_ftr:int, def_shd:int, def_lost:int}
     */
    public static function duel(int $aF, int $aS, float $aM, int $dF, int $dS, float $dM): array
    {
        $kr   = GameConfig::float('combat.round_kill_ratio', 0.65);
        $var  = GameConfig::float('combat.variance', 0.35);
        $maxR = GameConfig::int('combat.max_rounds', 12);

        $aF0 = $aF;
        $dF0 = $dF;
        $rounds = 0;
        $trace = [['r' => 0, 'aF' => $aF, 'aS' => $aS, 'dF' => $dF, 'dS' => $dS]];

        while ($aF > 0 && $dF > 0 && $rounds < $maxR) {
            $rounds++;

            [$dS, $rem] = self::soak($dS, $aF * $kr * $aM * self::jitter($var));
            $dHit = (int) floor($rem);
            $dF = max(0, $dF - $dHit);

            $aHit = 0;
            if ($dF > 0) {
                [$aS, $rem2] = self::soak($aS, $dF * $kr * $dM * self::jitter($var));
                $aHit = (int) floor($rem2);
                $aF = max(0, $aF - $aHit);
            }

            $trace[] = ['r' => $rounds, 'aF' => $aF, 'aS' => $aS, 'dF' => $dF, 'dS' => $dS, 'aHit' => $aHit, 'dHit' => $dHit];
        }

        return [
            'rounds'   => $rounds,
            'att_ftr'  => $aF, 'att_shd' => $aS, 'att_lost' => $aF0 - $aF,
            'def_ftr'  => $dF, 'def_shd' => $dS, 'def_lost' => $dF0 - $dF,
            'att_ftr0' => $aF0, 'def_ftr0' => $dF0,
            'trace'    => $trace,
        ];
    }

    private static function jitter(float $v): float
    {
        return max(0.05, 1.0 + ((mt_rand() / mt_getrandmax()) * 2 - 1) * $v);
    }

    /** @return array{0:int,1:float} scudi rimasti, colpi non assorbiti */
    private static function soak(int $shields, float $hits): array
    {
        if ($shields <= 0) {
            return [0, $hits];
        }
        $absorbed = min((float) $shields, $hits);
        return [(int) round($shields - $absorbed), $hits - $absorbed];
    }

    private static function err(string $m): array
    {
        return ['ok' => false, 'error' => $m];
    }

    // --- attacco nave contro nave -------------------------------------

    /**
     * @param array<string,mixed> $atkPlayer
     * @param array<string,mixed> $atkShip
     */
    public static function attackShip(array $atkPlayer, array $atkShip, int $targetPlayerId, int $commit = 0): array
    {
        $sectorId = (int) $atkPlayer['sector_id'];
        if ($targetPlayerId === (int) $atkPlayer['id']) {
            return self::err('Non puoi attaccare te stesso.');
        }
        $sector = Universe::sector($sectorId);
        if ((bool) $sector['is_fedspace']) {
            return self::err('La Federazione non tollera atti ostili in questo settore.');
        }

        $target = Database::first('SELECT * FROM players WHERE id = ? AND sector_id = ?', [$targetPlayerId, $sectorId]);
        if ($target === null) {
            return self::err('Bersaglio non presente in questo settore.');
        }
        if (Ranks::isProtected($target)) {
            return self::err('Il bersaglio e\' sotto protezione novizio.');
        }

        $turnCost = GameConfig::int('combat.attack_turn_cost', 2);
        $atkPlayer = TurnManager::sync($atkPlayer);
        if ((int) $atkPlayer['turns'] < $turnCost) {
            return self::err("Turni insufficienti per attaccare (servono {$turnCost}).");
        }

        $tShip = PlayerService::ship((int) $target['ship_id']);
        $aM = (float) ($atkShip['combat_rating'] ?? 1.0);
        $dM = (float) ($tShip['combat_rating'] ?? 1.0);
        if ($ab = Crew::consumePending((int) $atkPlayer['id'], 'attack_bonus_pct')) {
            $aM *= 1 + $ab / 100;
        }

        $commit = $commit > 0 ? min($commit, (int) $atkShip['fighters']) : (int) $atkShip['fighters'];
        if ($commit <= 0) {
            return self::err('Non hai caccia da lanciare.');
        }

        $r = self::duel($commit, (int) $atkShip['shields'], $aM, (int) $tShip['fighters'], (int) $tShip['shields'], $dM);

        $atkFtrLeft = (int) $atkShip['fighters'] - $r['att_lost'];
        $destroyedTarget = $r['def_ftr'] <= 0 && $r['def_shd'] <= 0 && ($commit - $r['att_lost']) > 0;
        $destroyedAtk = $atkFtrLeft <= 0 && $r['att_shd'] <= 0 && $r['def_ftr'] > 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $loot = 0;
        $expGain = 0;
        $drops = ['items' => [], 'salvage' => 0];
        try {
            Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$turnCost, $atkPlayer['id']]);
            Database::run('UPDATE players SET protected_until = NULL WHERE id = ? AND protected_until IS NOT NULL', [$atkPlayer['id']]);
            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [max(0, $atkFtrLeft), $r['att_shd'], $atkShip['id']]);
            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [$r['def_ftr'], $r['def_shd'], $tShip['id']]);

            if ($destroyedTarget) {
                $loot = (int) floor((int) $target['credits'] * GameConfig::float('combat.loot_pct', 0.5));
                $expGain = GameConfig::int('combat.exp_per_kill', 50)
                    + (int) round($r['def_lost'] * GameConfig::float('combat.exp_per_fighter', 0.02));
                $align = (int) $target['alignment'] >= 0
                    ? GameConfig::int('combat.kill_good_alignment', -25)
                    : GameConfig::int('combat.kill_evil_alignment', 15);
                $bounty = (int) floor($loot * GameConfig::float('combat.bounty_pct', 0.1) * GameConfig::float('combat.bounty_mult', 1.0)) * ((int) $target['alignment'] >= 0 ? 1 : 0);

                Database::run(
                    'UPDATE players SET credits = credits + ?, kills = kills + 1, experience = experience + ?, alignment = alignment + ?, bounty = bounty + ? WHERE id = ?',
                    [$loot, $expGain, $align, $bounty, $atkPlayer['id']]
                );
                Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$loot, $target['id']]);
                $drops = Loot::rollKill((int) $atkPlayer['id'], 'pvp', $sectorId,
                    (float) ($tShip['combat_rating'] ?? 1.0), null, $target);
                Crew::awardKillXp((int) $atkPlayer['id']);
                Faction::onKillPlayer((int) $atkPlayer['id'], (int) $target['alignment']);
                self::destroyShip($target);
                Contracts::onPlayerKilled((int) $target['id'], (int) $atkPlayer['id']);
                Live::alert((int) $target['id'], 'destroyed', 'Sei stato distrutto', "{$atkPlayer['handle']} ti ha distrutto nel settore {$sectorId}.", '/gioco');
                ShipLog::write((int) $target['id'], 'destroyed', 'alert',
                    "Nave perduta: attacco di {$atkPlayer['handle']}",
                    "Ingaggiati e distrutti da {$atkPlayer['handle']} nel settore {$sectorId} dopo {$r['rounds']} scambi. "
                    . 'Persi ' . number_format($r['def_lost'], 0, ',', '.') . ' caccia e ' . number_format($loot, 0, ',', '.') . ' cr di carico/fondi. '
                    . 'Equipaggio recuperato in capsula allo StarDock.',
                    $sectorId);
            } else {
                Live::player((int) $target['id'], 'attacked', 'Sotto attacco', "{$atkPlayer['handle']} ti ha attaccato nel settore {$sectorId}.");
                ShipLog::write((int) $target['id'], 'combat', 'warning',
                    "Sotto attacco da {$atkPlayer['handle']}",
                    sprintf('%s ti ha attaccato nel settore %d: %d scambi, persi %d caccia, scudi al %d%%. Nave integra.',
                        $atkPlayer['handle'], $sectorId, $r['rounds'], $r['def_lost'],
                        (int) $tShip['max_shields'] > 0 ? (int) round(100 * $r['def_shd'] / (int) $tShip['max_shields']) : 0),
                    $sectorId);
            }
            if ($destroyedAtk) {
                self::destroyShip($atkPlayer);
            }
            Live::sector($sectorId, 'combat', null, "Scontro fra {$atkPlayer['handle']} e {$target['handle']}");

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, defender_player_id, rounds, att_fighters_lost, def_fighters_lost, outcome, loot_credits, detail)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'ship', $sectorId, $atkPlayer['id'], $target['id'], $r['rounds'],
                    $r['att_lost'], $r['def_lost'],
                    $destroyedTarget ? 'def_destroyed' : ($destroyedAtk ? 'att_destroyed' : ($r['def_lost'] > $r['att_lost'] ? 'att_win' : 'draw')),
                    $loot, json_encode(['duel' => $r, 'drops' => $drops], JSON_UNESCAPED_UNICODE),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok'               => true,
            'kind'             => 'ship',
            'rounds'           => $r['rounds'],
            'attacker_lost'    => $r['att_lost'],
            'defender_lost'    => $r['def_lost'],
            'destroyed_target' => $destroyedTarget,
            'destroyed_self'   => $destroyedAtk,
            'loot'             => $loot,
            'exp'              => $expGain,
            'target_handle'    => $target['handle'],
            'player'           => Database::first('SELECT * FROM players WHERE id = ?', [$atkPlayer['id']]),
            'ship'             => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$atkPlayer['id']])['ship_id']),
        ];
    }

    // --- assalto al porto -------------------------------------------

    /**
     * @param array<string,mixed> $atkPlayer
     * @param array<string,mixed> $atkShip
     */
    public static function attackPort(array $atkPlayer, array $atkShip, int $commit = 0): array
    {
        $sectorId = (int) $atkPlayer['sector_id'];
        $sector = Universe::sector($sectorId);
        if ((bool) $sector['is_fedspace']) {
            return self::err('Non in spazio Federazione.');
        }
        $port = Economy::portAt($sectorId);
        if ($port === null) {
            return self::err('Nessun porto in questo settore.');
        }
        if ((int) $port['class'] === 0) {
            return self::err('Lo StarDock e\' inespugnabile.');
        }

        $turnCost = GameConfig::int('combat.attack_turn_cost', 2);
        $atkPlayer = TurnManager::sync($atkPlayer);
        if ((int) $atkPlayer['turns'] < $turnCost) {
            return self::err("Turni insufficienti (servono {$turnCost}).");
        }

        $commit = $commit > 0 ? min($commit, (int) $atkShip['fighters']) : (int) $atkShip['fighters'];
        if ($commit <= 0) {
            return self::err('Non hai caccia da lanciare.');
        }

        $aM = (float) ($atkShip['combat_rating'] ?? 1.0);
        if ($ab = Crew::consumePending((int) $atkPlayer['id'], 'attack_bonus_pct')) {
            $aM *= 1 + $ab / 100;
        }
        $pM = 1.0 + (int) $port['tech_level'] * 0.15;

        $r = self::duel($commit, (int) $atkShip['shields'], $aM, (int) $port['fighters'], 0, $pM);
        $atkFtrLeft = (int) $atkShip['fighters'] - $r['att_lost'];
        $bust = $r['def_ftr'] <= 0 && ($commit - $r['att_lost']) > 0;
        $destroyedAtk = $atkFtrLeft <= 0 && $r['att_shd'] <= 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $loot = 0;
        $stolen = [];
        $drops = ['items' => [], 'salvage' => 0];
        try {
            Database::run('UPDATE players SET turns = turns - ?, protected_until = NULL WHERE id = ?', [$turnCost, $atkPlayer['id']]);
            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [max(0, $atkFtrLeft), $r['att_shd'], $atkShip['id']]);
            Database::run('UPDATE ports SET fighters = ? WHERE id = ?', [$r['def_ftr'], $port['id']]);

            if ($bust) {
                $loot = (int) floor((int) $port['credits'] * GameConfig::float('combat.loot_pct', 0.5));
                $ship = Database::first('SELECT * FROM ships WHERE id = ?', [$atkShip['id']]);
                $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
                foreach (Economy::COMMODITIES as $c) {
                    $pf = Economy::prefix($c);
                    if ($room <= 0) {
                        break;
                    }
                    if ($port["{$pf}_mode"] === 'sell' && (int) $port["{$pf}_stock"] > 0) {
                        $take = min($room, (int) round((int) $port["{$pf}_stock"] * 0.5));
                        if ($take <= 0) {
                            continue;
                        }
                        Database::run("UPDATE ships SET " . Economy::shipColumn($c) . " = " . Economy::shipColumn($c) . " + ? WHERE id = ?", [$take, $ship['id']]);
                        Database::run("UPDATE ports SET {$pf}_stock = {$pf}_stock - ? WHERE id = ?", [$take, $port['id']]);
                        $stolen[$c] = $take;
                        $room -= $take;
                    }
                }
                $align = GameConfig::int('combat.port_bust_alignment', -120);
                if ($as = (float) ($atkShip['crew_align_shield_pct'] ?? 0)) {
                    $align = (int) round($align * (1 - min(60, $as) / 100));
                }
                $exp = GameConfig::int('combat.exp_per_kill', 50) + (int) round($r['def_lost'] * GameConfig::float('combat.exp_per_fighter', 0.02));
                Database::run(
                    'UPDATE players SET credits = credits + ?, port_busts = port_busts + 1, alignment = alignment + ?, experience = experience + ? WHERE id = ?',
                    [$loot, $align, $exp, $atkPlayer['id']]
                );
                Database::run('UPDATE ports SET credits = credits - ? WHERE id = ?', [$loot, $port['id']]);
                $drops = Loot::rollKill((int) $atkPlayer['id'], 'port', $sectorId,
                    1.0 + (float) ($port['tech_level'] ?? 1) * 0.4);
                Crew::awardKillXp((int) $atkPlayer['id']);
                Faction::onPortBust((int) $atkPlayer['id'], (int) ($sector['region_id'] ?? 0));
            }
            if ($destroyedAtk) {
                self::destroyShip($atkPlayer);
            }

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, defender_port_id, rounds, att_fighters_lost, def_fighters_lost, outcome, loot_credits, detail)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'port', $sectorId, $atkPlayer['id'], $port['id'], $r['rounds'],
                    $r['att_lost'], $r['def_lost'],
                    $bust ? 'def_destroyed' : ($destroyedAtk ? 'att_destroyed' : 'repelled'),
                    $loot, json_encode(['duel' => $r, 'stolen' => $stolen, 'drops' => $drops], JSON_UNESCAPED_UNICODE),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok'             => true,
            'kind'           => 'port',
            'rounds'         => $r['rounds'],
            'attacker_lost'  => $r['att_lost'],
            'defender_lost'  => $r['def_lost'],
            'bust'           => $bust,
            'destroyed_self' => $destroyedAtk,
            'loot'           => $loot,
            'stolen'         => $stolen,
            'drops'          => $drops,
            'player'         => Database::first('SELECT * FROM players WHERE id = ?', [$atkPlayer['id']]),
            'ship'           => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$atkPlayer['id']])['ship_id']),
        ];
    }

    // --- assalto planetario ----------------------------------------

    /**
     * @param array<string,mixed> $atkPlayer
     * @param array<string,mixed> $atkShip
     */
    public static function attackPlanet(array $atkPlayer, array $atkShip, int $planetId, int $commit = 0, bool $bombard = false): array
    {
        $p = Planets::get($planetId);
        if ($p === null) {
            return self::err('Pianeta inesistente.');
        }
        if ((int) $p['sector_id'] !== (int) $atkPlayer['sector_id']) {
            return self::err('Non sei nel settore del pianeta.');
        }
        if ((bool) Universe::sector((int) $p['sector_id'])['is_fedspace']) {
            return self::err('La Federazione protegge questo settore.');
        }
        if (Planets::isOwn($p, $atkPlayer)) {
            return self::err('Il pianeta e\' tuo o della tua corporazione.');
        }

        $turnCost = GameConfig::int('combat.attack_turn_cost', 2);
        $atkPlayer = TurnManager::sync($atkPlayer);
        if ((int) $atkPlayer['turns'] < $turnCost) {
            return self::err("Turni insufficienti (servono {$turnCost}).");
        }

        $commit = $commit > 0 ? min($commit, (int) $atkShip['fighters']) : (int) $atkShip['fighters'];
        if ($commit <= 0) {
            return self::err('Non hai caccia da lanciare.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET turns = turns - ?, protected_until = NULL WHERE id = ?', [$turnCost, $atkPlayer['id']]);

            $atkFtr = $commit;
            $atkShd = (int) $atkShip['shields'];
            $events = [];

            // volata Quasar
            $qdmg = (int) $p['quasar_level'] * GameConfig::int('planet.quasar_damage', 2200);
            if ($qdmg > 0) {
                [$atkShd, $rem] = self::soak($atkShd, $qdmg);
                $atkFtr = max(0, $atkFtr - (int) floor($rem));
                $events[] = "Quasar: -{$qdmg}";
            }

            $destroyedAtk = false;
            $loot = 0;
            $stolen = [];
            $drops = ['items' => [], 'salvage' => 0];
            $bombKilled = 0;
            $cracked = false;
            $rounds = 0;
            $duelTrace = null;

            if ($atkFtr <= 0 && $atkShd <= 0) {
                $destroyedAtk = true;
            } else {
                $totalCol = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
                $militia = (int) floor($totalCol * GameConfig::float('planet.militia_col_frac', 0.01));
                $defFtr = (int) $p['fighters'] + $militia;
                $defShd = (int) $p['shields'];
                $defM = 1.0 + (int) $p['citadel_level'] * 0.15;

                $aM = (float) ($atkShip['combat_rating'] ?? 1.0);
                if ($ab = Crew::consumePending((int) $atkPlayer['id'], 'attack_bonus_pct')) {
                    $aM *= 1 + $ab / 100;
                }
                $r = self::duel($atkFtr, $atkShd, $aM, $defFtr, $defShd, $defM);
                $rounds = $r['rounds'];
                $atkFtr = $r['att_ftr'];
                $atkShd = $r['att_shd'];
                $duelTrace = $r;

                // caccia difensivi persi: prima la milizia, poi la guarnigione
                $defLost = $r['def_lost'];
                $garLost = max(0, $defLost - $militia);
                Database::run('UPDATE planets SET fighters = GREATEST(0, fighters - ?), shields = ? WHERE id = ?', [$garLost, $r['def_shd'], $planetId]);

                $cracked = $r['def_ftr'] <= 0 && $atkFtr > 0;
                $destroyedAtk = $atkFtr <= 0 && $atkShd <= 0 && $r['def_ftr'] > 0;

                if ($cracked) {
                    $loot = (int) floor((int) $p['credits'] * GameConfig::float('combat.loot_pct', 0.5));
                    $ship = Database::first('SELECT * FROM ships WHERE id = ?', [$atkShip['id']]);
                    $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
                    foreach ([['stock_ore', 'ore', 'hold_ore'], ['stock_org', 'organics', 'hold_organics'], ['stock_equ', 'equipment', 'hold_equipment']] as [$sc, $cl, $hc]) {
                        if ($room <= 0) {
                            break;
                        }
                        $take = min($room, (int) round((int) $p[$sc] * 0.5));
                        if ($take <= 0) {
                            continue;
                        }
                        Database::run("UPDATE ships SET {$hc} = {$hc} + ? WHERE id = ?", [$take, $ship['id']]);
                        Database::run("UPDATE planets SET {$sc} = {$sc} - ? WHERE id = ?", [$take, $planetId]);
                        $stolen[$cl] = $take;
                        $room -= $take;
                    }
                    Database::run('UPDATE planets SET credits = GREATEST(0, credits - ?) WHERE id = ?', [$loot, $planetId]);

                    if ($bombard && $totalCol > 0) {
                        $frac = GameConfig::float('planet.bombard_frac', 0.2);
                        foreach (['col_ore', 'col_org', 'col_equ', 'col_idle'] as $cc) {
                            $k = (int) floor((int) $p[$cc] * $frac);
                            $bombKilled += $k;
                            Database::run("UPDATE planets SET {$cc} = GREATEST(0, {$cc} - ?) WHERE id = ?", [$k, $planetId]);
                        }
                    }

                    $align = $bombard
                        ? GameConfig::int('planet.bombard_alignment', -150)
                        : GameConfig::int('planet.bust_alignment', -60);
                    if ($as = (float) ($atkShip['crew_align_shield_pct'] ?? 0)) {
                        $align = (int) round($align * (1 - min(60, $as) / 100));
                    }
                    $exp = GameConfig::int('combat.exp_per_kill', 50) + (int) round($defLost * GameConfig::float('combat.exp_per_fighter', 0.02));
                    Database::run(
                        'UPDATE players SET credits = credits + ?, alignment = alignment + ?, experience = experience + ? WHERE id = ?',
                        [$loot, $align, $exp, $atkPlayer['id']]
                    );

                    $drops = Loot::rollKill((int) $atkPlayer['id'], 'planet', (int) $p['sector_id'],
                        1.0 + (float) ($p['citadel_level'] ?? 0) * 0.5);
                    Crew::awardKillXp((int) $atkPlayer['id']);
                    if ($bombard) {
                        Faction::onPlanetBomb((int) $atkPlayer['id']);
                    } else {
                        Faction::adjust((int) $atkPlayer['id'], 'frontier',
                            -(int) round(GameConfig::int('faction.bomb_loss', 25) / 3), 'assalto planetario', false);
                    }

                    $note = ($bombard ? 'BOMBARDATO' : 'espugnato') . " da {$atkPlayer['handle']}";
                    if ((int) ($p['owner_player_id'] ?? 0) > 0) {
                        Live::alert((int) $p['owner_player_id'], 'planet_hit', "Pianeta {$note}", "{$p['name']} (settore {$p['sector_id']}) e' stato {$note}.", '/gioco/pianeta/' . $planetId);
                        ShipLog::write((int) $p['owner_player_id'], 'planet', $bombard ? 'alert' : 'warning',
                            "Colonia sotto attacco: {$p['name']}",
                            "Rapporto dalla colonia di {$p['name']} (settore {$p['sector_id']}): il pianeta è stato {$note}."
                            . ($bombard ? ' Perdite gravi tra i coloni e le infrastrutture.' : ' La guarnigione è stata sopraffatta.'),
                            (int) $p['sector_id'], ['planet_id' => $planetId]);
                    }
                    Live::corp((int) ($p['corp_id'] ?? 0) ?: null, 'planet_hit', "Pianeta {$note}", "{$p['name']} e' stato {$note}.");
                }
            }

            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [max(0, $atkFtr), max(0, $atkShd), $atkShip['id']]);
            if ($destroyedAtk) {
                self::destroyShip($atkPlayer);
            }

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, defender_player_id, rounds, att_fighters_lost, def_fighters_lost, outcome, loot_credits, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'planet', (int) $p['sector_id'], $atkPlayer['id'], (int) ($p['owner_player_id'] ?? 0) ?: null, $rounds,
                    $commit - max(0, $atkFtr), 0,
                    $cracked ? 'def_destroyed' : ($destroyedAtk ? 'att_destroyed' : 'repelled'),
                    $loot, json_encode(['stolen' => $stolen, 'bomb_killed' => $bombKilled, 'events' => $events, 'duel' => $duelTrace, 'drops' => $drops], JSON_UNESCAPED_UNICODE),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok'             => true,
            'kind'           => 'planet',
            'rounds'         => $rounds,
            'cracked'        => $cracked,
            'bombarded'      => $bombard && $cracked,
            'bomb_killed'    => $bombKilled,
            'destroyed_self' => $destroyedAtk,
            'loot'           => $loot,
            'stolen'         => $stolen,
            'drops'          => $drops,
            'planet_name'    => $p['name'],
            'player'         => Database::first('SELECT * FROM players WHERE id = ?', [$atkPlayer['id']]),
            'ship'           => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$atkPlayer['id']])['ship_id']),
        ];
    }

    // --- NPC ------------------------------------------------------

    /**
     * Il giocatore attacca un NPC.
     *
     * @param array<string,mixed> $atkPlayer
     * @param array<string,mixed> $atkShip
     */
    public static function attackNpc(array $atkPlayer, array $atkShip, int $npcId, int $commit = 0): array
    {
        $npc = Database::first('SELECT * FROM npcs WHERE id = ?', [$npcId]);
        if ($npc === null) {
            return self::err('Nessun bersaglio con quel codice.');
        }
        if ((int) $npc['sector_id'] !== (int) $atkPlayer['sector_id']) {
            return self::err('Il bersaglio non e\' in questo settore.');
        }
        $turnCost = GameConfig::int('combat.attack_turn_cost', 2);
        $atkPlayer = TurnManager::sync($atkPlayer);
        if ((int) $atkPlayer['turns'] < $turnCost) {
            return self::err("Turni insufficienti (servono {$turnCost}).");
        }
        $commit = $commit > 0 ? min($commit, (int) $atkShip['fighters']) : (int) $atkShip['fighters'];
        if ($commit <= 0) {
            return self::err('Non hai caccia da lanciare.');
        }

        $aM = (float) ($atkShip['combat_rating'] ?? 1.0);
        if ($ab = Crew::consumePending((int) $atkPlayer['id'], 'attack_bonus_pct')) {
            $aM *= 1 + $ab / 100;
        }
        $r = self::duel($commit, (int) $atkShip['shields'], $aM,
            (int) $npc['fighters'], (int) $npc['shields'], (float) $npc['combat_rating']);

        $atkFtrLeft = (int) $atkShip['fighters'] - $r['att_lost'];
        $killed = $r['def_ftr'] <= 0 && $atkFtrLeft > 0;
        $destroyedAtk = $atkFtrLeft <= 0 && $r['att_shd'] <= 0 && $r['def_ftr'] > 0;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $loot = 0;
        $exp = 0;
        $drops = ['items' => [], 'salvage' => 0];
        try {
            Database::run('UPDATE players SET turns = turns - ? WHERE id = ?', [$turnCost, $atkPlayer['id']]);
            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [max(0, $atkFtrLeft), $r['att_shd'], $atkShip['id']]);

            if ($killed) {
                $ship = Database::first('SELECT * FROM ships WHERE id = ?', [$atkShip['id']]);
                $room = (int) $ship['holds_total'] - Economy::holdsUsed($ship);
                foreach ([['cargo_ore', 'hold_ore'], ['cargo_org', 'hold_organics'], ['cargo_equ', 'hold_equipment']] as [$nc, $hc]) {
                    $take = min($room, (int) $npc[$nc]);
                    if ($take > 0) {
                        Database::run("UPDATE ships SET {$hc} = {$hc} + ? WHERE id = ?", [$take, $ship['id']]);
                        $room -= $take;
                    }
                }
                $loot = (int) $npc['credits'];
                $exp = $npc['kind'] === 'ferrengi'
                    ? GameConfig::int('npc.kill_exp_ferrengi', 140)
                    : ($npc['kind'] === 'pirate' ? GameConfig::int('npc.kill_exp_pirate', 70) : 20);
                $align = match ($npc['kind']) {
                    'ferrengi' => 20, 'pirate' => 10, default => GameConfig::int('combat.kill_good_alignment', -25),
                };
                Database::run(
                    'UPDATE players SET credits = credits + ?, experience = experience + ?, alignment = alignment + ?, kills = kills + IF(? IN (\'ferrengi\',\'pirate\'), 1, 0) WHERE id = ?',
                    [$loot, $exp, $align, $npc['kind'], $atkPlayer['id']]
                );
                $drops = Loot::rollKill((int) $atkPlayer['id'], 'npc', (int) $npc['sector_id'],
                    (float) $npc['combat_rating'], (string) $npc['kind']);
                Crew::awardKillXp((int) $atkPlayer['id']);
                Faction::onKillNpc((int) $atkPlayer['id'], (string) $npc['kind']);
                Database::run('DELETE FROM npcs WHERE id = ?', [$npcId]);
            } else {
                Database::run('UPDATE npcs SET fighters = ?, shields = ? WHERE id = ?', [$r['def_ftr'], $r['def_shd'], $npcId]);
            }

            if ($destroyedAtk) {
                self::destroyShip($atkPlayer);
            }

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, rounds, att_fighters_lost, def_fighters_lost, outcome, loot_credits, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                ['npc', (int) $npc['sector_id'], $atkPlayer['id'], $r['rounds'], $r['att_lost'], $r['def_lost'],
                    $killed ? 'def_destroyed' : ($destroyedAtk ? 'att_destroyed' : 'repelled'), $loot,
                    json_encode(['npc' => $npc['name'], 'kind' => $npc['kind'], 'duel' => $r, 'drops' => $drops], JSON_UNESCAPED_UNICODE)]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok'             => true,
            'kind'           => 'npc',
            'npc_name'       => $npc['name'],
            'npc_kind'       => $npc['kind'],
            'rounds'         => $r['rounds'],
            'attacker_lost'  => $r['att_lost'],
            'defender_lost'  => $r['def_lost'],
            'killed'         => $killed,
            'destroyed_self' => $destroyedAtk,
            'loot'           => $loot,
            'exp'            => $exp,
            'drops'          => $drops,
            'player'         => Database::first('SELECT * FROM players WHERE id = ?', [$atkPlayer['id']]),
            'ship'           => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$atkPlayer['id']])['ship_id']),
        ];
    }

    /**
     * Un NPC ostile ingaggia il giocatore (dal tick o all'ingresso nel settore).
     *
     * @param array<string,mixed> $npc
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{event:string, destroyed:bool}
     */
    public static function npcEngagePlayer(array $npc, array $player, array $ship, bool $fromTick = false): array
    {
        $r = self::duel(
            (int) $npc['fighters'], (int) $npc['shields'], (float) $npc['combat_rating'],
            (int) $ship['fighters'], (int) $ship['shields'], (float) ($ship['combat_rating'] ?? 1.0)
        );

        Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [$r['def_ftr'], $r['def_shd'], $ship['id']]);

        $npcDead = $r['att_ftr'] <= 0 && $r['att_shd'] <= 0;
        $playerDead = $r['def_ftr'] <= 0 && $r['def_shd'] <= 0 && $r['att_ftr'] > 0;

        if ($npcDead) {
            Database::run('DELETE FROM npcs WHERE id = ?', [$npc['id']]);
        } else {
            Database::run('UPDATE npcs SET fighters = ?, shields = ? WHERE id = ?', [$r['att_ftr'], $r['att_shd'], $npc['id']]);
        }

        Database::run(
            'INSERT INTO combat_log (kind, sector_id, defender_player_id, rounds, att_fighters_lost, def_fighters_lost, outcome, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            ['npc', (int) $npc['sector_id'], (int) $player['id'], $r['rounds'], $r['att_lost'], $r['def_lost'],
                $playerDead ? 'att_destroyed' : ($npcDead ? 'def_win' : 'draw'),
                json_encode(['npc' => $npc['name'], 'aggressor' => true], JSON_UNESCAPED_UNICODE)]
        );

        if ($playerDead) {
            $d = self::destroyShip($player);
            Live::alert((int) $player['id'], 'destroyed', 'Nave distrutta da un NPC', "{$npc['name']} ti ha distrutto nel settore {$npc['sector_id']}.", '/gioco');
            if ($fromTick) {
                ShipLog::write((int) $player['id'], 'destroyed', 'alert',
                    "Nave perduta: {$npc['name']}",
                    "Agganciati e ingaggiati da {$npc['name']} nel settore {$npc['sector_id']} mentre eri in stazionamento. "
                    . "Difese collassate dopo {$r['rounds']} scambi. " . self::deathLine($d),
                    (int) $npc['sector_id']);
            }
            return ['event' => "{$npc['name']} ti ha distrutto. " . self::deathLine($d), 'destroyed' => true];
        }
        Live::player((int) $player['id'], 'npc_attack', 'Attacco NPC', "{$npc['name']} ti ha attaccato nel settore {$npc['sector_id']}.");
        if ($fromTick) {
            ShipLog::write((int) $player['id'], 'npc', 'warning',
                "Attacco NPC nel settore {$npc['sector_id']}",
                sprintf('%s ti ha ingaggiato in stazionamento nel settore %d: %d scambi, persi %d caccia, inflitti %d di perdite.',
                    $npc['name'], (int) $npc['sector_id'], $r['rounds'], $r['def_lost'], $r['att_lost'])
                . ($npcDead ? ' Ostile respinto e distrutto.' : ' Ostile ancora presente.'),
                (int) $npc['sector_id']);
        }
        if ($npcDead) {
            return ['event' => "{$npc['name']} ti ha attaccato ma e\' stato respinto e distrutto.", 'destroyed' => false];
        }
        return ['event' => sprintf('%s ti ha attaccato: persi %d caccia, inflitti -%d.', $npc['name'], $r['def_lost'], $r['att_lost']), 'destroyed' => false];
    }

    // --- intercettazioni all'ingresso ---------------------------------

    /**
     * Chiamata da Navigation::move dopo l'arrivo nel nuovo settore.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{events:list<string>, player:array<string,mixed>, ship:array<string,mixed>, destroyed:bool}
     */
    public static function onEnterSector(array $player, array $ship): array
    {
        $sectorId = (int) $player['sector_id'];
        $events = [];

        if ((bool) Universe::sector($sectorId)['is_fedspace']) {
            return ['events' => [], 'player' => $player, 'ship' => $ship, 'destroyed' => false];
        }

        $pid = (int) $player['id'];
        $mine = static fn (int $ownerId): bool => $ownerId === $pid || Corp::areMates($pid, $ownerId);
        $noEngage = Crew::consumePending($pid, 'no_engage') !== null;

        // hazard ambientali (Fase 9): radiazioni / tempeste ioniche all'ingresso
        $hz = SectorFeatures::entryHazards($player, $ship);
        $ship = $hz['ship'];
        foreach ($hz['events'] as $ev) {
            $events[] = $ev;
        }

        // 0) Quasar planetari ostili
        foreach (Database::all(
            'SELECT id, name, owner_player_id, corp_id, quasar_level, citadel_level FROM planets
             WHERE sector_id = ? AND destroyed = 0 AND citadel_level >= 3 AND quasar_level > 0',
            [$sectorId]
        ) as $pl) {
            $ownerId = (int) ($pl['owner_player_id'] ?? 0);
            $corpId = (int) ($pl['corp_id'] ?? 0);
            $friendly = ($ownerId > 0 && $mine($ownerId))
                || ($corpId > 0 && $corpId === (Corp::corpIdOf($pid) ?? -1));
            if ($friendly) {
                continue;
            }
            $dmg = (int) $pl['quasar_level'] * GameConfig::int('planet.quasar_damage', 2200);
            [$ship, $dead] = self::applyDamage($ship, $dmg);
            Database::run(
                'INSERT INTO combat_log (kind, sector_id, defender_player_id, outcome, detail) VALUES (?, ?, ?, ?, ?)',
                ['quasar', $sectorId, $pid, $dead ? 'att_destroyed' : 'passed', json_encode(['planet' => $pl['name'], 'dmg' => $dmg], JSON_UNESCAPED_UNICODE)]
            );
            $events[] = "Cannone Quasar di {$pl['name']}: {$dmg} danni alla nave.";
            if ($dead) {
                $d = self::destroyShip($player);
                return ['events' => array_merge($events, [self::deathLine($d)]), 'player' => Database::first('SELECT * FROM players WHERE id = ?', [$pid]), 'ship' => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$pid])['ship_id']), 'destroyed' => true];
            }
        }

        // 1) mine Armid
        foreach (Database::all("SELECT * FROM sector_mines WHERE sector_id = ? AND type = 'armid'", [$sectorId]) as $m) {
            if ($mine((int) $m['owner_player_id'])) {
                continue;
            }
            $dmg = (int) ceil((int) $m['qty'] * GameConfig::float('combat.armid_damage', 1.0));
            [$ship, $dead] = self::applyDamage($ship, $dmg);
            Database::run('DELETE FROM sector_mines WHERE id = ?', [$m['id']]);
            Database::run(
                'INSERT INTO combat_log (kind, sector_id, defender_player_id, outcome, detail) VALUES (?, ?, ?, ?, ?)',
                ['mines', $sectorId, $pid, $dead ? 'att_destroyed' : 'passed', json_encode(['armid' => (int) $m['qty'], 'dmg' => $dmg], JSON_UNESCAPED_UNICODE)]
            );
            $events[] = "Campo minato Armid ({$m['qty']} mine): {$dmg} danni alla nave.";
            if ($dead) {
                $d = self::destroyShip($player);
                return ['events' => array_merge($events, [self::deathLine($d)]), 'player' => Database::first('SELECT * FROM players WHERE id = ?', [$pid]), 'ship' => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$pid])['ship_id']), 'destroyed' => true];
            }
        }

        // 2) caccia: pedaggio, offensivi, difensivi (se il visitatore e' malvagio)
        $groups = $noEngage ? [] : Database::all(
            'SELECT sf.*, p.handle FROM sector_fighters sf JOIN players p ON p.id = sf.owner_player_id WHERE sf.sector_id = ?',
            [$sectorId]
        );
        if ($noEngage) {
            $events[] = 'Negoziato: le forze schierate qui ti lasciano passare.';
        }
        foreach ($groups as $g) {
            if ($mine((int) $g['owner_player_id']) || (int) $g['qty'] <= 0) {
                continue;
            }
            $hostile = false;

            if ($g['mode'] === 'toll') {
                $toll = (int) $g['toll'];
                if ((int) $player['credits'] >= $toll) {
                    Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$toll, $pid]);
                    Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [$toll, $g['owner_player_id']]);
                    $player['credits'] = (int) $player['credits'] - $toll;
                    $events[] = "Pedaggio di {$toll} cr versato a {$g['handle']}.";
                } else {
                    $events[] = "Pedaggio non pagato: i caccia di {$g['handle']} aprono il fuoco.";
                    $hostile = true;
                }
            } elseif ($g['mode'] === 'offensive') {
                $events[] = "Caccia offensivi di {$g['handle']}: ingaggio.";
                $hostile = true;
            } elseif ($g['mode'] === 'defensive' && Ranks::isEvil((int) $player['alignment'])) {
                $events[] = "I caccia difensivi di {$g['handle']} attaccano il pirata.";
                $hostile = true;
            }

            if (!$hostile) {
                continue;
            }

            $aM = 1.0;
            $dM = (float) ($ship['combat_rating'] ?? 1.0);
            $r = self::duel((int) $g['qty'], 0, $aM, (int) $ship['fighters'], (int) $ship['shields'], $dM);

            Database::run('UPDATE ships SET fighters = ?, shields = ? WHERE id = ?', [$r['def_ftr'], $r['def_shd'], $ship['id']]);
            $ship['fighters'] = $r['def_ftr'];
            $ship['shields'] = $r['def_shd'];

            $left = (int) $g['qty'] - $r['att_lost'];
            if ($left > 0) {
                Database::run('UPDATE sector_fighters SET qty = ? WHERE id = ?', [$left, $g['id']]);
            } else {
                Database::run('DELETE FROM sector_fighters WHERE id = ?', [$g['id']]);
            }

            Database::run(
                'INSERT INTO combat_log (kind, sector_id, attacker_player_id, defender_player_id, rounds, att_fighters_lost, def_fighters_lost, outcome, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                ['fighters', $sectorId, (int) $g['owner_player_id'], $pid, $r['rounds'], $r['att_lost'], $r['def_lost'],
                    ($r['def_ftr'] <= 0 && $r['def_shd'] <= 0) ? 'att_destroyed' : 'passed', json_encode($r, JSON_UNESCAPED_UNICODE)]
            );
            $events[] = sprintf('  Scontro: persi %d tuoi caccia, distrutti %d nemici.', $r['def_lost'], $r['att_lost']);

            if ($r['def_ftr'] <= 0 && $r['def_shd'] <= 0 && $r['att_ftr'] > 0) {
                $d = self::destroyShip($player);
                return ['events' => array_merge($events, [self::deathLine($d)]), 'player' => Database::first('SELECT * FROM players WHERE id = ?', [$pid]), 'ship' => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$pid])['ship_id']), 'destroyed' => true];
            }
        }

        // 3) NPC ostili nel settore
        $ferrOk = Faction::tierAtLeast($pid, 'ferrengi', 'friendly');
        $pirOk  = Faction::tierAtLeast($pid, 'frontier', 'allied');
        foreach (($noEngage ? [] : Database::all('SELECT * FROM npcs WHERE sector_id = ? AND aggression > 0', [$sectorId])) as $npc) {
            if ($npc['kind'] === 'ferrengi' && (Ranks::isEvil((int) ($player['alignment'] ?? 0)) || $ferrOk)) {
                continue;
            }
            if ($npc['kind'] === 'pirate' && $pirOk && $npc['name'] !== 'Cacciatore di taglie') {
                continue;
            }
            $freshShip = PlayerService::ship((int) $player['ship_id']);
            if ($freshShip === null || $freshShip['type_key'] === 'escape_pod') {
                break;
            }
            $out = self::npcEngagePlayer($npc, $player, $freshShip);
            $events[] = $out['event'];
            if ($out['destroyed']) {
                return ['events' => $events, 'player' => Database::first('SELECT * FROM players WHERE id = ?', [$pid]), 'ship' => PlayerService::ship((int) Database::first('SELECT ship_id FROM players WHERE id = ?', [$pid])['ship_id']), 'destroyed' => true];
            }
        }

        return [
            'events'    => $events,
            'player'    => Database::first('SELECT * FROM players WHERE id = ?', [$pid]) ?? $player,
            'ship'      => PlayerService::ship((int) $player['ship_id']) ?? $ship,
            'destroyed' => false,
        ];
    }

    /**
     * @param array<string,mixed> $ship
     * @return array{0:array<string,mixed>,1:bool} nave aggiornata, distrutta?
     */
    private static function applyDamage(array $ship, int $dmg): array
    {
        $sh = (int) $ship['shields'];
        $ft = (int) $ship['fighters'];
        $s = min($sh, $dmg); $sh -= $s; $dmg -= $s;
        $f = min($ft, $dmg); $ft -= $f; $dmg -= $f;
        $ship['shields'] = $sh;
        $ship['fighters'] = $ft;
        Database::run('UPDATE ships SET shields = ?, fighters = ? WHERE id = ?', [$sh, $ft, $ship['id']]);
        return [$ship, $dmg > 0];
    }

    // --- distruzione + capsula --------------------------------------

    /**
     * @param array<string,mixed> $player
     * @return array{dock:int, lost_credits:int, had_pod:bool}
     */
    public static function destroyShip(array $player): array
    {
        $player = Database::first('SELECT * FROM players WHERE id = ?', [$player['id']]);
        $ship = Database::first('SELECT * FROM ships WHERE id = ?', [$player['ship_id']]);
        $dock = (int) (Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'] ?? 1);
        $hadPod = (int) $ship['escape_pod'] === 1;
        $lost = $hadPod ? 0 : (int) floor((int) $player['credits'] * 0.5);
        $podHolds = GameConfig::int('hardware.pod_holds', 5);

        // moduli installati: persi, ma se ne recupera una parte in Leghe
        try {
            $refPct = GameConfig::float('loot.death_module_refund_pct', 0.5);
            $refund = 0;
            foreach (Database::all(
                'SELECT it.base_salvage FROM ship_modules sm JOIN item_types it ON it.ckey = sm.item_key WHERE sm.ship_id = ?',
                [$ship['id']]
            ) as $mm) {
                $refund += (int) round((int) $mm['base_salvage'] * $refPct);
            }
            if ($refund > 0) {
                Database::run('UPDATE players SET salvage = salvage + ? WHERE id = ?', [$refund, $player['id']]);
            }
            Database::run('DELETE FROM ship_modules WHERE ship_id = ?', [$ship['id']]);
        } catch (\Throwable) {
            // tabelle non ancora migrate
        }

        Database::run(
            "UPDATE ships SET type_key = 'escape_pod', name = ?, sector_id = ?, holds_total = ?,
             hold_ore = 0, hold_organics = 0, hold_equipment = 0, hold_colonists = 0,
             fighters = 0, shields = 0, mines_armid = 0, mines_limpet = 0, probes = 0, genesis = 0,
             escape_pod = 0, dev_scanner = 'none', dev_transwarp = 0, dev_cloak = 0
             WHERE id = ?",
            ['Capsula ' . $player['handle'], $dock, $podHolds, $ship['id']]
        );
        Database::run(
            'UPDATE players SET sector_id = ?, credits = GREATEST(0, credits - ?), deaths = deaths + 1, last_death_at = NOW() WHERE id = ?',
            [$dock, $lost, $player['id']]
        );
        Database::run('INSERT IGNORE INTO player_visited_sectors (player_id, sector_id) VALUES (?, ?)', [$player['id'], $dock]);
        Database::run('DELETE FROM ship_limpets WHERE ship_id = ?', [$ship['id']]);

        return ['dock' => $dock, 'lost_credits' => $lost, 'had_pod' => $hadPod];
    }

    private static function deathLine(array $d): string
    {
        $tail = ' Al Cantiere puoi comprare una nave nuova o, se sei a secco, farti dare una nave di soccorso dalla Federazione.';
        return $d['had_pod']
            ? "NAVE DISTRUTTA. Capsula di salvataggio attivata: sei allo StarDock (settore {$d['dock']})." . $tail
            : "NAVE DISTRUTTA. Nessuna capsula: recuperato allo StarDock, persi {$d['lost_credits']} cr." . $tail;
    }
}
