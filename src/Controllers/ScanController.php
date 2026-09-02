<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Game\Ctx;
use App\Game\SectorFeatures;

final class ScanController
{
    public function scan(Request $request): Response
    {
        $r = SectorFeatures::scan(Ctx::$player, Ctx::$ship);
        if (empty($r['ok'])) {
            Session::flash('error', $r['error']);
        } elseif ($r['found'] === 0) {
            Session::flash('success', "Scansione completata ({$r['sectors']} settori nel raggio): niente di nuovo.");
        } else {
            $bits = [];
            foreach ($r['by_kind'] as $k => $n) {
                $bits[] = "{$n} " . (SectorFeatures::KIND_LABEL[$k] ?? $k) . ($n > 1 ? 'i' : '');
            }
            Session::flash('success', "Scansione: individuati " . implode(', ', $bits) . '.');
        }
        return redirect('/gioco');
    }

    public function probe(Request $request): Response
    {
        $r = SectorFeatures::probe(Ctx::$player, Ctx::$ship, $request->int('to'));
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok']
            ? "Sonda sul settore {$r['sector']}: " . ($r['found'] > 0 ? "{$r['found']} feature rilevate." : 'niente di rilevante.')
            : $r['error']);
        return redirect('/gioco');
    }

    public function salvage(Request $request): Response
    {
        $r = SectorFeatures::salvage(Ctx::$player, Ctx::$ship, $request->int('feature'));
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Relitto spogliato: {$r['text']}." : $r['error']);
        return redirect('/gioco');
    }

    public function harvest(Request $request): Response
    {
        $r = SectorFeatures::harvest(Ctx::$player, Ctx::$ship, $request->int('feature'));
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Deposito svuotato: {$r['text']}." : $r['error']);
        return redirect('/gioco');
    }

    public function study(Request $request): Response
    {
        $r = SectorFeatures::study(Ctx::$player, Ctx::$ship, $request->int('feature'));
        Session::flash($r['ok'] ? ($r['done'] ?? false ? 'success' : 'success') : 'error', $r['ok'] ? $r['text'] : $r['error']);
        return redirect('/gioco');
    }
}
