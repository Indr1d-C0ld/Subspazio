<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Navigation;
use App\Game\TerminalRenderer;
use App\Game\TurnManager;

final class GameController
{
    public function index(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship   = Ctx::$ship;
        \App\Game\Achievements::evaluate((int) $player['id']);
        $look   = Navigation::look($player);

        return Response::html(view('game/index', [
            'title'   => 'Plancia — Settore ' . $look['id'],
            'player'  => $player,
            'ship'    => $ship,
            'look'    => $look,
            'created' => Ctx::$created,
            'events'  => \App\Game\Events::active(),
        ]));
    }

    public function move(Request $request): Response
    {
        $res = Navigation::move(Ctx::$player, Ctx::$ship, $request->int('to'));
        if (!$res['ok']) {
            Session::flash('error', $res['error']);
        } elseif (!empty($res['entry_events'])) {
            Session::flash($res['destroyed'] ? 'error' : 'success', implode(' ', $res['entry_events']));
        }
        return redirect('/gioco');
    }

    public function course(Request $request): Response
    {
        $to = $request->int('to');
        $player = Ctx::$player;
        $ship = Ctx::$ship;

        $known = Navigation::plotCourse($player, $to, true, (int) ($ship['turns_per_warp'] ?? 1));
        $full  = $known['ok'] ? null : Navigation::plotCourse($player, $to, false, (int) ($ship['turns_per_warp'] ?? 1));

        return Response::html(view('game/course', [
            'title'  => 'Rotta verso ' . $to,
            'to'     => $to,
            'player' => $player,
            'ship'   => $ship,
            'known'  => $known,
            'full'   => $full,
        ]));
    }

    public function autopilot(Request $request): Response
    {
        $res = Navigation::autopilot(Ctx::$player, Ctx::$ship, $request->int('to'), true);
        if (!$res['ok']) {
            Session::flash('error', $res['error'] ?? 'Rotta non disponibile.');
        } else {
            $n = count($res['moved']);
            $reason = [
                'arrived' => 'arrivato a destinazione',
                'no_turns' => 'turni esauriti',
                'max_hops' => 'limite salti raggiunto',
            ][$res['stopped']] ?? $res['stopped'];
            Session::flash($n > 0 ? 'success' : 'error', "Autopilota: {$n} warp ({$reason}).");
        }
        return redirect('/gioco');
    }

    public function beacon(Request $request): Response
    {
        Navigation::setBeacon(Ctx::$player, $request->str('text'));
        Session::flash('success', 'Faro aggiornato.');
        return redirect('/gioco');
    }

    // --- skin terminale ----------------------------------------------------

    public function terminal(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::html(view('terminal/index', [
            'title'  => 'Terminale',
            'player' => $player,
            'ship'   => Ctx::$ship,
            'intro'  => TerminalRenderer::sector(Navigation::look($player))
                . "\n" . TerminalRenderer::help(),
        ]));
    }
}
