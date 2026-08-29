<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Admin;

final class AdminGameController
{
    public function show(Request $request): Response
    {
        return Response::html(view('admin/game', [
            'title'   => 'Controllo gioco',
            'stats'   => Admin::stats(),
            'config'  => Admin::configGrouped(),
            'players' => Admin::players(120),
            'season'  => \App\Game\Season::current(),
        ]));
    }

    public function config(Request $request): Response
    {
        $actor = Auth::id() ?? 0;
        $res = $request->str('op') === 'reset'
            ? Admin::resetConfig($actor, $request->str('key'))
            : Admin::setConfig($actor, $request->str('key'), $request->str('value'));
        $this->flash($res, 'Configurazione aggiornata.');
        return redirect('/admin/gioco#config');
    }

    public function event(Request $request): Response
    {
        $actor = Auth::id() ?? 0;
        $res = $request->str('op') === 'end'
            ? Admin::endEvent($actor, $request->int('id'))
            : Admin::forceEvent($actor, $request->str('kind'));
        $this->flash($res, 'Evento aggiornato.');
        return redirect('/admin/gioco#eventi');
    }

    public function npc(Request $request): Response
    {
        $actor = Auth::id() ?? 0;
        $res = $request->str('op') === 'purge'
            ? Admin::purgeNpcs($actor, $request->str('kind'))
            : Admin::spawnNpcs($actor, $request->str('kind'), $request->int('n', 1));
        $this->flash($res, 'NPC aggiornati.');
        return redirect('/admin/gioco#npc');
    }

    public function bigbang(Request $request): Response
    {
        if ($request->str('confirm') !== 'SUBSPAZIO') {
            Session::flash('error', 'Conferma non valida: digita SUBSPAZIO.');
            return redirect('/admin/gioco#universo');
        }
        $res = Admin::bigBang(Auth::id() ?? 0);
        Session::flash('success', 'Big Bang: ' . $res['universe']['sectors'] . ' settori, ' . $res['ports']['ports'] . ' porti.');
        return redirect('/admin/gioco#universo');
    }

    public function season(Request $request): Response
    {
        if ($request->str('confirm') !== 'CHIUDI') {
            Session::flash('error', 'Conferma non valida: digita CHIUDI.');
            return redirect('/admin/gioco#stagione');
        }
        $res = \App\Game\Season::close(Auth::id() ?? 0, $request->str('regen') === '1');
        Session::flash('success', 'Stagione chiusa. Aperta la Stagione ' . $res['number'] . ' (snapshot ' . $res['snapshot'] . ' comandanti).');
        return redirect('/admin/gioco#stagione');
    }

    public function player(Request $request): Response
    {
        $actor = Auth::id() ?? 0;
        $op = $request->str('op');
        $uid = $request->int('user_id');
        $pid = $request->int('player_id');

        $res = match ($op) {
            'kick'      => Admin::kick($actor, $uid),
            'suspend'   => Admin::setStatus($actor, $uid, 'suspended'),
            'ban'       => Admin::setStatus($actor, $uid, 'banned'),
            'activate'  => Admin::setStatus($actor, $uid, 'active'),
            'teleport'  => Admin::teleport($actor, $pid, $request->int('sector', 1)),
            'adjust'    => Admin::adjust($actor, $pid, $request->int('credits', 0), $request->str('turns') === '' ? null : $request->int('turns')),
            'reset'     => Admin::resetPlayer($actor, $pid),
            default     => ['ok' => false, 'error' => 'Azione sconosciuta.'],
        };
        $this->flash($res, 'Azione eseguita.');
        return redirect('/admin/gioco#giocatori');
    }

    private function flash(array $res, string $okMsg): void
    {
        Session::flash(!empty($res['ok']) ? 'success' : 'error', !empty($res['ok']) ? $okMsg : ($res['error'] ?? 'Errore.'));
    }
}
