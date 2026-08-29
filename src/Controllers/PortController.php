<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Economy;
use App\Game\Haggle;
use App\Game\Navigation;
use App\Game\TurnManager;

final class PortController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship = Ctx::$ship;
        $sectorId = (int) $player['sector_id'];
        $port = Economy::portAt($sectorId);

        if ($port === null) {
            Session::flash('error', 'Nessun porto in questo settore.');
            return redirect('/gioco');
        }

        $rows = [];
        foreach (Economy::COMMODITIES as $c) {
            $pf = Economy::prefix($c);
            $mode = $port["{$pf}_mode"];
            $action = $mode === 'sell' ? 'buy' : 'sell';
            $max = Economy::maxQty($port, $player, $ship, $c, $action);
            $q = Economy::quote($port, $c, $action, max(1, $max));
            $rows[] = [
                'commodity' => $c,
                'label'     => Economy::label($c),
                'mode'      => $mode,
                'action'    => $action,
                'stock'     => (int) $port["{$pf}_stock"],
                'capacity'  => (int) $port["{$pf}_capacity"],
                'pct'       => (int) round(100 * (int) $port["{$pf}_stock"] / max(1, (int) $port["{$pf}_capacity"])),
                'unit'      => $q['unit'],
                'fair'      => $q['fair'],
                'max'       => $max,
                'cargo'     => (int) $ship[Economy::shipColumn($c)],
            ];
        }

        return Response::html(view('game/port', [
            'title'  => 'Porto — Settore ' . $sectorId,
            'player' => $player,
            'ship'   => $ship,
            'port'   => Economy::portSummary($port),
            'rows'   => $rows,
            'haggle' => Haggle::active(),
        ]));
    }

    public function quickTrade(Request $request): Response
    {
        $player = Ctx::$player;
        $ship = Ctx::$ship;
        $commodity = $request->str('commodity');
        $action = $request->str('action');
        $qty = $request->int('qty');

        $res = Economy::settle($player, $ship, (int) $player['sector_id'], $commodity, $action, $qty, null, 0);
        if (!$res['ok']) {
            Session::flash('error', $res['error']);
        } else {
            $verb = $action === 'buy' ? 'Comprate' : 'Vendute';
            Session::flash('success', sprintf(
                '%s %d unita\' di %s per %s cr (%.2f cr/u).',
                $verb, $qty, Economy::label($commodity),
                number_format($res['total'], 0, ',', '.'), $res['unit']
            ));
        }
        return redirect('/gioco/porto');
    }
}
