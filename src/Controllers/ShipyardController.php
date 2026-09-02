<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Economy;
use App\Game\Faction;
use App\Game\GameConfig;
use App\Game\Shipyard;
use App\Game\TurnManager;

final class ShipyardController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship = Ctx::$ship;

        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            Session::flash('error', 'Il cantiere e\' disponibile solo allo StarDock.');
            return redirect('/gioco');
        }
        if (($ship['type_key'] ?? '') !== 'escape_pod' && ($block = Faction::stardockBlocked((int) $player['id']))) {
            Session::flash('error', $block);
            return redirect('/gioco/fazioni');
        }

        return Response::html(view('game/shipyard', [
            'title'    => 'Cantiere StarDock',
            'player'   => $player,
            'ship'     => $ship,
            'catalog'  => Shipyard::catalog(),
            'trade_in' => Shipyard::tradeInValue($ship),
            'used'     => Economy::holdsUsed($ship),
            'prices'   => [
                'fighter'    => GameConfig::float('hardware.fighter_price', 12),
                'shield'     => GameConfig::float('hardware.shield_price', 8),
                'genesis'    => GameConfig::int('hardware.genesis_price', 31000),
                'probe'      => GameConfig::int('hardware.probe_price', 200),
                'armid'      => GameConfig::int('hardware.armid_price', 95),
                'limpet'     => GameConfig::int('hardware.limpet_price', 55),
                'escape_pod' => GameConfig::int('hardware.escape_pod_price', 3000),
                'scanner_density' => GameConfig::int('hardware.scanner_density_price', 3000),
                'scanner_holo'    => GameConfig::int('hardware.scanner_holo_price', 12000),
                'transwarp'  => GameConfig::int('hardware.transwarp_price', 28000),
                'cloak'      => GameConfig::int('hardware.cloak_price', 35000),
            ],
        ]));
    }

    private function factionGate(): ?Response
    {
        if ((Ctx::$ship['type_key'] ?? '') !== 'escape_pod' && ($b = Faction::stardockBlocked((int) Ctx::$player['id']))) {
            Session::flash('error', $b);
            return redirect('/gioco/fazioni');
        }
        return null;
    }

    public function buyShip(Request $request): Response
    {
        if ($r = $this->factionGate()) {
            return $r;
        }
        $res = Shipyard::buyShip(Ctx::$player, Ctx::$ship, $request->str('type'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Nuova nave: {$res['name']} (costo {$res['cost']} cr, permuta {$res['trade_in']})."
            : $res['error']);
        return redirect('/gioco/cantiere');
    }

    public function rescue(Request $request): Response
    {
        $res = Shipyard::rescueShip(Ctx::$player, Ctx::$ship);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "La Federazione ti assegna una {$res['name']}. Rimettiti in rotta, comandante."
            : $res['error']);
        return redirect('/gioco/cantiere');
    }

    public function upgrade(Request $request): Response
    {
        if ($r = $this->factionGate()) {
            return $r;
        }
        $res = Shipyard::upgrade(Ctx::$player, Ctx::$ship, $request->str('kind'), $request->int('qty'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Potenziamento {$res['kind']}: +{$res['qty']} per {$res['cost']} cr."
            : $res['error']);
        return redirect('/gioco/cantiere');
    }

    public function hardware(Request $request): Response
    {
        if ($r = $this->factionGate()) {
            return $r;
        }
        $res = Shipyard::buyHardware(Ctx::$player, Ctx::$ship, $request->str('item'), $request->int('qty', 1));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Acquistato: ' . $request->str('item') . (isset($res['qty']) ? " x{$res['qty']}" : '') . " per {$res['cost']} cr."
            : $res['error']);
        return redirect('/gioco/cantiere');
    }
}
