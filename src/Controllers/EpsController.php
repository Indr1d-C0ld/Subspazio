<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\PowerGrid;
use App\Game\TurnManager;

final class EpsController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship   = Ctx::$ship;

        return Response::html(view('game/eps', [
            'title'   => 'Griglia di potenza',
            'player'  => $player,
            'ship'    => $ship,
            'grid'    => PowerGrid::describe($ship),
            'nominal'  => PowerGrid::nominal(),
            'max_per'  => PowerGrid::maxPerChannel(),
            'total'    => PowerGrid::pipsTotal(),
            'cost'     => PowerGrid::reallocTurnCost(),
            'step_pct' => PowerGrid::stepPct(),
            'is_pod'  => ($ship['type_key'] ?? '') === 'escape_pod',
        ]));
    }

    public function save(Request $request): Response
    {
        $in = [];
        foreach (PowerGrid::CHANNELS as $c) {
            $in[$c] = $request->int('eps_' . $c, -1);
        }
        $res = PowerGrid::save(Ctx::$player, Ctx::$ship, $in);

        if ($res['ok']) {
            $cost = (int) ($res['cost'] ?? 0);
            Session::flash('success', $cost > 0
                ? "Griglia di potenza ritarata (−{$cost} turno)."
                : 'Nessuna modifica alla griglia.');
        } else {
            Session::flash('error', $res['error'] ?? 'Operazione non riuscita.');
        }
        return redirect('/gioco/eps');
    }
}
