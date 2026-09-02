<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\BattleLog;
use App\Game\Ctx;
use App\Game\RouteLog;
use App\Game\SectorNotes;
use App\Game\TurnManager;

final class RegistroController
{
    public function battles(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('game/battles', [
            'title'  => 'Registro battaglie',
            'wide'   => true,
            'player' => $player,
            'rows'   => BattleLog::forPlayer((int) $player['id'], 50),
        ]));
    }

    public function battle(Request $request, string $id): Response
    {
        $player = Ctx::$player;
        $b = BattleLog::get((int) $id, (int) $player['id'], Auth::isAdmin());
        if ($b === null) {
            Session::flash('error', 'Battaglia non trovata o non tua.');
            return redirect('/gioco/battaglie');
        }
        return Response::html(view('game/battle', [
            'title'  => 'Battaglia #' . $b['id'],
            'player' => $player,
            'b'      => $b,
        ]));
    }

    public function routes(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('game/routes', [
            'title'   => 'Cronologia rotte',
            'wide'    => true,
            'player'  => $player,
            'recent'  => RouteLog::recent((int) $player['id'], 60),
            'visited' => RouteLog::mostVisited((int) $player['id'], 15),
            'stats'   => RouteLog::stats((int) $player['id']),
            'notes'   => SectorNotes::all((int) $player['id']),
        ]));
    }

    public function saveNote(Request $request): Response
    {
        $res = SectorNotes::set(
            (int) Ctx::$player['id'],
            $request->int('sector'),
            $request->str('label'),
            $request->str('note'),
            $request->str('pinned') === '1',
        );
        Session::flash($res['ok'] ? 'success' : 'error',
            $res['ok'] ? (!empty($res['removed']) ? 'Nota rimossa.' : 'Nota salvata.') : $res['error']);
        return redirect($request->str('back', '/gioco'));
    }
}
