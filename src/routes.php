<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AdminGameController;
use App\Controllers\AuthController;
use App\Controllers\BankController;
use App\Controllers\CombatController;
use App\Controllers\CodexController;
use App\Controllers\CorpController;
use App\Controllers\CrewController;
use App\Controllers\FactionController;
use App\Controllers\GameApiController;
use App\Controllers\GameController;
use App\Controllers\HomeController;
use App\Controllers\LeaderboardController;
use App\Controllers\MetaController;
use App\Controllers\MissionController;
use App\Controllers\ModuleController;
use App\Controllers\PlanetController;
use App\Controllers\PortController;
use App\Controllers\RadioController;
use App\Controllers\RegistroController;
use App\Controllers\ScanController;
use App\Controllers\ShipyardController;
use App\Core\Router;

/** @var Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/health', [HomeController::class, 'health']);

// Autenticazione
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/registrati', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/registrati', [AuthController::class, 'register'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/attesa', [AuthController::class, 'pending'], ['auth']);

// Gioco (HTML)
$game = ['auth', 'active', 'player'];
$router->get('/gioco', [GameController::class, 'index'], $game);
$router->post('/gioco/primi-passi/nascondi', [GameController::class, 'hideOnboarding'], $game);
$router->get('/gioco/guida', [GameController::class, 'guide'], $game);
$router->post('/gioco/muovi', [GameController::class, 'move'], $game);
$router->get('/gioco/rotta', [GameController::class, 'course'], $game);
$router->post('/gioco/autopilot', [GameController::class, 'autopilot'], $game);
$router->post('/gioco/faro', [GameController::class, 'beacon'], $game);

// Porto ed economia (HTML)
$router->get('/gioco/porto', [PortController::class, 'show'], $game);
$router->post('/gioco/porto/scambio', [PortController::class, 'quickTrade'], $game);
$router->get('/gioco/banca', [BankController::class, 'show'], $game);
$router->post('/gioco/banca/{dir}', [BankController::class, 'operate'], $game);

// Cantiere (HTML)
$router->get('/gioco/cantiere', [ShipyardController::class, 'show'], $game);
$router->post('/gioco/cantiere/nave', [ShipyardController::class, 'buyShip'], $game);
$router->post('/gioco/cantiere/soccorso', [ShipyardController::class, 'rescue'], $game);
$router->post('/gioco/cantiere/upgrade', [ShipyardController::class, 'upgrade'], $game);
$router->post('/gioco/cantiere/hardware', [ShipyardController::class, 'hardware'], $game);

// Officina moduli (HTML)
$router->get('/gioco/moduli', [ModuleController::class, 'index'], $game);
$router->post('/gioco/moduli/installa', [ModuleController::class, 'install'], $game);
$router->post('/gioco/moduli/rimuovi', [ModuleController::class, 'remove'], $game);
$router->post('/gioco/moduli/smonta', [ModuleController::class, 'scrap'], $game);
$router->post('/gioco/moduli/potenzia', [ModuleController::class, 'upgrade'], $game);
$router->post('/gioco/moduli/raffina', [ModuleController::class, 'refine'], $game);
$router->post('/gioco/moduli/crafta', [ModuleController::class, 'craft'], $game);

// Equipaggio (HTML)
$router->get('/gioco/equipaggio', [CrewController::class, 'index'], $game);
$router->post('/gioco/equipaggio/assumi', [CrewController::class, 'hire'], $game);
$router->post('/gioco/equipaggio/assegna', [CrewController::class, 'assign'], $game);
$router->post('/gioco/equipaggio/panchina', [CrewController::class, 'bench'], $game);
$router->post('/gioco/equipaggio/congeda', [CrewController::class, 'dismiss'], $game);
$router->post('/gioco/equipaggio/cura', [CrewController::class, 'heal'], $game);
$router->post('/gioco/equipaggio/abilita', [CrewController::class, 'ability'], $game);

// Missioni away (HTML)
$router->get('/gioco/missioni', [MissionController::class, 'index'], $game);
$router->post('/gioco/missioni/invia', [MissionController::class, 'run'], $game);

// Fazioni & reputazione (HTML)
$router->get('/gioco/fazioni', [FactionController::class, 'index'], $game);
$router->post('/gioco/fazioni/compra', [FactionController::class, 'buy'], $game);
$router->post('/gioco/fazioni/ammenda', [FactionController::class, 'amnesty'], $game);

// Scansione & frontiera (HTML)
$router->post('/gioco/scansiona', [ScanController::class, 'scan'], $game);
$router->post('/gioco/sonda', [ScanController::class, 'probe'], $game);
$router->post('/gioco/relitto', [ScanController::class, 'salvage'], $game);
$router->post('/gioco/deposito', [ScanController::class, 'harvest'], $game);
$router->post('/gioco/anomalia', [ScanController::class, 'study'], $game);
$router->post('/gioco/giacimento', [ScanController::class, 'mine'], $game);
$router->get('/gioco/codex', [CodexController::class, 'index'], $game);

// Combattimento e dispiegamento (HTML)
$router->post('/gioco/attacca/nave', [CombatController::class, 'attackShip'], $game);
$router->post('/gioco/attacca/porto', [CombatController::class, 'attackPort'], $game);
$router->post('/gioco/dispiega/fighter', [CombatController::class, 'deployFighters'], $game);
$router->post('/gioco/dispiega/mine', [CombatController::class, 'deployMines'], $game);
$router->post('/gioco/recupera/fighter', [CombatController::class, 'pullFighters'], $game);

// Pianeti (HTML)
$router->get('/gioco/pianeti', [PlanetController::class, 'sectorList'], $game);
$router->get('/gioco/pianeta/{id}', [PlanetController::class, 'manage'], $game);
$router->post('/gioco/genesi', [PlanetController::class, 'genesis'], $game);
$router->post('/gioco/coloni/carica', [PlanetController::class, 'pickup'], $game);
$router->post('/gioco/pianeta/{id}/coloni', [PlanetController::class, 'colonists'], $game);
$router->post('/gioco/pianeta/{id}/assegna', [PlanetController::class, 'assign'], $game);
$router->post('/gioco/pianeta/{id}/risorse', [PlanetController::class, 'resources'], $game);
$router->post('/gioco/pianeta/{id}/tesoreria', [PlanetController::class, 'treasury'], $game);
$router->post('/gioco/pianeta/{id}/citadel', [PlanetController::class, 'citadel'], $game);
$router->post('/gioco/pianeta/{id}/quasar', [PlanetController::class, 'quasar'], $game);
$router->post('/gioco/pianeta/{id}/guarnigione', [PlanetController::class, 'garrison'], $game);
$router->post('/gioco/pianeta/{id}/industria', [PlanetController::class, 'industry'], $game);
$router->post('/gioco/pianeta/{id}/attacca', [PlanetController::class, 'attack'], $game);

$router->post('/gioco/attacca/npc', [CombatController::class, 'attackNpc'], $game);

// Radio e classifiche (HTML)
$router->get('/gioco/radio', [RadioController::class, 'show'], $game);
$router->post('/gioco/radio/invia', [RadioController::class, 'send'], $game);
$router->get('/gioco/classifica', [LeaderboardController::class, 'show'], $game);

// Registro: battaglie, rotte, note (HTML)
$router->get('/gioco/battaglie', [RegistroController::class, 'battles'], $game);
$router->get('/gioco/battaglia/{id}', [RegistroController::class, 'battle'], $game);
$router->get('/gioco/rotte', [RegistroController::class, 'routes'], $game);
$router->post('/gioco/settore/nota', [RegistroController::class, 'saveNote'], $game);

// Corporazioni (HTML)
$router->get('/gioco/corp', [CorpController::class, 'show'], $game);
$router->post('/gioco/corp/crea', [CorpController::class, 'create'], $game);
$router->post('/gioco/corp/entra', [CorpController::class, 'join'], $game);
$router->post('/gioco/corp/esci', [CorpController::class, 'leave'], $game);
$router->post('/gioco/corp/alleanza', [CorpController::class, 'ally'], $game);
$router->post('/gioco/corp/{dir}', [CorpController::class, 'treasury'], $game);

// Meta: traguardi, albo, mercato nero, contratti
$router->get('/gioco/traguardi', [MetaController::class, 'achievements'], $game);
$router->get('/gioco/albo', [MetaController::class, 'hall'], $game);
$router->get('/gioco/mercato-nero', [MetaController::class, 'blackMarket'], $game);
$router->post('/gioco/mercato-nero', [MetaController::class, 'bmAction'], $game);
$router->get('/gioco/contratti', [MetaController::class, 'contracts'], $game);
$router->post('/gioco/contratti', [MetaController::class, 'contractAction'], $game);

// Gioco (API JSON)
$router->get('/api/stato', [GameApiController::class, 'state'], $game);
$router->get('/api/mappa', [GameApiController::class, 'map'], $game);
$router->get('/api/settore/{id}', [GameApiController::class, 'sector'], $game);
$router->get('/api/rotta', [GameApiController::class, 'courseApi'], $game);
$router->post('/api/muovi', [GameApiController::class, 'move'], $game);
$router->post('/api/autopilot', [GameApiController::class, 'autopilotApi'], $game);
$router->post('/api/faro', [GameApiController::class, 'beaconApi'], $game);

// Porto / contrattazione / banca (API JSON)
$router->get('/api/porto', [GameApiController::class, 'port'], $game);
$router->post('/api/porto/scambio', [GameApiController::class, 'quickTrade'], $game);
$router->post('/api/porto/contratta', [GameApiController::class, 'haggleOpen'], $game);
$router->post('/api/porto/contratta/offerta', [GameApiController::class, 'haggleCounter'], $game);
$router->post('/api/porto/contratta/accetta', [GameApiController::class, 'haggleAccept'], $game);
$router->post('/api/porto/contratta/lascia', [GameApiController::class, 'haggleAbort'], $game);
$router->get('/api/banca', [GameApiController::class, 'bank'], $game);
$router->post('/api/banca', [GameApiController::class, 'bank'], $game);

// Cantiere / combattimento (API JSON)
$router->get('/api/cantiere', [GameApiController::class, 'shipyard'], $game);
$router->post('/api/cantiere/nave', [GameApiController::class, 'shipyardBuy'], $game);
$router->post('/api/cantiere/upgrade', [GameApiController::class, 'shipyardUpgrade'], $game);
$router->post('/api/cantiere/hardware', [GameApiController::class, 'shipyardHardware'], $game);
$router->post('/api/attacca/nave', [GameApiController::class, 'attackShip'], $game);
$router->post('/api/attacca/porto', [GameApiController::class, 'attackPort'], $game);
$router->post('/api/dispiega/fighter', [GameApiController::class, 'deployFighters'], $game);
$router->post('/api/dispiega/mine', [GameApiController::class, 'deployMines'], $game);
$router->post('/api/recupera/fighter', [GameApiController::class, 'pullFighters'], $game);

// Pianeti / corp (API JSON)
$router->get('/api/pianeti', [GameApiController::class, 'planets'], $game);
$router->get('/api/pianeta/{id}', [GameApiController::class, 'planet'], $game);
$router->post('/api/genesi', [GameApiController::class, 'planetGenesis'], $game);
$router->post('/api/coloni/carica', [GameApiController::class, 'planetPickup'], $game);
$router->post('/api/pianeta/{id}/attacca', [GameApiController::class, 'planetAttack'], $game);
$router->post('/api/pianeta/{action}/{id}', [GameApiController::class, 'planetAction'], $game);
$router->get('/api/corp', [GameApiController::class, 'corp'], $game);
$router->post('/api/corp/{action}', [GameApiController::class, 'corpAction'], $game);

// Realtime (SSE) + alert + scheda settore
$router->get('/api/stream', [GameApiController::class, 'stream'], $game);
$router->get('/api/alerts', [GameApiController::class, 'alerts'], $game);
$router->post('/api/alerts/letti', [GameApiController::class, 'alerts'], $game);
$router->get('/api/settore', [GameApiController::class, 'currentSector'], $game);
$router->get('/api/battaglie', [GameApiController::class, 'battles'], $game);
$router->get('/api/battaglia/{id}', [GameApiController::class, 'battle'], $game);
$router->get('/api/rotte', [GameApiController::class, 'routesHistory'], $game);
$router->post('/api/settore/nota', [GameApiController::class, 'saveNote'], $game);

// Radio / classifiche / NPC (API JSON)
$router->get('/api/radio', [GameApiController::class, 'radio'], $game);
$router->post('/api/radio', [GameApiController::class, 'radio'], $game);
$router->get('/api/classifica', [GameApiController::class, 'leaderboard'], $game);
$router->post('/api/attacca/npc', [GameApiController::class, 'attackNpc'], $game);

// Amministrazione
$admin = ['auth', 'active', 'admin'];
$router->get('/admin', [AdminController::class, 'dashboard'], $admin);
$router->post('/admin/utenti/{id}/approva', [AdminController::class, 'approve'], $admin);
$router->post('/admin/utenti/{id}/sospendi', [AdminController::class, 'suspend'], $admin);
$router->post('/admin/utenti/{id}/rifiuta', [AdminController::class, 'reject'], $admin);

// Pannello di controllo del gioco
$router->get('/admin/gioco', [AdminGameController::class, 'show'], $admin);
$router->post('/admin/gioco/config', [AdminGameController::class, 'config'], $admin);
$router->post('/admin/gioco/evento', [AdminGameController::class, 'event'], $admin);
$router->post('/admin/gioco/npc', [AdminGameController::class, 'npc'], $admin);
$router->post('/admin/gioco/bigbang', [AdminGameController::class, 'bigbang'], $admin);
$router->post('/admin/gioco/stagione', [AdminGameController::class, 'season'], $admin);
$router->post('/admin/gioco/giocatore', [AdminGameController::class, 'player'], $admin);
