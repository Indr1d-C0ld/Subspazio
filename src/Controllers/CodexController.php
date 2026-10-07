<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Session;
use App\Game\Codex;
use App\Game\Ctx;
use App\Game\Reperti;
use App\Game\Shipyard;

final class CodexController
{
    public function index(Request $request): Response
    {
        $pid = (int) Ctx::$player['id'];
        return Response::html(view('game/codex', [
            'title'   => 'Codex',
            'entries' => Codex::forPlayer($pid),
            'counts'  => Codex::counts($pid),
            'reperti' => Reperti::posseduti($pid),
            'completate' => Reperti::completate($pid),
            'progetti' => Database::all(
                'SELECT r.ckey, r.label, it.rarity, (pp.player_id IS NOT NULL) AS posseduto
                   FROM recipes r JOIN item_types it ON it.ckey = r.output_item
                   LEFT JOIN player_progetti pp ON pp.player_id = ? AND pp.recipe_key = r.ckey
                  WHERE r.progetto = 1 ORDER BY r.sort', [$pid]),
            'at_dock' => Shipyard::atShipyard((int) Ctx::$player['sector_id']),
        ]));
    }

    public function vendi(Request $request): Response
    {
        $res = Reperti::vendi(Ctx::$player, $request->str('ckey'), max(1, $request->int('qty', 1)));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Venduto: {$res['qty']} × {$res['name']} per " . number_format($res['credits'], 0, ',', '.') . ' cr.'
            : $res['error']);
        return redirect('/gioco/codex');
    }

    public function collezione(Request $request): Response
    {
        $res = Reperti::completa(Ctx::$player, $request->str('collezione'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? "Collezione completata: {$res['name']}. +" . number_format($res['credits'], 0, ',', '.') . " cr, +{$res['xp']} exp"
              . ($res['modulo'] !== null ? ", e un modulo: {$res['modulo']['name']} [{$res['modulo']['label']}]." : '.')
            : $res['error']);
        return redirect('/gioco/codex');
    }
}
