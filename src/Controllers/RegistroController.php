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
        return redirect(self::ritorno($request));
    }

    /**
     * Toglie nota e preferito di un settore, da qualunque punto della
     * galassia. Prima si potevano cambiare solo stando in quel settore, e solo
     * svuotando i campi e togliendo la spunta: un preferito lontano restava
     * nella barra per sempre.
     */
    public function removeNote(Request $request): Response
    {
        SectorNotes::remove((int) Ctx::$player['id'], $request->int('sector'));
        Session::flash('success', 'Preferito e nota del settore ' . $request->int('sector') . ' rimossi.');
        return redirect(self::ritorno($request));
    }

    /** Si torna solo alle due pagine che ospitano i moduli delle note. */
    private static function ritorno(Request $request): string
    {
        $back = $request->str('back', '/gioco');
        return in_array($back, ['/gioco', '/gioco/rotte'], true) ? $back : '/gioco';
    }
}
