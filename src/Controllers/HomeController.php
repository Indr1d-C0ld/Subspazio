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

        $stats = ['status' => 'setup', 'sectors' => null, 'players' => null, 'season' => null, 'registration' => 'verify'];
        try {
            $cfg = [];
            foreach (Database::all('SELECT ckey, cvalue FROM game_config') as $row) {
                $cfg[$row['ckey']] = $row['cvalue'];
            }
            $stats['status']  = $cfg['game.status'] ?? 'setup';
            $stats['sectors'] = $cfg['universe.sectors'] ?? null;
            $stats['season']  = $cfg['season.number'] ?? null;
            $stats['registration'] = $cfg['registration.open'] ?? 'verify';
            $stats['players'] = (int) (Database::first(
                "SELECT COUNT(*) AS c FROM users WHERE status = 'active'"
            )['c'] ?? 0);
            // Ripartenza da zero (13/10/2026): la pagina lo dice per il primo
            // mese della Stagione 1, poi il riquadro sparisce da solo.
            $stats['ripartenza'] = \App\Game\Season::ripartenzaRecente();
            // la stessa cronaca del notiziario: come va la galassia nuova
            $stats['cronaca'] = $stats['ripartenza'] !== null ? \App\Game\FedNews::cronacaRipartenza($stats['ripartenza']) : null;
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
