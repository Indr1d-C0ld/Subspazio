<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Combat;
use App\Game\Ctx;
use App\Game\Economy;
use App\Game\Navigation;
use App\Game\Planets;
use App\Game\TurnManager;

final class PlanetController
{
    public function sectorList(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('game/planets', [
            'title'   => 'Pianeti — Settore ' . $player['sector_id'],
            'player'  => $player,
            'ship'    => Ctx::$ship,
            'planets' => Navigation::look($player)['planets'],
            'at_dock' => (int) $player['sector_id'] === 1 || Planets::inSector((int) $player['sector_id']) !== null,
        ]));
    }

    public function manage(Request $request, string $id): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $p = Planets::get((int) $id);
        if ($p === null) {
            Session::flash('error', 'Pianeta inesistente.');
            return redirect('/gioco');
        }

        return Response::html(view('game/planet', [
            'title'  => 'Pianeta ' . $p['name'],
            'player' => $player,
            'ship'   => Ctx::$ship,
            'p'      => $p,
            'own'    => Planets::isOwn($p, $player),
            'here'   => (int) $player['sector_id'] === (int) $p['sector_id'],
            'next'   => Planets::nextCitadel($p),
            'used'   => Economy::holdsUsed(Ctx::$ship),
        ]));
    }

    public function genesis(Request $request): Response
    {
        $res = Planets::genesis(Ctx::$player, Ctx::$ship);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Genesi: nuovo pianeta {$res['name']} (tipo {$res['type']})."
            : $res['error']);
        return redirect('/gioco/pianeti');
    }

    public function colonists(Request $request, string $id): Response
    {
        $res = Planets::moveColonists(Ctx::$player, Ctx::$ship, (int) $id, $request->str('bucket'), $request->int('qty'), $request->str('dir'));
        $this->flash($res, 'Coloni trasferiti.');
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function assign(Request $request, string $id): Response
    {
        $res = Planets::assignColonists(Ctx::$player, (int) $id, $request->str('from'), $request->str('to'), $request->int('qty'));
        $this->flash($res, 'Coloni riassegnati.');
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function resources(Request $request, string $id): Response
    {
        $res = Planets::moveResources(Ctx::$player, Ctx::$ship, (int) $id, $request->str('commodity'), $request->int('qty'), $request->str('dir'));
        $this->flash($res, 'Risorse trasferite.');
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function treasury(Request $request, string $id): Response
    {
        $res = Planets::treasury(Ctx::$player, (int) $id, $request->int('amount'), $request->str('dir'));
        $this->flash($res, 'Operazione di tesoreria completata.');
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function citadel(Request $request, string $id): Response
    {
        $res = Planets::upgradeCitadel(Ctx::$player, (int) $id);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Citadel liv. {$res['level']} in costruzione (~{$res['hours']}h)."
            : $res['error']);
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function quasar(Request $request, string $id): Response
    {
        $res = Planets::buildQuasar(Ctx::$player, (int) $id);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Cannone Quasar liv. {$res['quasar_level']}."
            : $res['error']);
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function garrison(Request $request, string $id): Response
    {
        $res = Planets::garrison(Ctx::$player, Ctx::$ship, (int) $id, $request->int('qty'), $request->str('dir'));
        $this->flash($res, 'Guarnigione aggiornata.');
        return redirect('/gioco/pianeta/' . (int) $id);
    }

    public function pickup(Request $request): Response
    {
        $res = Planets::pickupColonists(Ctx::$player, Ctx::$ship, $request->int('qty'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Imbarcati {$res['loaded']} coloni (quota residua oggi: {$res['remaining_today']})."
            : $res['error']);
        return redirect('/gioco');
    }

    public function attack(Request $request, string $id): Response
    {
        $bombard = $request->str('bombard') === '1';
        $res = Combat::attackPlanet(Ctx::$player, Ctx::$ship, (int) $id, $request->int('fighters'), $bombard);
        if (empty($res['ok'])) {
            Session::flash('error', $res['error']);
        } elseif ($res['destroyed_self']) {
            Session::flash('error', "Le difese di {$res['planet_name']} ti hanno distrutto. Capsula allo StarDock.");
        } elseif ($res['cracked']) {
            $st = [];
            foreach ($res['stolen'] as $c => $q) {
                $st[] = "{$q} " . Economy::label($c);
            }
            $b = $res['bombarded'] ? " Bombardamento: {$res['bomb_killed']} coloni sterminati." : '';
            Session::flash('success', "Difese di {$res['planet_name']} annientate ({$res['rounds']} round). Saccheggiati "
                . number_format($res['loot'], 0, ',', '.') . ' cr'
                . ($st ? ' + ' . implode(', ', $st) : '') . '.' . $b);
        } else {
            Session::flash('error', "Assalto a {$res['planet_name']} respinto ({$res['rounds']} round).");
        }
        return redirect('/gioco');
    }

    private function flash(array $res, string $okMsg): void
    {
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok']) ? $okMsg : $res['error']);
    }
}
