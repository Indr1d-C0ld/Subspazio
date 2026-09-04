<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\GameConfig;
use App\Game\Industry;
use App\Game\Loot;
use App\Game\Modules;
use App\Game\ShipStats;
use App\Game\Shipyard;
use App\Game\TurnManager;

final class ModuleController
{
    public function index(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship   = Ctx::$ship;

        return Response::html(view('game/modules', [
            'title'      => 'Officina moduli',
            'player'     => $player,
            'ship'       => $ship,
            'at_dock'    => Shipyard::atShipyard((int) $player['sector_id']),
            'slots'      => ShipStats::slots((string) $ship['type_key']),
            'installed'  => Modules::installed((int) $ship['id']),
            'inventory'  => Modules::inventory((int) $player['id']),
            'up_credits' => self::parseTiers(GameConfig::str('loot.upgrade_cost_credits', '')),
            'up_salvage' => self::parseTiers(GameConfig::str('loot.upgrade_cost_salvage', '')),
            'recipes'    => Industry::recipes($player, $ship),
            'jobs'       => Industry::craftJobs((int) $player['id']),
            'max_jobs'   => GameConfig::int('craft.max_jobs', 3),
            'refine'     => [
                'ore' => GameConfig::int('craft.refine_ore_per_component', 4),
                'equ' => GameConfig::int('craft.refine_equ_per_component', 2),
            ],
        ]));
    }

    public function refine(Request $request): Response
    {
        $res = Industry::refine(Ctx::$player, Ctx::$ship, $request->int('qty', 1));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Raffinati {$res['components']} Componenti ({$res['ore']} minerale + {$res['equ']} equip.)."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function craft(Request $request): Response
    {
        $res = Industry::craft(Ctx::$player, Ctx::$ship, $request->str('recipe'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Avviata la fabbricazione di {$res['name']}: pronta fra circa {$res['minutes']} minuti."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function cancelJob(Request $request): Response
    {
        $res = Industry::cancelJob(Ctx::$player, $request->int('job'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Lavoro annullato: {$res['name']}. Materiali rimborsati (turni no)."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function install(Request $request): Response
    {
        $res = Modules::install(Ctx::$player, Ctx::$ship, $request->int('item'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Installato: {$res['name']} (slot " . Modules::catLabel($res['slot']) . ').'
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function remove(Request $request): Response
    {
        $res = Modules::remove(Ctx::$player, Ctx::$ship, $request->int('mod'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Rimosso: {$res['name']} (torna in inventario)."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function scrap(Request $request): Response
    {
        $res = Modules::scrap(Ctx::$player, $request->int('item'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Smontato {$res['name']}: +{$res['salvage']} Leghe di recupero."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    public function upgrade(Request $request): Response
    {
        $res = Modules::upgrade(Ctx::$player, $request->int('item'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Potenziato a {$res['name']} [{$res['label']}] — {$res['cost']} cr + {$res['mat']} Leghe."
            : $res['error']);
        return redirect('/gioco/moduli');
    }

    /** @return array<string,int> */
    private static function parseTiers(string $s): array
    {
        $out = [];
        foreach (explode(',', $s) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2) {
                $out[$p[0]] = (int) $p[1];
            }
        }
        return $out;
    }
}
