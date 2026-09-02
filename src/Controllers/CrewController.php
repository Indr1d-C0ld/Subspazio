<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Crew;
use App\Game\Ctx;
use App\Game\Shipyard;
use App\Game\TurnManager;

final class CrewController
{
    public function index(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship   = Ctx::$ship;
        $atDock = Shipyard::atShipyard((int) $player['sector_id']);

        return Response::html(view('game/crew', [
            'title'    => 'Equipaggio',
            'player'   => $player,
            'ship'     => $ship,
            'at_dock'  => $atDock,
            'slots'    => Crew::slots((string) $ship['type_key']),
            'roster'   => Crew::roster((int) $player['id']),
            'counts'   => Crew::counts((int) $player['id']),
            'recruits' => $atDock ? Crew::recruits((int) $player['id']) : [],
        ]));
    }

    public function hire(Request $request): Response
    {
        $res = Crew::hire(Ctx::$player, (string) (Ctx::$ship['type_key'] ?? ''), $request->int('candidate'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Assunto {$res['name']} (" . Crew::roleLabel($res['role']) . ", {$res['cost']} cr)"
                . ($res['assigned'] ? ' — imbarcato.' : ' — in riserva.')
            : $res['error']);
        return redirect('/gioco/equipaggio');
    }

    public function assign(Request $request): Response
    {
        $res = Crew::assign(Ctx::$player, (string) (Ctx::$ship['type_key'] ?? ''), $request->int('officer'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? "{$res['name']} imbarcato." : $res['error']);
        return redirect('/gioco/equipaggio');
    }

    public function bench(Request $request): Response
    {
        $res = Crew::bench(Ctx::$player, $request->int('officer'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? "{$res['name']} messo in riserva." : $res['error']);
        return redirect('/gioco/equipaggio');
    }

    public function dismiss(Request $request): Response
    {
        $res = Crew::dismiss(Ctx::$player, $request->int('officer'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? "{$res['name']} congedato." : $res['error']);
        return redirect('/gioco/equipaggio');
    }

    public function heal(Request $request): Response
    {
        $res = Crew::heal(Ctx::$player, $request->int('officer'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "{$res['name']} curato ({$res['cost']} cr)."
            : $res['error']);
        return redirect('/gioco/equipaggio');
    }

    public function ability(Request $request): Response
    {
        $res = Crew::useAbility(Ctx::$player, Ctx::$ship, $request->int('officer'), $request->int('target'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['msg'] : $res['error']);
        return redirect($request->str('back', '/gioco/equipaggio'));
    }
}
