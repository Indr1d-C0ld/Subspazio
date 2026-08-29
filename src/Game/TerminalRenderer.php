<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rende in testo (stile door) lo stato di navigazione e interpreta i
 * comandi della skin terminale.
 */
final class TerminalRenderer
{
    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function prompt(array $player, array $ship): string
    {
        return sprintf(
            'Comando [TL=%d Cr=%s Settore=%d] (?=aiuto) : ',
            (int) $player['turns'],
            number_format((int) $player['credits'], 0, ',', '.'),
            (int) $player['sector_id'],
        );
    }

    /** @param array<string,mixed> $look */
    public static function sector(array $look): string
    {
        $l = [];
        $l[] = str_repeat('-', 56);
        $porto = $look['has_port'] ? '  [PORTO]' : '';
        $dock  = $look['is_stardock'] ? '  <<STARDOCK>>' : '';
        $l[] = sprintf('  Settore  : %d  %s%s%s', $look['id'], $look['name'], $porto, $dock);
        $l[] = sprintf('  Regione  : %s%s', $look['region'] ?? '?', $look['is_fedspace'] ? '  (Federazione - zona protetta)' : '');
        if (!empty($look['nebula'])) {
            $l[] = sprintf('  Nebulosa : %s', $look['nebula']);
        }
        if (!empty($look['beacon'])) {
            $l[] = sprintf('  Faro     : "%s"', $look['beacon']);
        }
        if (!empty($look['players_here'])) {
            $names = array_map(static fn ($p) => $p['handle'] . ' (' . $p['ship_type'] . ')', $look['players_here']);
            $l[] = '  Navi     : ' . implode(', ', $names);
        }
        $warps = array_map(static function ($w) {
            $mark = $w['visited'] ? '' : '*';
            $oneway = $w['return_known'] ? '' : ' (senso unico)';
            return $w['to'] . $mark . $oneway;
        }, $look['warps']);
        $l[] = '  Warp a   : ' . (implode(' - ', $warps) ?: '(nessuno)');
        $l[] = str_repeat('-', 56);
        if (self::hasUnknown($look['warps'])) {
            $l[] = '  ( * = settore mai visitato )';
        }
        return implode("\n", $l) . "\n";
    }

    /** @param list<array{to:int,visited:bool,return_known:bool}> $warps */
    private static function hasUnknown(array $warps): bool
    {
        foreach ($warps as $w) {
            if (!$w['visited']) {
                return true;
            }
        }
        return false;
    }

    public static function help(): string
    {
        return implode("\n", [
            'Comandi disponibili:',
            '  L            guarda il settore corrente',
            '  <numero>     muovi verso quel settore (se adiacente)',
            '  M <numero>   muovi verso quel settore',
            '  C <numero>   traccia una rotta verso quel settore',
            '  A <numero>   autopilota verso quel settore',
            '  B <testo>    imposta il faro del settore corrente (B da solo lo cancella)',
            '  V            elenco settori visitati',
            '  P            stato del porto nel settore',
            '  CV <m> <q>   compra veloce  q unita\' di merce m (o/g/e)',
            '  VV <m> <q>   vendi veloce',
            '  T <m> c|v <q>  apri contrattazione (poi: numero=offri, A=accetta, X=lascia)',
            '  BANCA        saldo IGB · DEP <q> deposita · PREL <q> preleva  (allo StarDock)',
            '  Y            catalogo cantiere (StarDock) · BUY <modello> · UPG H|F|S <q> · HW <articolo> [q]',
            '  F            forze nel settore',
            '  DF <q> o|d|t [pedaggio]   dispiega caccia · PF recupera · DM a|l <q> dispiega mine',
            '  ATK <handle|#id> [q]     attacca una nave · BUST [q] assalta il porto',
            '  PL           pianeti nel settore · GEN lancia Genesi · LOADCOL <q> imbarca coloni (StarDock)',
            '  PLAN <id>    stato pianeta · PCIT <id> Citadel+ · PQ <id> Quasar · PGAR <id> +-<q> guarnigione',
            '  PC <id> o|g|e|i +-<q>   coloni (+sbarca / -imbarca) · PR <id> <m> +-<q>   risorse',
            '  PATK <id> [q]  assalta pianeta · PBOMB <id> [q] bombarda',
            '  CORP / CORP NEW <nome> <sigla> <pw> / CORP JOIN <nome> <pw> / CORP LEAVE',
            '  R <txt> radio · RF fedcomm · RC corp · RH hail · RP <handle> <txt> privato · MSGS leggi',
            '  TOP          classifica · ATKN <id> [q] attacca un NPC · RANK statistiche',
            '  LOG          ultime battaglie · REPLAY <id> replay testuale · RLOG cronologia rotte',
            '  NOTE <testo> nota sul settore · NOTE PIN preferito · NOTE DEL rimuovi · FAV lista preferiti',
            '  ACH          traguardi · ALBO stagioni · CONTRACTS bacheca contratti',
            '  BOUNTY <handle> <cr>   metti una taglia · DELIVER <id> consegna un contratto',
            '  BM           mercato nero · BM SELL <m> <q>   piazza merce',
            '  ?            questo aiuto',
        ]) . "\n";
    }

    private static function commodity(string $s): ?string
    {
        return match (strtolower($s)) {
            'o', 'ore', 'min', 'minerale'                 => 'ore',
            'g', 'org', 'organico', 'organics'            => 'organics',
            'e', 'equ', 'equip', 'equipaggiamento', 'equipment' => 'equipment',
            default => null,
        };
    }

    /** @param array<string,mixed> $port */
    public static function port(array $look): string
    {
        if (empty($look['port'])) {
            return "  Nessun porto in questo settore.\n";
        }
        $p = $look['port'];
        $l = [];
        $l[] = sprintf('  %s  (classe %d · %s · tech %d)', $p['name'], $p['class'], $p['code'], $p['tech']);
        foreach ($p['commodities'] as $c) {
            $l[] = sprintf(
                '   %-16s %-11s %8.2f cr/u   scorte %3d%%   (equo %.2f)',
                $c['label'],
                $c['mode'] === 'sell' ? 'VENDE a te' : 'COMPRA da te',
                $c['unit'],
                $c['pct'],
                $c['fair'],
            );
        }
        return implode("\n", $l) . "\n";
    }

    /**
     * Interpreta un comando testuale e restituisce testo + eventuale
     * cambio di settore.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @return array{text:string, player:array<string,mixed>, changed:bool}
     */
    public static function handle(array $player, array $ship, string $raw): array
    {
        $raw = trim($raw);
        $changed = false;

        // Modalita' contrattazione attiva: l'input e' un'offerta / A / X.
        $hg = Haggle::active();
        if ($hg !== null) {
            return self::haggleTurn($player, $ship, $hg, $raw);
        }

        if ($raw === '' || strcasecmp($raw, 'L') === 0 || strcasecmp($raw, 'D') === 0) {
            return ['text' => self::sector(Navigation::look($player)), 'player' => $player, 'changed' => false];
        }
        if ($raw === '?' || strcasecmp($raw, 'H') === 0) {
            return ['text' => self::help(), 'player' => $player, 'changed' => false];
        }

        if (preg_match('/^(\d+)$/', $raw, $m) || preg_match('/^M\s+(\d+)$/i', $raw, $m)) {
            $res = Navigation::move($player, $ship, (int) $m[1]);
            if (!$res['ok']) {
                return ['text' => '  ' . $res['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            return [
                'text' => "  Warp effettuato (costo {$res['cost']} TL).\n\n" . self::sector($res['sector']),
                'player' => $res['player'],
                'changed' => true,
            ];
        }

        if (preg_match('/^C\s+(\d+)$/i', $raw, $m)) {
            $plot = Navigation::plotCourse($player, (int) $m[1], true, (int) ($ship['turns_per_warp'] ?? 1));
            if (!$plot['ok']) {
                return ['text' => '  ' . $plot['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            return [
                'text' => sprintf(
                    "  Rotta: %s\n  %d warp, ~%d TL.  ( A %d  per l'autopilota )\n",
                    implode(' > ', $plot['path']),
                    $plot['hops'],
                    $plot['turns'],
                    (int) $m[1],
                ),
                'player' => $player,
                'changed' => false,
            ];
        }

        if (preg_match('/^A\s+(\d+)$/i', $raw, $m)) {
            $res = Navigation::autopilot($player, $ship, (int) $m[1], true);
            if (!$res['ok']) {
                return ['text' => '  ' . ($res['error'] ?? 'Rotta non disponibile.') . "\n", 'player' => $player, 'changed' => false];
            }
            $reason = [
                'arrived'  => 'arrivato a destinazione',
                'no_turns' => 'turni esauriti',
                'no_warp'  => 'warp mancante',
                'max_hops' => 'limite salti raggiunto',
            ][$res['stopped']] ?? $res['stopped'];
            $line = $res['moved'] === []
                ? "  Autopilota: nessun movimento ({$reason}).\n"
                : sprintf("  Autopilota: %s.  (%s)\n", implode(' > ', $res['moved']), $reason);
            return [
                'text' => $line . "\n" . self::sector($res['sector']),
                'player' => $res['player'],
                'changed' => $res['moved'] !== [],
            ];
        }

        if (preg_match('/^B(\s+(.*))?$/i', $raw, $m)) {
            $res = Navigation::setBeacon($player, $m[2] ?? '');
            return [
                'text' => $res['beacon'] === null ? "  Faro rimosso.\n" : "  Faro impostato: \"{$res['beacon']}\"\n",
                'player' => $player,
                'changed' => false,
            ];
        }

        if (strcasecmp($raw, 'V') === 0) {
            $rows = \App\Core\Database::all(
                'SELECT sector_id FROM player_visited_sectors WHERE player_id = ? ORDER BY sector_id',
                [(int) $player['id']]
            );
            $ids = array_map(static fn ($r) => (int) $r['sector_id'], $rows);
            return [
                'text' => '  Settori visitati (' . count($ids) . '): ' . implode(', ', $ids) . "\n",
                'player' => $player,
                'changed' => false,
            ];
        }

        if (strcasecmp($raw, 'P') === 0) {
            return ['text' => self::port(Navigation::look($player)), 'player' => $player, 'changed' => false];
        }

        if (preg_match('/^(CV|VV)\s+(\S+)\s+(\d+)$/i', $raw, $m)) {
            $c = self::commodity($m[2]);
            if ($c === null) {
                return ['text' => "  Merce sconosciuta (usa o / g / e).\n", 'player' => $player, 'changed' => false];
            }
            $action = strcasecmp($m[1], 'CV') === 0 ? 'buy' : 'sell';
            $res = Economy::settle($player, $ship, (int) $player['sector_id'], $c, $action, (int) $m[3], null, 0);
            if (!$res['ok']) {
                return ['text' => '  ' . $res['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            return [
                'text' => sprintf(
                    "  %s %d x %s per %s cr (%.2f cr/u).\n",
                    $action === 'buy' ? 'Comprate' : 'Vendute',
                    (int) $m[3], Economy::label($c),
                    number_format($res['total'], 0, ',', '.'), $res['unit']
                ),
                'player' => $res['player'],
                'changed' => false,
            ];
        }

        if (preg_match('/^T\s+(\S+)\s+([cv])\s+(\d+)$/i', $raw, $m)) {
            $c = self::commodity($m[1]);
            if ($c === null) {
                return ['text' => "  Merce sconosciuta (usa o / g / e).\n", 'player' => $player, 'changed' => false];
            }
            $action = strcasecmp($m[2], 'c') === 0 ? 'buy' : 'sell';
            $open = Haggle::open($player, $ship, $c, $action, (int) $m[3]);
            if (!$open['ok']) {
                return ['text' => '  ' . $open['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            return ['text' => self::haggleLine($open, $c) . "\n  (numero = offri · A = accetta · X = lascia)\n",
                'player' => $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'BANCA') === 0) {
            $acct = Bank::account((int) $player['id']);
            return [
                'text' => sprintf(
                    "  Banca IGB: saldo %s cr · a bordo %s cr%s\n",
                    number_format($acct['balance'], 0, ',', '.'),
                    number_format((int) $player['credits'], 0, ',', '.'),
                    Bank::atBank((int) $player['sector_id']) ? '' : '  (operabile solo allo StarDock)'
                ),
                'player' => $player,
                'changed' => false,
            ];
        }
        if (preg_match('/^(DEP|PREL)\s+(\d+)$/i', $raw, $m)) {
            $res = strcasecmp($m[1], 'DEP') === 0
                ? Bank::deposit($player, (int) $m[2])
                : Bank::withdraw($player, (int) $m[2]);
            if (!$res['ok']) {
                return ['text' => '  ' . $res['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            $player['credits'] = $res['credits'];
            return [
                'text' => sprintf(
                    "  OK. Saldo banca %s cr · a bordo %s cr.\n",
                    number_format($res['balance'], 0, ',', '.'),
                    number_format($res['credits'], 0, ',', '.')
                ),
                'player' => $player,
                'changed' => false,
            ];
        }

        if (strcasecmp($raw, 'RANK') === 0) {
            return [
                'text' => sprintf(
                    "  %s — grado: %s (%d exp) — allineamento: %s (%d)\n  kill: %d · morti: %d · warp: %d · taglia: %s cr%s\n",
                    $player['handle'], Ranks::title((int) $player['experience']), (int) $player['experience'],
                    Ranks::alignmentLabel((int) $player['alignment']), (int) $player['alignment'],
                    (int) ($player['kills'] ?? 0), (int) ($player['deaths'] ?? 0), (int) $player['total_warps'],
                    number_format((int) ($player['bounty'] ?? 0), 0, ',', '.'),
                    Ranks::isProtected($player) ? ' · PROTEZIONE NOVIZIO attiva' : ''
                ),
                'player' => $player, 'changed' => false,
            ];
        }

        if (strcasecmp($raw, 'F') === 0 || strcasecmp($raw, 'SCAN') === 0) {
            $f = Deploy::forces((int) $player['sector_id'], (int) $player['id'], ($ship['dev_scanner'] ?? 'none') !== 'none');
            $l = ['  Forze nel settore:'];
            foreach (Npc::inSector((int) $player['sector_id']) as $n) {
                $l[] = sprintf('   NPC #%d [%s] %s — %s caccia', $n['id'], $n['kind'], $n['name'], number_format($n['fighters'], 0, ',', '.'));
            }
            foreach ($f['fighters'] as $g) {
                $l[] = sprintf('   %s caccia [%s]%s di %s', number_format($g['qty'], 0, ',', '.'), $g['mode'],
                    $g['mode'] === 'toll' ? ' pedaggio ' . $g['toll'] : '', $g['mine'] ? 'te' : $g['handle']);
            }
            foreach ($f['mines'] as $m) {
                $l[] = sprintf('   %d mine %s di %s', $m['qty'], $m['type'], $m['mine'] ? 'te' : ('#' . $m['owner_id']));
            }
            if (count($l) === 1) {
                $l[] = '   (nessuna)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'TOP') === 0) {
            $l = ['  Classifica comandanti:'];
            foreach (array_slice(Leaderboard::topPlayers(10), 0, 10) as $i => $r) {
                $l[] = sprintf('   %2d. %-20s rating %s  (exp %s, kill %d)', $i + 1, $r['handle'],
                    number_format($r['rating'], 0, ',', '.'), number_format($r['experience'], 0, ',', '.'), $r['kills']);
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'MSGS') === 0 || strcasecmp($raw, 'MAIL') === 0) {
            $l = ['  Radio:'];
            foreach (array_slice(Radio::inbox($player, 15), 0, 15) as $m) {
                $l[] = sprintf('   [%s] %s: %s', strtoupper($m['channel']), $m['from'], $m['body']);
            }
            Radio::markRead($player);
            if (count($l) === 1) {
                $l[] = '   (nessun messaggio)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^R([FCH])\s+(.+)$/i', $raw, $m)) {
            $ch = ['f' => 'fedcomm', 'c' => 'corp', 'h' => 'hail'][strtolower($m[1])];
            $r = Radio::send($player, $ch, $m[2]);
            return ['text' => '  ' . ($r['ok'] ? "Trasmesso su {$ch}." : $r['error']) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^RP\s+(\S+)\s+(.+)$/i', $raw, $m)) {
            $r = Radio::send($player, 'private', $m[2], $m[1]);
            return ['text' => '  ' . ($r['ok'] ? "Messaggio a {$m[1]} inviato." : $r['error']) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^R\s+(.+)$/i', $raw, $m)) {
            $r = Radio::send($player, 'radio', $m[1]);
            return ['text' => '  ' . ($r['ok'] ? 'Trasmesso.' : $r['error']) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^ATKN\s+(\d+)(?:\s+(\d+))?$/i', $raw, $m)) {
            $r = Combat::attackNpc($player, $ship, (int) $m[1], (int) ($m[2] ?? 0));
            if (empty($r['ok'])) {
                return ['text' => '  ' . $r['error'] . "\n", 'player' => $player, 'changed' => false];
            }
            $t = $r['killed']
                ? "{$r['npc_name']} distrutto ({$r['rounds']} round). Bottino " . number_format($r['loot'], 0, ',', '.') . " cr, +{$r['exp']} exp."
                : ($r['destroyed_self']
                    ? "{$r['npc_name']} ti ha distrutto. Capsula allo StarDock."
                    : "Scontro con {$r['npc_name']} ({$r['rounds']} round): persi {$r['attacker_lost']}, inflitti -{$r['defender_lost']}.");
            return ['text' => '  ' . $t . "\n", 'player' => $r['player'] ?? $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'Y') === 0) {
            if (!Shipyard::atShipyard((int) $player['sector_id'])) {
                return ['text' => "  Il cantiere e' solo allo StarDock.\n", 'player' => $player, 'changed' => false];
            }
            $l = ['  Cantiere (permuta ' . number_format(Shipyard::tradeInValue($ship), 0, ',', '.') . ' cr):'];
            foreach (Shipyard::catalog() as $t) {
                $net = max(0, (int) $t['base_cost'] - Shipyard::tradeInValue($ship));
                $l[] = sprintf('   %-14s %-20s stive %d-%d  netto %s cr%s',
                    $t['ckey'], $t['name'], (int) $t['base_holds'], (int) $t['max_holds'],
                    number_format($net, 0, ',', '.'), $t['ckey'] === $ship['type_key'] ? '  <- attuale' : '');
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^BUY\s+(\S+)$/i', $raw, $m)) {
            $r = Shipyard::buyShip($player, $ship, strtolower($m[1]));
            return ['text' => '  ' . ($r['ok'] ? "Nuova nave: {$r['name']} (costo {$r['cost']} cr)." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^UPG\s+([HFS])\s+(\d+)$/i', $raw, $m)) {
            $kind = ['h' => 'holds', 'f' => 'fighters', 's' => 'shields'][strtolower($m[1])];
            $r = Shipyard::upgrade($player, $ship, $kind, (int) $m[2]);
            return ['text' => '  ' . ($r['ok'] ? "+{$r['qty']} {$kind} per {$r['cost']} cr." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^HW\s+(\S+)(?:\s+(\d+))?$/i', $raw, $m)) {
            $r = Shipyard::buyHardware($player, $ship, strtolower($m[1]), (int) ($m[2] ?? 1));
            return ['text' => '  ' . ($r['ok'] ? 'Acquistato: ' . strtolower($m[1]) . (isset($r['qty']) ? " x{$r['qty']}" : '') . " ({$r['cost']} cr)." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }

        if (preg_match('/^DF\s+(\d+)\s+([odt])(?:\s+(\d+))?$/i', $raw, $m)) {
            $mode = ['o' => 'offensive', 'd' => 'defensive', 't' => 'toll'][strtolower($m[2])];
            $r = Deploy::deployFighters($player, $ship, (int) $m[1], $mode, (int) ($m[3] ?? 0));
            return ['text' => '  ' . ($r['ok'] ? "Dispiegati {$r['deployed']} caccia ({$mode})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'PF') === 0) {
            $r = Deploy::pullFighters($player, $ship);
            return ['text' => '  ' . ($r['ok'] ? "Recuperati {$r['recovered']} caccia." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^DM\s+([al])\s+(\d+)$/i', $raw, $m)) {
            $type = strtolower($m[1]) === 'a' ? 'armid' : 'limpet';
            $r = Deploy::deployMines($player, $ship, $type, (int) $m[2]);
            return ['text' => '  ' . ($r['ok'] ? "Dispiegate {$r['deployed']} mine {$type}." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }

        if (preg_match('/^ATK\s+(#\d+|\S+)(?:\s+(\d+))?$/i', $raw, $m)) {
            $target = $m[1];
            if ($target[0] === '#') {
                $tid = (int) substr($target, 1);
            } else {
                $row = \App\Core\Database::first(
                    'SELECT id FROM players WHERE handle = ? AND sector_id = ?',
                    [$target, (int) $player['sector_id']]
                );
                $tid = $row['id'] ?? 0;
            }
            $r = Combat::attackShip($player, $ship, (int) $tid, (int) ($m[2] ?? 0));
            return ['text' => '  ' . self::combatText($r) . "\n", 'player' => $r['player'] ?? $player, 'changed' => false];
        }
        if (preg_match('/^BUST(?:\s+(\d+))?$/i', $raw, $m)) {
            $r = Combat::attackPort($player, $ship, (int) ($m[1] ?? 0));
            return ['text' => '  ' . self::combatText($r) . "\n", 'player' => $r['player'] ?? $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'PL') === 0) {
            $pls = Planets::inSector((int) $player['sector_id']);
            if ($pls === []) {
                return ['text' => "  Nessun pianeta in questo settore.\n", 'player' => $player, 'changed' => false];
            }
            $l = ['  Pianeti:'];
            foreach ($pls as $p) {
                $l[] = sprintf('   #%d %-14s tipo %s  Citadel %d  Quasar %d  %s',
                    $p['id'], $p['name'], $p['type_key'], $p['citadel_level'], $p['quasar_level'],
                    Planets::isOwn($p, $player) ? 'TUO' : ($p['owner_handle'] ?? 'disabitato'));
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'GEN') === 0) {
            $r = Planets::genesis($player, $ship);
            return ['text' => '  ' . ($r['ok'] ? "Genesi: pianeta {$r['name']} (tipo {$r['type']})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^LOADCOL\s+(\d+)$/i', $raw, $m)) {
            $r = Planets::pickupColonists($player, $ship, (int) $m[1]);
            return ['text' => '  ' . ($r['ok'] ? "Imbarcati {$r['loaded']} coloni (residuo oggi {$r['remaining_today']})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PLAN\s+(\d+)$/i', $raw, $m)) {
            $p = Planets::get((int) $m[1]);
            if ($p === null) {
                return ['text' => "  Pianeta inesistente.\n", 'player' => $player, 'changed' => false];
            }
            $tot = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
            return ['text' => sprintf(
                "  #%d %s — tipo %s (%s)\n   coloni %s/%s [O %s / G %s / E %s / idle %s]\n   magazzino O %s  G %s  E %s\n   Citadel %d%s  Quasar %d  guarnigione %s  tesoreria %s cr\n",
                $p['id'], $p['name'], $p['type_key'], $p['type_name'],
                number_format($tot,0,',','.'), number_format((int)$p['max_col'],0,',','.'),
                number_format((int)$p['col_ore'],0,',','.'), number_format((int)$p['col_org'],0,',','.'),
                number_format((int)$p['col_equ'],0,',','.'), number_format((int)$p['col_idle'],0,',','.'),
                number_format((int)$p['stock_ore'],0,',','.'), number_format((int)$p['stock_org'],0,',','.'), number_format((int)$p['stock_equ'],0,',','.'),
                (int)$p['citadel_level'], $p['citadel_upgrade_to'] ? ' -> '.(int)$p['citadel_upgrade_to'] : '',
                (int)$p['quasar_level'], number_format((int)$p['fighters'],0,',','.'), number_format((int)$p['credits'],0,',','.')
            ), 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PCIT\s+(\d+)$/i', $raw, $m)) {
            $r = Planets::upgradeCitadel($player, (int) $m[1]);
            return ['text' => '  ' . ($r['ok'] ? "Citadel liv. {$r['level']} in costruzione (~{$r['hours']}h)." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PQ\s+(\d+)$/i', $raw, $m)) {
            $r = Planets::buildQuasar($player, (int) $m[1]);
            return ['text' => '  ' . ($r['ok'] ? "Quasar liv. {$r['quasar_level']}." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PGAR\s+(\d+)\s+([+-]\d+)$/i', $raw, $m)) {
            $q = (int) $m[2];
            $r = Planets::garrison($player, $ship, (int) $m[1], abs($q), $q >= 0 ? 'garrison' : 'recall');
            return ['text' => '  ' . ($r['ok'] ? "Guarnigione: {$r['moved']} caccia ({$r['dir']})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PC\s+(\d+)\s+([ogei])\s+([+-]\d+)$/i', $raw, $m)) {
            $bucket = ['o' => 'ore', 'g' => 'org', 'e' => 'equ', 'i' => 'idle'][strtolower($m[2])];
            $q = (int) $m[3];
            $r = Planets::moveColonists($player, $ship, (int) $m[1], $bucket, abs($q), $q >= 0 ? 'down' : 'up');
            return ['text' => '  ' . ($r['ok'] ? "Coloni: {$r['moved']} ({$r['dir']} {$bucket})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^PR\s+(\d+)\s+(\S+)\s+([+-]\d+)$/i', $raw, $m)) {
            $c = self::commodity($m[2]);
            if ($c === null) {
                return ['text' => "  Merce sconosciuta.\n", 'player' => $player, 'changed' => false];
            }
            $q = (int) $m[3];
            $r = Planets::moveResources($player, $ship, (int) $m[1], $c, abs($q), $q >= 0 ? 'load' : 'unload');
            return ['text' => '  ' . ($r['ok'] ? "Risorse: {$r['moved']} " . Economy::label($c) . '.' : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^(PATK|PBOMB)\s+(\d+)(?:\s+(\d+))?$/i', $raw, $m)) {
            $r = Combat::attackPlanet($player, $ship, (int) $m[2], (int) ($m[3] ?? 0), strcasecmp($m[1], 'PBOMB') === 0);
            return ['text' => '  ' . self::planetCombatText($r) . "\n", 'player' => $r['player'] ?? $player, 'changed' => false];
        }

        if (preg_match('/^CORP(\s+(.*))?$/i', $raw, $m)) {
            $arg = trim($m[2] ?? '');
            if ($arg === '') {
                $c = Corp::of((int) $player['id']);
                if ($c === null) {
                    return ['text' => "  Non sei in una corporazione. CORP NEW <nome> <sigla> <pw> per fondarne una.\n", 'player' => $player, 'changed' => false];
                }
                $mem = Corp::members((int) $c['id']);
                return ['text' => sprintf("  %s [%s] — %d membri — cassa %s cr — ruolo %s\n",
                    $c['name'], $c['tag'], count($mem), number_format((int) $c['treasury'], 0, ',', '.'), $c['role']),
                    'player' => $player, 'changed' => false];
            }
            if (preg_match('/^NEW\s+(.+?)\s+(\S{2,6})\s+(\S+)$/i', $arg, $mm)) {
                $r = Corp::create($player, $mm[1], $mm[2], $mm[3]);
                return ['text' => '  ' . ($r['ok'] ? "Corporazione fondata: {$r['corp']['name']} [{$r['corp']['tag']}]." : $r['error']) . "\n",
                    'player' => $player, 'changed' => false];
            }
            if (preg_match('/^JOIN\s+(.+?)\s+(\S+)$/i', $arg, $mm)) {
                $r = Corp::join($player, $mm[1], $mm[2]);
                return ['text' => '  ' . ($r['ok'] ? "Sei entrato in {$r['corp']['name']}." : $r['error']) . "\n",
                    'player' => $player, 'changed' => false];
            }
            if (strcasecmp($arg, 'LEAVE') === 0) {
                $r = Corp::leave($player);
                return ['text' => '  ' . ($r['ok'] ? ($r['disbanded'] ? 'Corporazione sciolta.' : 'Hai lasciato la corporazione.') : $r['error']) . "\n",
                    'player' => $player, 'changed' => false];
            }
            return ['text' => "  CORP · CORP NEW <nome> <sigla> <pw> · CORP JOIN <nome> <pw> · CORP LEAVE\n", 'player' => $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'LOG') === 0) {
            $rows = BattleLog::forPlayer((int) $player['id'], 12);
            $l = ['  Ultime battaglie:'];
            foreach ($rows as $r) {
                $l[] = sprintf('   #%-4d %-8s vs %-16s %-14s r%d %s', $r['id'], $r['role'], mb_substr($r['opponent'], 0, 16),
                    $r['outcome'], $r['rounds'], $r['replayable'] ? '(REPLAY ' . $r['id'] . ')' : '');
            }
            if (count($l) === 1) {
                $l[] = '   (nessuna)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^REPLAY\s+(\d+)$/i', $raw, $m)) {
            $b = BattleLog::get((int) $m[1], (int) $player['id']);
            if ($b === null) {
                return ['text' => "  Battaglia non trovata o non tua.\n", 'player' => $player, 'changed' => false];
            }
            if ($b['trace'] === []) {
                return ['text' => "  Nessun dettaglio round-per-round per questa battaglia.\n", 'player' => $player, 'changed' => false];
            }
            $l = [sprintf('  #%d  %s vs %s  (%s, %d round)', $b['id'], $b['attacker'], $b['defender'], $b['outcome'], $b['rounds'])];
            foreach ($b['trace'] as $t) {
                $l[] = sprintf('   R%-2d  att %s/%s scudi   def %s/%s scudi%s',
                    $t['r'],
                    number_format((int) $t['aF'], 0, ',', '.'), number_format((int) $t['aS'], 0, ',', '.'),
                    number_format((int) $t['dF'], 0, ',', '.'), number_format((int) $t['dS'], 0, ',', '.'),
                    isset($t['dHit']) ? sprintf('   (-%s / -%s)', number_format((int) $t['dHit'], 0, ',', '.'), number_format((int) ($t['aHit'] ?? 0), 0, ',', '.')) : '');
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'RLOG') === 0 || strcasecmp($raw, 'ROUTES') === 0) {
            $l = ['  Ultimi spostamenti:'];
            foreach (RouteLog::recent((int) $player['id'], 15) as $r) {
                $l[] = sprintf('   %d -> %d  %s  %dTL  %s', $r['from'], $r['to'], $r['mode'], $r['turns'], $r['at']);
            }
            if (count($l) === 1) {
                $l[] = '   (nessuno)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'FAV') === 0) {
            $p = SectorNotes::pinned((int) $player['id']);
            $l = ['  Preferiti:'];
            foreach ($p as $f) {
                $l[] = sprintf('   #%d  %s', $f['sector'], $f['label'] ?? $f['name']);
            }
            if (count($l) === 1) {
                $l[] = '   (nessuno)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^NOTE(\s+(.*))?$/i', $raw, $m)) {
            $arg = trim($m[2] ?? '');
            $sid = (int) $player['sector_id'];
            $cur = SectorNotes::get((int) $player['id'], $sid) ?? [];
            if ($arg === '' || strcasecmp($arg, 'SHOW') === 0) {
                return ['text' => sprintf("  Nota settore %d: %s%s %s\n", $sid,
                    !empty($cur['pinned']) ? '[preferito] ' : '', $cur['label'] ?? '(nessuna etichetta)', $cur['note'] ?? ''),
                    'player' => $player, 'changed' => false];
            }
            if (strcasecmp($arg, 'DEL') === 0) {
                SectorNotes::remove((int) $player['id'], $sid);
                return ['text' => "  Nota rimossa.\n", 'player' => $player, 'changed' => false];
            }
            if (strcasecmp($arg, 'PIN') === 0) {
                SectorNotes::set((int) $player['id'], $sid, (string) ($cur['label'] ?? ''), (string) ($cur['note'] ?? ''), empty($cur['pinned']));
                return ['text' => '  ' . (empty($cur['pinned']) ? 'Aggiunto ai preferiti.' : 'Rimosso dai preferiti.') . "\n", 'player' => $player, 'changed' => false];
            }
            SectorNotes::set((int) $player['id'], $sid, mb_substr($arg, 0, 32), (string) ($cur['note'] ?? ''), !empty($cur['pinned']));
            return ['text' => "  Nota salvata.\n", 'player' => $player, 'changed' => false];
        }

        if (strcasecmp($raw, 'ACH') === 0) {
            $earned = Achievements::earned((int) $player['id']);
            $l = ['  Traguardi (' . count($earned) . '/' . count(Achievements::all()) . ', ' . Achievements::points((int) $player['id']) . ' pt):'];
            foreach (Achievements::all() as $a) {
                $l[] = sprintf('   [%s] %-22s %s', isset($earned[$a['ckey']]) ? 'X' : ' ', $a['name'], $a['descr']);
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'ALBO') === 0) {
            $cur = Season::current();
            $l = ['  Stagione in corso: #' . $cur['number'] . ' ' . $cur['name']];
            foreach (Season::hall() as $h) {
                $l[] = sprintf('   #%d %-14s vince %s', $h['number'], $h['name'], $h['winner'] ?? '-');
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'CONTRACTS') === 0) {
            $l = ['  Bacheca contratti:'];
            foreach (Contracts::board((int) $player['id'], 15) as $c) {
                $d = $c['kind'] === 'bounty'
                    ? 'taglia su ' . ($c['target'] ?? '?')
                    : $c['qty'] . ' ' . Economy::label((string) $c['commodity']) . ' -> settore ' . $c['sector_id'];
                $l[] = sprintf('   #%-4d %-8s %s  (%s cr) da %s', $c['id'], $c['kind'], $d,
                    number_format((int) $c['reward'], 0, ',', '.'), $c['issuer']);
            }
            if (count($l) === 1) {
                $l[] = '   (nessuno)';
            }
            return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^BOUNTY\s+(\S+)\s+(\d+)$/i', $raw, $m)) {
            $r = Contracts::open($player, 'bounty', ['target' => $m[1], 'reward' => (int) $m[2]]);
            return ['text' => '  ' . ($r['ok'] ? "Taglia pubblicata (#{$r['id']})." : $r['error']) . "\n", 'player' => $player, 'changed' => false];
        }
        if (preg_match('/^DELIVER\s+(\d+)$/i', $raw, $m)) {
            $r = Contracts::deliver($player, $ship, (int) $m[1]);
            return ['text' => '  ' . ($r['ok'] ? 'Consegna completata: +' . number_format($r['reward'], 0, ',', '.') . ' cr.' : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }
        if (preg_match('/^BM(\s+SELL\s+(\S+)\s+(\d+))?$/i', $raw, $m)) {
            if (empty($m[1])) {
                $l = ['  Mercato nero:'];
                foreach (BlackMarket::catalog() as $c) {
                    $l[] = sprintf('   %-8s %-16s %s cr', $c['item'], $c['name'], number_format($c['price'], 0, ',', '.'));
                }
                $l[] = '  BM SELL <o|g|e> <q> per piazzare merce (costa allineamento).';
                return ['text' => implode("\n", $l) . "\n", 'player' => $player, 'changed' => false];
            }
            $c = self::commodity($m[2]);
            if ($c === null) {
                return ['text' => "  Merce sconosciuta.\n", 'player' => $player, 'changed' => false];
            }
            $r = BlackMarket::sell($player, $ship, $c, (int) $m[3]);
            return ['text' => '  ' . ($r['ok'] ? 'Piazzati per ' . number_format($r['total'], 0, ',', '.') . " cr (align {$r['align']})." : $r['error']) . "\n",
                'player' => $player, 'changed' => false];
        }

        return ['text' => "  Comando non riconosciuto. Premi ? per l'aiuto.\n", 'player' => $player, 'changed' => false];
    }

    private static function planetCombatText(array $r): string
    {
        if (empty($r['ok'])) {
            return $r['error'] ?? 'Azione non riuscita.';
        }
        if (!empty($r['destroyed_self'])) {
            return "Le difese di {$r['planet_name']} ti hanno distrutto. Capsula allo StarDock.";
        }
        if (!empty($r['cracked'])) {
            $st = [];
            foreach (($r['stolen'] ?? []) as $c => $q) {
                $st[] = "{$q} " . Economy::label($c);
            }
            return "Difese di {$r['planet_name']} annientate ({$r['rounds']} round). Bottino "
                . number_format($r['loot'], 0, ',', '.') . ' cr' . ($st ? ' + ' . implode(', ', $st) : '')
                . (!empty($r['bombarded']) ? " · {$r['bomb_killed']} coloni sterminati" : '') . '.';
        }
        return "Assalto a {$r['planet_name']} respinto ({$r['rounds']} round).";
    }

    private static function combatText(array $r): string
    {
        if (empty($r['ok'])) {
            return $r['error'] ?? 'Azione non riuscita.';
        }
        if (!empty($r['destroyed_target'])) {
            return "{$r['target_handle']} DISTRUTTO in {$r['rounds']} round. Bottino "
                . number_format($r['loot'], 0, ',', '.') . " cr, +{$r['exp']} exp.";
        }
        if (!empty($r['bust'])) {
            $st = [];
            foreach (($r['stolen'] ?? []) as $c => $q) {
                $st[] = "{$q} " . Economy::label($c);
            }
            return "PORTO ESPUGNATO ({$r['rounds']} round). Bottino " . number_format($r['loot'], 0, ',', '.') . ' cr'
                . ($st ? ' + ' . implode(', ', $st) : '') . '. Allineamento in picchiata.';
        }
        if (!empty($r['destroyed_self'])) {
            return 'La tua nave e\' stata distrutta. Capsula allo StarDock.';
        }
        return "Scontro ({$r['rounds']} round): persi {$r['attacker_lost']} caccia, inflitti -{$r['defender_lost']}.";
    }

    /** @param array<string,mixed> $hg */
    private static function haggleTurn(array $player, array $ship, array $hg, string $raw): array
    {
        $c = (string) $hg['commodity'];
        if ($raw === '' || strcasecmp($raw, 'D') === 0) {
            return ['text' => self::haggleLine(self::hgToView($hg), $c) . "\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'X') === 0 || strcasecmp($raw, 'Q') === 0) {
            Haggle::abort();
            return ['text' => "  Trattativa abbandonata.\n", 'player' => $player, 'changed' => false];
        }
        if (strcasecmp($raw, 'A') === 0) {
            $res = Haggle::accept($player, $ship, (string) $hg['token']);
            return self::haggleResult($player, $res, $c);
        }
        if (preg_match('/^(\d+)$/', $raw, $m)) {
            $res = Haggle::counter($player, $ship, (string) $hg['token'], (int) $m[1]);
            return self::haggleResult($player, $res, $c);
        }
        return ['text' => "  In trattativa: digita un numero (offerta), A per accettare, X per lasciare.\n",
            'player' => $player, 'changed' => false];
    }

    /**
     * @param array<string,mixed> $res
     * @return array{text:string, player:array<string,mixed>, changed:bool}
     */
    private static function haggleResult(array $player, array $res, string $c): array
    {
        if (empty($res['ok'])) {
            return ['text' => '  ' . ($res['error'] ?? 'Errore.') . "\n", 'player' => $player, 'changed' => false];
        }
        return match ($res['result']) {
            'accepted' => [
                'text' => sprintf(
                    "  Affare fatto: %d x %s per %s cr (%.2f cr/u, equo %s, %d round).\n",
                    $res['qty'], Economy::label($c),
                    number_format($res['total'], 0, ',', '.'), $res['unit'],
                    number_format($res['fair_total'], 0, ',', '.'), $res['rounds']
                ),
                'player' => $res['player'],
                'changed' => false,
            ],
            'walk' => ['text' => '  ' . $res['message'] . "\n", 'player' => $player, 'changed' => false],
            default => [
                'text' => self::haggleLine($res, $c) . ($res['final'] ? "\n  Offerta FINALE: A per accettare, X per lasciare.\n" : "\n"),
                'player' => $player,
                'changed' => false,
            ],
        };
    }

    /** @param array<string,mixed> $v vista trattativa */
    private static function haggleLine(array $v, string $c): string
    {
        $verb = $v['action'] === 'buy' ? 'chiede' : 'offre';
        return sprintf(
            '  Contratt. %d x %s — round %d/%d — il porto %s %s cr (%.2f cr/u) — equo ~%s cr',
            $v['qty'], Economy::label($c), $v['round'], $v['max_rounds'], $verb,
            number_format($v['port_offer'], 0, ',', '.'), $v['port_unit'],
            number_format($v['fair_total'], 0, ',', '.')
        );
    }

    /** @param array<string,mixed> $hg sessione grezza -> vista */
    private static function hgToView(array $hg): array
    {
        return [
            'action' => $hg['action'], 'qty' => $hg['qty'], 'round' => $hg['round'],
            'max_rounds' => $hg['max_rounds'], 'final' => $hg['final'],
            'port_offer' => $hg['port_offer'],
            'port_unit' => round($hg['port_offer'] / max(1, $hg['qty']), 2),
            'fair_total' => $hg['fair_total'],
        ];
    }
}
