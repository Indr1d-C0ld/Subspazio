<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Combat;
use App\Game\Ctx;
use App\Game\Deploy;
use App\Game\Economy;

final class CombatController
{
    public function attackShip(Request $request): Response
    {
        $res = Combat::attackShip(Ctx::$player, Ctx::$ship, $request->int('target'), $request->int('fighters'));
        Session::flash(...self::shipMessage($res));
        return redirect('/gioco');
    }

    public function attackPort(Request $request): Response
    {
        $res = Combat::attackPort(Ctx::$player, Ctx::$ship, $request->int('fighters'));
        if (empty($res['ok'])) {
            Session::flash('error', $res['error']);
            return redirect('/gioco/porto');
        }
        if ($res['bust']) {
            $loot = number_format($res['loot'], 0, ',', '.');
            $stolen = [];
            foreach ($res['stolen'] as $c => $q) {
                $stolen[] = "{$q} " . Economy::label($c);
            }
            $s = $stolen ? ' Bottino merci: ' . implode(', ', $stolen) . '.' : '';
            Session::flash('success', "PORTO ESPUGNATO in {$res['rounds']} round. Saccheggiati {$loot} cr.{$s} Allineamento in picchiata.");
        } elseif ($res['destroyed_self']) {
            Session::flash('error', "Le difese del porto ti hanno distrutto ({$res['rounds']} round). Capsula allo StarDock.");
        } else {
            Session::flash('error', "Assalto respinto ({$res['rounds']} round): persi {$res['attacker_lost']} caccia, difese del porto -{$res['defender_lost']}.");
        }
        return redirect('/gioco');
    }

    public function attackNpc(Request $request): Response
    {
        $res = Combat::attackNpc(Ctx::$player, Ctx::$ship, $request->int('npc'), $request->int('fighters'));
        if (empty($res['ok'])) {
            Session::flash('error', $res['error']);
        } elseif ($res['killed']) {
            Session::flash('success', "{$res['npc_name']} distrutto ({$res['rounds']} round). Bottino "
                . number_format($res['loot'], 0, ',', '.') . " cr, +{$res['exp']} exp.");
        } elseif ($res['destroyed_self']) {
            Session::flash('error', "{$res['npc_name']} ti ha distrutto. Capsula allo StarDock.");
        } else {
            Session::flash('error', "Scontro con {$res['npc_name']} ({$res['rounds']} round): persi {$res['attacker_lost']} caccia, inflitti -{$res['defender_lost']}.");
        }
        return redirect('/gioco');
    }

    public function deployFighters(Request $request): Response
    {
        $res = Deploy::deployFighters(
            Ctx::$player,
            Ctx::$ship,
            $request->int('qty'),
            $request->str('mode'),
            $request->int('toll'),
        );
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Dispiegati {$res['deployed']} caccia ({$res['mode']})."
            : $res['error']);
        return redirect('/gioco');
    }

    public function pullFighters(Request $request): Response
    {
        $res = Deploy::pullFighters(Ctx::$player, Ctx::$ship);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Recuperati {$res['recovered']} caccia."
            : $res['error']);
        return redirect('/gioco');
    }

    public function deployMines(Request $request): Response
    {
        $res = Deploy::deployMines(Ctx::$player, Ctx::$ship, $request->str('type'), $request->int('qty'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Dispiegate {$res['deployed']} mine {$res['type']}."
            : $res['error']);
        return redirect('/gioco');
    }

    /** @return array{0:string,1:string} */
    private static function shipMessage(array $res): array
    {
        if (empty($res['ok'])) {
            return ['error', $res['error']];
        }
        if ($res['destroyed_target']) {
            $loot = number_format($res['loot'], 0, ',', '.');
            return ['success', "{$res['target_handle']} DISTRUTTO in {$res['rounds']} round! Bottino {$loot} cr, +{$res['exp']} exp."];
        }
        if ($res['destroyed_self']) {
            return ['error', "La tua nave e' stata distrutta da {$res['target_handle']}. Capsula allo StarDock."];
        }
        return ['success', "Scontro con {$res['target_handle']} ({$res['rounds']} round): persi {$res['attacker_lost']} caccia, inflitti -{$res['defender_lost']}."];
    }
}
