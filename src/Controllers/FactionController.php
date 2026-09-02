<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Faction;
use App\Game\Shipyard;
use App\Game\TurnManager;

final class FactionController
{
    public function index(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $pid = (int) $player['id'];

        $factions = Database::all('SELECT * FROM factions ORDER BY FIELD(ckey,\'fed\',\'ferrengi\',\'hegemony\',\'frontier\')');
        $rep = Faction::all($pid);

        return Response::html(view('game/factions', [
            'title'    => 'Fazioni',
            'player'   => $player,
            'at_dock'  => Shipyard::atShipyard((int) $player['sector_id']),
            'factions' => $factions,
            'rep'      => $rep,
            'offers'   => Faction::offers($pid),
            'log'      => Database::all('SELECT * FROM faction_log WHERE player_id = ? ORDER BY id DESC LIMIT 15', [$pid]),
            'blocked'  => Faction::stardockBlocked($pid),
        ]));
    }

    public function buy(Request $request): Response
    {
        $res = Faction::buyOffer(Ctx::$player, $request->int('offer'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Acquistato: {$res['name']} ({$res['cost']} cr). È nell'inventario moduli."
            : $res['error']);
        return redirect('/gioco/fazioni');
    }

    public function amnesty(Request $request): Response
    {
        $res = Faction::amnesty(Ctx::$player);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Ammenda pagata ({$res['cost']} cr): la Federazione riapre i servizi StarDock."
            : $res['error']);
        return redirect('/gioco/fazioni');
    }
}
