<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Corp;
use App\Game\Ctx;
use App\Game\GameConfig;
use App\Game\TurnManager;

final class CorpController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $corp = Corp::of((int) $player['id']);

        return Response::html(view('game/corp', [
            'title'      => 'Corporazione',
            'player'     => $player,
            'corp'       => $corp,
            'members'    => $corp ? Corp::members((int) $corp['id']) : [],
            'alliances'  => $corp ? Corp::alliances((int) $corp['id']) : [],
            'cost'       => GameConfig::int('corp.create_cost', 50000),
            'at_dock'    => (int) $player['sector_id'] === 1,
        ]));
    }

    public function ally(Request $request): Response
    {
        $op = $request->str('op');
        $res = $op === 'dissolve'
            ? Corp::dissolveAlliance(Ctx::$player, $request->int('corp'))
            : Corp::proposeAlliance(Ctx::$player, $request->str('tag'));
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok'])
            ? (!empty($res['accepted']) ? 'Alleanza siglata.' : (!empty($res['proposed']) ? 'Proposta inviata.' : 'Alleanza sciolta.'))
            : $res['error']);
        return redirect('/gioco/corp');
    }

    public function create(Request $request): Response
    {
        $res = Corp::create(Ctx::$player, $request->str('name'), $request->str('tag'), (string) $request->input('password', ''));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Corporazione fondata: ' . $res['corp']['name'] . ' [' . $res['corp']['tag'] . '].'
            : $res['error']);
        return redirect('/gioco/corp');
    }

    public function join(Request $request): Response
    {
        $res = Corp::join(Ctx::$player, $request->str('name'), (string) $request->input('password', ''));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Sei entrato in ' . $res['corp']['name'] . '.'
            : $res['error']);
        return redirect('/gioco/corp');
    }

    public function leave(Request $request): Response
    {
        $res = Corp::leave(Ctx::$player);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? ($res['disbanded'] ? 'Corporazione sciolta.' : 'Hai lasciato la corporazione.')
            : $res['error']);
        return redirect('/gioco/corp');
    }

    public function treasury(Request $request, string $dir): Response
    {
        $res = Corp::treasury(Ctx::$player, $request->int('amount'), $dir === 'preleva' ? 'withdraw' : 'deposit');
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok'])
            ? 'Cassa corp: ' . number_format($res['treasury'], 0, ',', '.') . ' cr.'
            : $res['error']);
        return redirect('/gioco/corp');
    }
}
