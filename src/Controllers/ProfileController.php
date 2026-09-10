<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\Identity;
use App\Game\MediaAsset;

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
            'media'   => MediaAsset::forOwner('player', (int) Ctx::$player['id']),
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

    public function uploadMedia(Request $request): Response
    {
        $kind = $request->str('kind');
        $file = $request->file('file');

        if ($file === null) {
            Session::flash('errors', ['Nessun file ricevuto.']);
            return redirect('/gioco/profilo');
        }

        $res = MediaAsset::storeUpload('player', (int) Ctx::$player['id'], $kind, $file, Auth::isAdmin());

        if ($res['ok']) {
            $label = MediaAsset::KINDS[$kind]['label'] ?? 'Immagine';
            Session::flash('success', !empty($res['auto_approved'])
                ? $label . ' caricata e approvata.'
                : $label . ' caricata: in attesa di approvazione dell\'amministratore.');
        } else {
            Session::flash('errors', $res['errors'] ?? ['Caricamento non riuscito.']);
        }
        return redirect('/gioco/profilo');
    }

    public function removeMedia(Request $request): Response
    {
        $res = MediaAsset::removeOwn(
            $request->int('id'),
            'player',
            (int) Ctx::$player['id'],
        );
        Session::flash($res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'Immagine rimossa.' : ($res['error'] ?? 'Rimozione non riuscita.'));
        return redirect('/gioco/profilo');
    }
}
