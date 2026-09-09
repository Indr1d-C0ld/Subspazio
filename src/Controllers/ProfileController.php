<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Identity;

final class ProfileController
{
    public function show(Request $request): Response
    {
        return Response::html(view('game/profilo', [
            'title'   => 'Profilo comandante',
            'player'  => Ctx::$player,
            'ship'    => Ctx::$ship,
            'palette' => Identity::PALETTE,
            'crests'  => Identity::CRESTS,
        ]));
    }

    public function save(Request $request): Response
    {
        $res = Identity::save((int) Ctx::$player['id'], (int) Ctx::$ship['id'], [
            'color'     => $request->str('color'),
            'crest'     => $request->str('crest'),
            'motto'     => $request->str('motto'),
            'ship_name' => $request->str('ship_name'),
            'registry'  => $request->str('registry'),
        ]);

        if ($res['ok']) {
            Session::flash('success', 'Identità aggiornata, comandante.');
        } else {
            Session::flash('errors', $res['errors'] ?? ['Dati non validi.']);
        }
        return redirect('/gioco/profilo');
    }
}
