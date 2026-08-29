<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $stats = ['status' => 'setup', 'sectors' => null, 'players' => null];
        try {
            $cfg = [];
            foreach (Database::all('SELECT ckey, cvalue FROM game_config') as $row) {
                $cfg[$row['ckey']] = $row['cvalue'];
            }
            $stats['status']  = $cfg['game.status'] ?? 'setup';
            $stats['sectors'] = $cfg['universe.sectors'] ?? null;
            $stats['players'] = (int) (Database::first(
                "SELECT COUNT(*) AS c FROM users WHERE status = 'active'"
            )['c'] ?? 0);
        } catch (\Throwable) {
            // il layout gestira' la modalita' "setup incompleto"
        }

        return Response::html(view('home', [
            'title' => config('app.name', 'SubSpazio'),
            'user'  => $user,
            'stats' => $stats,
        ]));
    }

    public function health(Request $request): Response
    {
        $db = Database::isReachable();
        return Response::json([
            'app'  => config('app.name', 'SubSpazio'),
            'ok'   => $db,
            'db'   => $db ? 'up' : 'down',
            'time' => date('c'),
        ], $db ? 200 : 503);
    }
}
