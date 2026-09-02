<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Bank;
use App\Game\Ctx;
use App\Game\Faction;
use App\Game\GameConfig;
use App\Game\TurnManager;

final class BankController
{
    public function show(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);

        if (!Bank::enabled()) {
            Session::flash('error', 'Servizio bancario non disponibile.');
            return redirect('/gioco');
        }
        if (!Bank::atBank((int) $player['sector_id'])) {
            Session::flash('error', 'La Banca Intergalattica opera solo allo StarDock.');
            return redirect('/gioco');
        }
        if ($block = Faction::stardockBlocked((int) $player['id'])) {
            Session::flash('error', $block);
            return redirect('/gioco/fazioni');
        }

        return Response::html(view('game/bank', [
            'title'   => 'Banca Intergalattica',
            'player'  => $player,
            'account' => Bank::account((int) $player['id']),
            'rate'    => GameConfig::float('bank.daily_interest_pct', 0.5),
        ]));
    }

    public function operate(Request $request, string $dir): Response
    {
        if ($block = Faction::stardockBlocked((int) Ctx::$player['id'])) {
            Session::flash('error', $block);
            return redirect('/gioco/fazioni');
        }
        $amount = $request->int('amount');
        $res = $dir === 'deposita'
            ? Bank::deposit(Ctx::$player, $amount)
            : Bank::withdraw(Ctx::$player, $amount);

        if (!$res['ok']) {
            Session::flash('error', $res['error']);
        } else {
            Session::flash('success', ($dir === 'deposita' ? 'Depositati ' : 'Prelevati ')
                . number_format($amount, 0, ',', '.') . ' cr. Saldo: '
                . number_format($res['balance'], 0, ',', '.') . ' cr.');
        }
        return redirect('/gioco/banca');
    }
}
