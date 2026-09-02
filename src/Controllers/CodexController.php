<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Game\Codex;
use App\Game\Ctx;

final class CodexController
{
    public function index(Request $request): Response
    {
        $pid = (int) Ctx::$player['id'];
        return Response::html(view('game/codex', [
            'title'   => 'Codex',
            'entries' => Codex::forPlayer($pid),
            'counts'  => Codex::counts($pid),
        ]));
    }
}
