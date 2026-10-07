<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Faction;
use App\Game\Legge;
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
            'bandoRep' => Faction::value($pid, 'fed') <= \App\Game\GameConfig::int('faction.tier_hostile', -60),
            'legge'    => [
                'punti'   => Legge::puntiDi($pid),
                'grado'   => Legge::grado(Legge::puntiDi($pid)),
                'costo'   => Legge::costoAmmenda($pid),
                'taglia'  => Legge::taglia($player),
                'ore'     => Legge::oreAlPerdono(Legge::puntiDi($pid)),
                'recenti' => Legge::recenti($pid),
                'fedina'  => Legge::fedina($pid),
            ],
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

    /** L'ammenda che azzera la notorieta': si paga da ovunque, anche con lo StarDock chiuso. */
    public function ammendaLegge(Request $request): Response
    {
        $res = Legge::ammenda(Ctx::$player);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Ammenda pagata (' . number_format($res['cost'], 0, ',', '.') . ' cr): la Federazione chiude il tuo fascicolo e richiama le squadre.'
            : $res['error']);
        return redirect('/gioco/fazioni');
    }
}
