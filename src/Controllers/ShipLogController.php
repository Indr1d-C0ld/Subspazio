<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Game\Ctx;
use App\Game\ShipLog;

final class ShipLogController
{
    public function show(Request $request): Response
    {
        $pid = (int) Ctx::$player['id'];
        $before = max(0, $request->int('before'));

        $entries = ShipLog::page($pid, 60, $before);
        $unread  = ShipLog::unread($pid);

        // aprire il giornale segna tutto come letto
        if ($before === 0) {
            ShipLog::markRead($pid);
        }

        $nextBefore = count($entries) === 60 ? (int) end($entries)['id'] : 0;

        return Response::html(view('game/shiplog', [
            'title'       => 'Giornale di bordo',
            'player'      => Ctx::$player,
            'entries'     => $entries,
            'unread'      => $unread,
            'next_before' => $nextBefore,
        ]));
    }
}
