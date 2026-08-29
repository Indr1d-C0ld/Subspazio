<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Game\Ctx;
use App\Game\Leaderboard;
use App\Game\TurnManager;

final class LeaderboardController
{
    public function show(Request $request): Response
    {
        return Response::html(view('game/leaderboard', [
            'title'   => 'Classifiche',
            'player'  => TurnManager::sync(Ctx::$player),
            'players' => Leaderboard::topPlayers(30),
            'corps'   => Leaderboard::topCorps(15),
        ]));
    }
}
