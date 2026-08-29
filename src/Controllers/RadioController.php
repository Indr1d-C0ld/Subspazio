<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Radio;
use App\Game\TurnManager;

final class RadioController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $inbox = Radio::inbox($player);
        Radio::markRead($player);

        return Response::html(view('game/radio', [
            'title'  => 'Radio subspaziale',
            'player' => $player,
            'inbox'  => $inbox,
            'in_fed' => (bool) \App\Game\Universe::sector((int) $player['sector_id'])['is_fedspace'],
            'has_corp' => \App\Game\Corp::corpIdOf((int) $player['id']) !== null,
        ]));
    }

    public function send(Request $request): Response
    {
        $res = Radio::send(
            Ctx::$player,
            $request->str('channel'),
            $request->str('body'),
            $request->str('target'),
        );
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Trasmesso.' : $res['error']);
        return redirect('/gioco/radio');
    }
}
