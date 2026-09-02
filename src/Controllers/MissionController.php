<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\AwayMissions;
use App\Game\Crew;
use App\Game\Ctx;
use App\Game\TurnManager;

final class MissionController
{
    public function index(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);

        $officers = array_values(array_filter(
            Crew::roster((int) $player['id']),
            static fn ($o) => (int) $o['assigned'] === 1 && $o['status'] === 'active'
                && ($o['ready_at'] === null || strtotime((string) $o['ready_at']) <= time())
        ));

        return Response::html(view('game/missions', [
            'title'    => 'Missioni away',
            'player'   => $player,
            'missions' => AwayMissions::open((int) $player['id'], (int) $player['sector_id']),
            'officers' => $officers,
            'busy'     => array_values(array_filter(
                Crew::roster((int) $player['id']),
                static fn ($o) => (int) $o['assigned'] === 1 && ($o['status'] !== 'active'
                    || ($o['ready_at'] !== null && strtotime((string) $o['ready_at']) > time()))
            )),
            'log'      => AwayMissions::log((int) $player['id']),
        ]));
    }

    public function run(Request $request): Response
    {
        $officers = array_map('intval', (array) $request->input('officers', []));
        $res = AwayMissions::run(Ctx::$player, $request->int('mission'), $officers);
        if (empty($res['ok'])) {
            Session::flash('error', $res['error']);
            return redirect('/gioco/missioni');
        }
        $tail = $res['reward_text'] !== '' ? ' — ' . $res['reward_text'] : '';
        Session::flash(
            in_array($res['outcome'], ['failure', 'disaster'], true) ? 'error' : 'success',
            "{$res['label']} (margine {$res['margin']}){$tail}."
        );
        return redirect('/gioco/missioni');
    }
}
