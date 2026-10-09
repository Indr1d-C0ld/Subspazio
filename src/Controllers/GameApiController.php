<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Auth\Auth;
use App\Game\Bank;
use App\Game\BattleLog;
use App\Game\Combat;
use App\Game\Corp;
use App\Game\Ctx;
use App\Game\Deploy;
use App\Game\Economy;
use App\Game\Events;
use App\Game\GameConfig;
use App\Game\Haggle;
use App\Game\Leaderboard;
use App\Game\Live;
use App\Game\Navigation;
use App\Game\Planets;
use App\Game\Radio;
use App\Game\Ranks;
use App\Game\RouteLog;
use App\Game\SectorNotes;
use App\Game\Shipyard;
use App\Game\TurnManager;

/**
 * Endpoint JSON per la plancia.
 */
final class GameApiController
{
    public function state(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::json([
            'player' => self::playerDto($player),
            'ship'   => self::shipDto(Ctx::$ship),
            'sector' => Navigation::look($player, Ctx::$ship),
            'events' => Events::active(),
            'unread' => Radio::unread($player),
        ]);
    }

    public function map(Request $request): Response
    {
        return Response::json(Navigation::mapData(TurnManager::sync(Ctx::$player)));
    }

    public function sector(Request $request, string $id): Response
    {
        $sid = (int) $id;
        $player = Ctx::$player;
        $visited = Database::first(
            'SELECT 1 AS x FROM player_visited_sectors WHERE player_id = ? AND sector_id = ?',
            [(int) $player['id'], $sid]
        );
        if ($visited === null && $sid !== (int) $player['sector_id']) {
            return Response::json(['ok' => false, 'error' => 'Settore non ancora esplorato.'], 404);
        }
        $probe = $player;
        $probe['sector_id'] = $sid;
        $vista = Navigation::look($probe, Ctx::$ship);
        // Un settore lontano si ricorda, non si osserva. Prima questa chiamata
        // restituiva il contenuto VIVO di qualunque settore gia' visitato — chi
        // c'e' adesso, forze schierate, NPC, difese dei pianeti — senza sonde,
        // senza turni, da ovunque: bastava ripeterla per sapere dove fossero
        // tutti. Restano le informazioni di mappa; il resto si vede andandoci o
        // mandando una sonda.
        if ($sid !== (int) $player['sector_id']) {
            $vista['players_here'] = [];
            $vista['forces'] = [];
            $vista['npcs'] = [];
            $vista['can_attack'] = false;
            $vista['remote'] = true;
            $vista['planets'] = array_map(static fn (array $pl): array => array_diff_key($pl, ['citadel' => 1, 'quasar' => 1]), (array) ($vista['planets'] ?? []));
        }
        return Response::json(['ok' => true, 'sector' => $vista]);
    }

    public function move(Request $request): Response
    {
        $res = Navigation::move(Ctx::$player, Ctx::$ship, $request->int('to'));
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['error'], 'code' => $res['code'] ?? null], 422);
        }
        return Response::json([
            'ok'           => true,
            'cost'         => $res['cost'],
            'entry_events' => $res['entry_events'] ?? [],
            'destroyed'    => $res['destroyed'] ?? false,
            'player'       => self::playerDto($res['player']),
            'sector'       => $res['sector'],
        ]);
    }

    public function courseApi(Request $request): Response
    {
        $known = $request->int('known', 1) === 1;
        $res = Navigation::plotCourse(
            Ctx::$player,
            $request->int('to'),
            $known,
            (int) (Ctx::$ship['turns_per_warp'] ?? 1)
        );
        return Response::json($res, $res['ok'] ? 200 : 422);
    }

    public function autopilotApi(Request $request): Response
    {
        $res = Navigation::autopilot(Ctx::$player, Ctx::$ship, $request->int('to'), true);
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['error'] ?? 'Rotta non disponibile.'], 422);
        }
        return Response::json([
            'ok'      => true,
            'moved'   => $res['moved'],
            'stopped' => $res['stopped'],
            'events'  => $res['events'] ?? [],
            'player'  => self::playerDto($res['player']),
            'sector'  => $res['sector'],
        ]);
    }

    public function beaconApi(Request $request): Response
    {
        $res = Navigation::setBeacon(Ctx::$player, $request->str('text'));
        return Response::json($res);
    }

    // --- porto / contrattazione -----------------------------------------

    public function port(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        $ship = Ctx::$ship;
        $port = Economy::portAt((int) $player['sector_id']);
        if ($port === null) {
            return Response::json(['ok' => false, 'error' => 'Nessun porto in questo settore.'], 404);
        }

        $rows = [];
        foreach (Economy::COMMODITIES as $c) {
            $pf = Economy::prefix($c);
            $action = $port["{$pf}_mode"] === 'sell' ? 'buy' : 'sell';
            $max = Economy::maxQty($port, $player, $ship, $c, $action);
            $q = Economy::quote($port, $c, $action, max(1, $max));
            $rows[$c] = [
                'mode'   => $port["{$pf}_mode"],
                'action' => $action,
                'unit'   => $q['unit'],
                'fair'   => $q['fair'],
                'max'    => $max,
                'stock'  => (int) $port["{$pf}_stock"],
                'capacity' => (int) $port["{$pf}_capacity"],
                'cargo'  => (int) $ship[Economy::shipColumn($c)],
            ];
        }

        return Response::json([
            'ok'     => true,
            'port'   => Economy::portSummary($port),
            'rows'   => $rows,
            'player' => self::playerDto($player),
            'ship'   => self::shipDto($ship),
            'haggle' => Haggle::active() ? Haggle::active()['token'] : null,
        ]);
    }

    public function quickTrade(Request $request): Response
    {
        $res = Economy::settle(
            Ctx::$player,
            Ctx::$ship,
            (int) Ctx::$player['sector_id'],
            $request->str('commodity'),
            $request->str('action'),
            $request->int('qty'),
            null,
            0,
        );
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['error']], 422);
        }
        return Response::json([
            'ok'     => true,
            'total'  => $res['total'],
            'unit'   => $res['unit'],
            'player' => self::playerDto($res['player']),
            'ship'   => self::shipDto($res['ship']),
        ]);
    }

    public function haggleOpen(Request $request): Response
    {
        $res = Haggle::open(
            Ctx::$player,
            Ctx::$ship,
            $request->str('commodity'),
            $request->str('action'),
            $request->int('qty'),
        );
        return Response::json($res, $res['ok'] ? 200 : 422);
    }

    public function haggleCounter(Request $request): Response
    {
        $res = Haggle::counter(Ctx::$player, Ctx::$ship, $request->str('token'), $request->int('offer'));
        if (!empty($res['ok']) && ($res['result'] ?? '') === 'accepted') {
            $res['player'] = self::playerDto($res['player']);
            $res['ship'] = self::shipDto($res['ship']);
        }
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function haggleAccept(Request $request): Response
    {
        $res = Haggle::accept(Ctx::$player, Ctx::$ship, $request->str('token'));
        if (!empty($res['ok'])) {
            $res['player'] = self::playerDto($res['player']);
            $res['ship'] = self::shipDto($res['ship']);
        }
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function haggleAbort(Request $request): Response
    {
        Haggle::abort();
        return Response::json(['ok' => true]);
    }

    public function bank(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        if ($request->isPost()) {
            $dir = $request->str('action') === 'withdraw' ? 'withdraw' : 'deposit';
            $res = $dir === 'deposit'
                ? Bank::deposit($player, $request->int('amount'))
                : Bank::withdraw($player, $request->int('amount'));
            return Response::json($res, !empty($res['ok']) ? 200 : 422);
        }
        return Response::json([
            'ok'      => true,
            'account' => Bank::account((int) $player['id']),
            'credits' => (int) $player['credits'],
            'at_bank' => Bank::atBank((int) $player['sector_id']),
        ]);
    }

    // --- DTO ---------------------------------------------------------------

    /** @param array<string,mixed> $p */
    private static function playerDto(array $p): array
    {
        return [
            'handle'      => $p['handle'],
            'credits'     => (int) $p['credits'],
            'turns'       => (int) $p['turns'],
            'turns_max'   => TurnManager::perDay(),
            'experience'  => (int) $p['experience'],
            'rank'        => Ranks::title((int) $p['experience']),
            'alignment'   => (int) $p['alignment'],
            'alignment_label' => Ranks::alignmentLabel((int) $p['alignment']),
            'total_warps' => (int) $p['total_warps'],
            'kills'       => (int) ($p['kills'] ?? 0),
            'deaths'      => (int) ($p['deaths'] ?? 0),
            'bounty'      => (int) ($p['bounty'] ?? 0),
            'protected'   => Ranks::isProtected($p),
            'sector_id'   => (int) $p['sector_id'],
        ];
    }

    /** @param array<string,mixed> $s */
    private static function shipDto(array $s): array
    {
        $used = (int) $s['hold_ore'] + (int) $s['hold_organics']
            + (int) $s['hold_equipment'] + (int) $s['hold_colonists'];
        return [
            'type'        => $s['type_key'] ?? null,
            'type_name'   => $s['type_name'] ?? null,
            'name'        => $s['name'] ?? null,
            'holds_total' => (int) ($s['holds_total'] ?? 0),
            'holds_used'  => $used,
            'cargo'       => [
                'ore'       => (int) ($s['hold_ore'] ?? 0),
                'organics'  => (int) ($s['hold_organics'] ?? 0),
                'equipment' => (int) ($s['hold_equipment'] ?? 0),
                'colonists' => (int) ($s['hold_colonists'] ?? 0),
            ],
            'fighters'    => (int) ($s['fighters'] ?? 0),
            'shields'     => (int) ($s['shields'] ?? 0),
            'combat_rating' => (float) ($s['combat_rating'] ?? 1.0),
            'mines_armid'  => (int) ($s['mines_armid'] ?? 0),
            'mines_limpet' => (int) ($s['mines_limpet'] ?? 0),
            'probes'      => (int) ($s['probes'] ?? 0),
            'escape_pod'  => (bool) ($s['escape_pod'] ?? false),
            'scanner'     => $s['dev_scanner'] ?? 'none',
            'transwarp'   => (bool) (($s['dev_transwarp'] ?? 0) || ($s['can_transwarp'] ?? false)),
            'cloak'       => (bool) ($s['dev_cloak'] ?? false),
            'turns_per_warp' => (int) ($s['turns_per_warp'] ?? 1),
        ];
    }

    // --- combattimento / dispiegamento / cantiere -----------------------

    public function attackShip(Request $request): Response
    {
        $res = Combat::attackShip(Ctx::$player, Ctx::$ship, $request->int('target'), $request->int('fighters'));
        if (empty($res['ok'])) {
            return Response::json(['ok' => false, 'error' => $res['error']], 422);
        }
        $res['player'] = self::playerDto($res['player']);
        $res['ship'] = self::shipDto($res['ship']);
        return Response::json($res);
    }

    public function attackPort(Request $request): Response
    {
        $res = Combat::attackPort(Ctx::$player, Ctx::$ship, $request->int('fighters'));
        if (empty($res['ok'])) {
            return Response::json(['ok' => false, 'error' => $res['error']], 422);
        }
        $res['player'] = self::playerDto($res['player']);
        $res['ship'] = self::shipDto($res['ship']);
        return Response::json($res);
    }

    public function deployFighters(Request $request): Response
    {
        $res = Deploy::deployFighters(Ctx::$player, Ctx::$ship, $request->int('qty'), $request->str('mode'), $request->int('toll'));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function pullFighters(Request $request): Response
    {
        $res = Deploy::pullFighters(Ctx::$player, Ctx::$ship);
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function deployMines(Request $request): Response
    {
        $res = Deploy::deployMines(Ctx::$player, Ctx::$ship, $request->str('type'), $request->int('qty'));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function shipyard(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        return Response::json([
            'ok'       => true,
            'at_dock'  => Shipyard::atShipyard((int) $player['sector_id']),
            'catalog'  => Shipyard::catalog(),
            'trade_in' => Shipyard::tradeInValue(Ctx::$ship),
            'player'   => self::playerDto($player),
            'ship'     => self::shipDto(Ctx::$ship),
        ]);
    }

    public function shipyardBuy(Request $request): Response
    {
        $res = Shipyard::buyShip(Ctx::$player, Ctx::$ship, $request->str('type'));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function shipyardUpgrade(Request $request): Response
    {
        $res = Shipyard::upgrade(Ctx::$player, Ctx::$ship, $request->str('kind'), $request->int('qty'));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function shipyardHardware(Request $request): Response
    {
        $res = Shipyard::buyHardware(Ctx::$player, Ctx::$ship, $request->str('item'), $request->int('qty', 1));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    // --- pianeti / corporazioni ---------------------------------------

    public function planets(Request $request): Response
    {
        return Response::json([
            'ok'      => true,
            'planets' => Navigation::look(Ctx::$player)['planets'],
        ]);
    }

    public function planet(Request $request, string $id): Response
    {
        $p = Planets::get((int) $id);
        if ($p === null || !Planets::visibleTo($p, Ctx::$player)) {
            return Response::json(['ok' => false, 'error' => 'Pianeta non rilevato dai sensori.'], 404);
        }
        return Response::json([
            'ok'   => true,
            'own'  => Planets::isOwn($p, Ctx::$player),
            'next' => Planets::nextCitadel($p),
            'planet' => $p,
        ]);
    }

    public function planetAction(Request $request, string $action, string $id): Response
    {
        $pid = (int) $id;
        $res = match ($action) {
            'coloni'    => Planets::moveColonists(Ctx::$player, Ctx::$ship, $pid, $request->str('bucket'), $request->int('qty'), $request->str('dir')),
            'assegna'   => Planets::assignColonists(Ctx::$player, $pid, $request->str('from'), $request->str('to'), $request->int('qty')),
            'risorse'   => Planets::moveResources(Ctx::$player, Ctx::$ship, $pid, $request->str('commodity'), $request->int('qty'), $request->str('dir')),
            'tesoreria' => Planets::treasury(Ctx::$player, $pid, $request->int('amount'), $request->str('dir')),
            'citadel'   => Planets::upgradeCitadel(Ctx::$player, $pid),
            'quasar'    => Planets::buildQuasar(Ctx::$player, $pid),
            'guarnigione' => Planets::garrison(Ctx::$player, Ctx::$ship, $pid, $request->int('qty'), $request->str('dir')),
            default     => ['ok' => false, 'error' => 'Azione sconosciuta.'],
        };
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function planetGenesis(Request $request): Response
    {
        $res = \App\Game\Planets::genesis(Ctx::$player, Ctx::$ship);
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function planetPickup(Request $request): Response
    {
        $res = Planets::pickupColonists(Ctx::$player, Ctx::$ship, $request->int('qty'));
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function planetAttack(Request $request, string $id): Response
    {
        $res = Combat::attackPlanet(Ctx::$player, Ctx::$ship, (int) $id, $request->int('fighters'), $request->str('bombard') === '1');
        if (empty($res['ok'])) {
            return Response::json(['ok' => false, 'error' => $res['error']], 422);
        }
        $res['player'] = self::playerDto($res['player']);
        $res['ship'] = self::shipDto($res['ship']);
        return Response::json($res);
    }

    public function corp(Request $request): Response
    {
        $corp = Corp::of((int) Ctx::$player['id']);
        return Response::json([
            'ok'      => true,
            'corp'    => $corp,
            'members' => $corp ? Corp::members((int) $corp['id']) : [],
        ]);
    }

    /**
     * Stream SSE degli eventi live. Non ritorna: scrive e termina.
     */
    public function stream(Request $request): Response
    {
        // libera SUBITO il lock di sessione: una SSE lunga bloccherebbe
        // ogni altra richiesta dello stesso utente.
        $pidForStream = (int) (Ctx::$player['id'] ?? 0);
        // Ogni stream occupa un processo del server fino a live.stream_max_s
        // secondi. Senza limite un solo account poteva aprirne a centinaia e
        // lasciare gli altri senza processi. Il vero tetto e' sugli stream
        // aperti (Live::streamSuperato: vince la pagina piu' nuova); questo
        // ferma solo i cicli impazziti. Prima era il solo tetto, a 6 aperture
        // al minuto: ogni pagina ne apre una, e chi navigava svelto restava
        // senza avvisi sulle pagine successive.
        if (!\App\Core\RateLimiter::hit('stream:' . $pidForStream, GameConfig::int('live.stream_opens_per_min', 30), 60)) {
            return Response::json(['ok' => false, 'error' => 'Troppe connessioni in tempo reale aperte.'], 429);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
            @apache_setenv('dont-vary', '1');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ob_implicit_flush(true);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        header('Content-Encoding: none');

        $lastId = (int) ($request->header('Last-Event-ID') ?: $request->int('last', 0));
        // Un cursore oltre l'ultimo evento viene da prima di un azzeramento
        // (fine stagione): tenerlo voleva dire non ricevere piu' nulla.
        if ($lastId <= 0 || $lastId > Live::lastId()) {
            $lastId = Live::lastId();
        }

        // padding per vincere eventuali buffer intermedi
        echo ':' . str_repeat(' ', 2048) . "\n";
        echo "retry: 3000\n\n";
        // Da dove riparte la pagina successiva, se arriva entro un minuto: senza
        // eventi recenti il cursore restava vecchio e la nuova pagina perdeva
        // cio' che arrivava mentre si caricava.
        echo "event: cursore\ndata: {$lastId}\n\n";
        flush();

        $streamId = $pidForStream > 0 ? Live::apriStream($pidForStream) : 0;
        register_shutdown_function(static function () use ($streamId): void {
            if ($streamId > 0) {
                Live::chiudiStream($streamId);
            }
        });

        $maxS = GameConfig::int('live.stream_max_s', 300);
        $tickMs = max(500, GameConfig::int('live.tick_ms', 2000));
        $start = time();
        $lastBeat = time();

        $stato = Live::statoStream($lastId);
        while (!connection_aborted() && (time() - $start) < $maxS) {
            if ($streamId > 0 && Live::streamSuperato($pidForStream, $streamId)) {
                // la pagina non deve riconnettersi da sola: lo fara' quando
                // tornera' in primo piano
                echo "event: superato\ndata: 1\n\n";
                @flush();
                break;
            }
            $player = \App\Game\PlayerService::forUser((int) \App\Auth\Auth::id())
                ?? ($pidForStream > 0 ? Database::first('SELECT * FROM players WHERE id = ?', [$pidForStream]) : null);
            if ($player === null) {
                break;
            }
            foreach (Live::giro($player, $stato) as $ev) {
                $data = json_encode([
                    // L'id dell'evento, distinto dal cursore «id:» (che e' il
                    // massimo raggiunto): serve al client per non mostrare
                    // due volte lo stesso evento.
                    'eid'     => (int) $ev['id'],
                    'kind'    => $ev['kind'],
                    'title'   => $ev['title'],
                    'body'    => $ev['body'],
                    'payload' => $ev['payload'] ? json_decode((string) $ev['payload'], true) : null,
                    'at'      => $ev['created_at'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo "id: {$ev['cursore']}\n";
                echo "event: {$ev['kind']}\n";
                echo 'data: ' . $data . "\n\n";
            }
            // Solo scrivendo ci si accorge che la pagina e' stata chiusa: ogni
            // cinque secondi (erano quindici, e ogni cambio di schermata
            // lasciava un processo appeso fino ad allora).
            if (time() - $lastBeat >= 5) {
                // il battito porta il cursore: la pagina sa sempre da dove
                // ripartire, anche se non le arriva nessun evento
                echo "event: cursore\ndata: " . (int) $stato['cursore'] . "\n\n";
                if ($streamId > 0) {
                    Live::battitoStream($streamId);
                }
                $lastBeat = time();
            }
            @flush();
            usleep($tickMs * 1000);
        }

        exit;
    }

    public function alerts(Request $request): Response
    {
        $pid = (int) Ctx::$player['id'];
        if ($request->isPost()) {
            Live::markAlertsRead($pid);
            return Response::json(['ok' => true]);
        }
        return Response::json([
            'ok'     => true,
            'unread' => Live::unreadAlerts($pid),
            'items'  => Live::alerts($pid, 30),
        ]);
    }

    public function currentSector(Request $request): Response
    {
        return Response::json(['ok' => true, 'sector' => Navigation::look(TurnManager::sync(Ctx::$player), Ctx::$ship)]);
    }

    public function battles(Request $request): Response
    {
        return Response::json(['ok' => true, 'rows' => BattleLog::forPlayer((int) Ctx::$player['id'], 50)]);
    }

    public function battle(Request $request, string $id): Response
    {
        $b = BattleLog::get((int) $id, (int) Ctx::$player['id'], Auth::isAdmin());
        return $b === null
            ? Response::json(['ok' => false, 'error' => 'Non trovata.'], 404)
            : Response::json(['ok' => true, 'battle' => $b]);
    }

    public function routesHistory(Request $request): Response
    {
        $pid = (int) Ctx::$player['id'];
        return Response::json([
            'ok'      => true,
            'recent'  => RouteLog::recent($pid, 60),
            'visited' => RouteLog::mostVisited($pid, 15),
            'stats'   => RouteLog::stats($pid),
        ]);
    }

    public function saveNote(Request $request): Response
    {
        $res = SectorNotes::set(
            (int) Ctx::$player['id'],
            $request->int('sector'),
            $request->str('label'),
            $request->str('note'),
            (string) $request->input('pinned', '') === '1',
        );
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }

    public function radio(Request $request): Response
    {
        $player = TurnManager::sync(Ctx::$player);
        if ($request->isPost()) {
            $res = Radio::send($player, $request->str('channel'), $request->str('body'), $request->str('target'));
            return Response::json($res, !empty($res['ok']) ? 200 : 422);
        }
        $inbox = Radio::inbox($player);
        Radio::markRead($player);
        return Response::json(['ok' => true, 'inbox' => $inbox]);
    }

    public function leaderboard(Request $request): Response
    {
        return Response::json([
            'ok'      => true,
            'players' => Leaderboard::topPlayers(30),
            'corps'   => Leaderboard::topCorps(15),
        ]);
    }

    public function attackNpc(Request $request): Response
    {
        $res = Combat::attackNpc(Ctx::$player, Ctx::$ship, $request->int('npc'), $request->int('fighters'));
        if (empty($res['ok'])) {
            return Response::json(['ok' => false, 'error' => $res['error']], 422);
        }
        $res['player'] = self::playerDto($res['player']);
        $res['ship'] = self::shipDto($res['ship']);
        return Response::json($res);
    }

    public function corpAction(Request $request, string $action): Response
    {
        $res = match ($action) {
            'crea'      => Corp::create(Ctx::$player, $request->str('name'), $request->str('tag'), (string) $request->input('password', '')),
            'entra'     => Corp::join(Ctx::$player, $request->str('name'), (string) $request->input('password', '')),
            'esci'      => Corp::leave(Ctx::$player),
            'deposita'  => Corp::treasury(Ctx::$player, $request->int('amount'), 'deposit'),
            'preleva'   => Corp::treasury(Ctx::$player, $request->int('amount'), 'withdraw'),
            default     => ['ok' => false, 'error' => 'Azione sconosciuta.'],
        };
        return Response::json($res, !empty($res['ok']) ? 200 : 422);
    }
}
