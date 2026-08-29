<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Achievements;
use App\Game\BlackMarket;
use App\Game\Contracts;
use App\Game\Ctx;
use App\Game\Season;
use App\Game\TurnManager;

final class MetaController
{
    public function achievements(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        Achievements::evaluate((int) $player['id']);
        return Response::html(view('game/achievements', [
            'title'  => 'Traguardi',
            'player' => $player,
            'all'    => Achievements::all(),
            'earned' => Achievements::earned((int) $player['id']),
            'points' => Achievements::points((int) $player['id']),
        ]));
    }

    public function hall(Request $request): Response
    {
        return Response::html(view('game/hall', [
            'title'   => 'Albo d\'oro',
            'player'  => Ctx::$player,
            'current' => Season::current(),
            'hall'    => Season::hall(),
        ]));
    }

    // --- mercato nero ------------------------------------------------

    public function blackMarket(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('game/blackmarket', [
            'title'     => 'Mercato nero',
            'player'    => $player,
            'ship'      => Ctx::$ship,
            'available' => BlackMarket::available((int) $player['sector_id']),
            'catalog'   => BlackMarket::catalog(),
        ]));
    }

    public function bmAction(Request $request): Response
    {
        $op = $request->str('op');
        $res = match ($op) {
            'sell'   => BlackMarket::sell(Ctx::$player, Ctx::$ship, $request->str('commodity'), $request->int('qty')),
            'buy'    => BlackMarket::buy(Ctx::$player, Ctx::$ship, $request->str('item'), $request->int('qty', 1)),
            'bounty' => BlackMarket::clearBounty(Ctx::$player),
            default  => ['ok' => false, 'error' => 'Azione sconosciuta.'],
        };
        if (empty($res['ok'])) {
            Session::flash('error', $res['error']);
        } else {
            $align = isset($res['align']) ? " (allineamento {$res['align']})" : '';
            Session::flash('success', match ($op) {
                'sell'   => 'Merce piazzata per ' . number_format($res['total'], 0, ',', '.') . " cr{$align}.",
                'buy'    => 'Acquisto concluso' . (isset($res['qty']) ? " x{$res['qty']}" : '') . " per {$res['cost']} cr{$align}.",
                'bounty' => 'Taglia ripulita per ' . number_format($res['cost'], 0, ',', '.') . ' cr.',
                default  => 'Fatto.',
            });
        }
        return redirect('/gioco/mercato-nero');
    }

    // --- contratti -------------------------------------------------

    public function contracts(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('game/contracts', [
            'title'  => 'Contratti',
            'player' => $player,
            'board'  => Contracts::board((int) $player['id']),
            'mine'   => Contracts::mine((int) $player['id']),
        ]));
    }

    public function contractAction(Request $request): Response
    {
        $op = $request->str('op');
        $res = match ($op) {
            'bounty'   => Contracts::open(Ctx::$player, 'bounty', ['target' => $request->str('target'), 'reward' => $request->int('reward')]),
            'delivery' => Contracts::open(Ctx::$player, 'delivery', [
                'commodity' => $request->str('commodity'), 'qty' => $request->int('qty'),
                'sector' => $request->int('sector'), 'reward' => $request->int('reward'),
            ]),
            'cancel'   => Contracts::cancel(Ctx::$player, $request->int('id')),
            'deliver'  => Contracts::deliver(Ctx::$player, Ctx::$ship, $request->int('id')),
            default    => ['ok' => false, 'error' => 'Azione sconosciuta.'],
        };
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok'])
            ? match ($op) {
                'cancel'  => 'Contratto annullato, cauzione restituita.',
                'deliver' => 'Consegna completata: +' . number_format($res['reward'], 0, ',', '.') . ' cr.',
                default   => 'Contratto pubblicato.',
            }
            : $res['error']);
        return redirect('/gioco/contratti');
    }
}
