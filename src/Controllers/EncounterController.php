<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Encounters;

final class EncounterController
{
    public function resolve(Request $request): Response
    {
        $res = Encounters::resolve((int) Ctx::$player['id'], $request->str('choice'));

        if ($res['ok']) {
            $msg = $res['text'] ?? 'Fatto.';
            if (!empty($res['summary'])) {
                $msg .= ' (' . $res['summary'] . ')';
            }
            Session::flash('success', $msg);
        } else {
            Session::flash('error', $res['error'] ?? 'Operazione non riuscita.');
        }
        return redirect('/gioco');
    }
}
