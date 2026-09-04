# Changelog

Registro delle modifiche sincronizzate dal deployment live a questo repo.
Ogni voce elenca i file toccati e cosa/perché è cambiato — stesso dettaglio
riportato nel messaggio del commit corrispondente.

## 2026-09-03 — Giornale di bordo

Registro incidenti persistente e sfogliabile per giocatore, con voce
coerente all'ambientazione. Quando qualcosa capita alla nave o alle
colonie fuori da un'azione esplicita (scontri all'ingresso di un settore,
hazard, pedaggi, contatti NPC, comunicazioni diplomatiche, colonie
colpite, esiti di contratti) il computer di bordo ne scrive un rapporto.
La campanella resta per il toast in tempo reale; il giornale è la
narrazione durevole.

- **[db/migrations/0022_shiplog.sql](db/migrations/0022_shiplog.sql)** —
  tabella `ship_log` (`kind`, `severity` info|warning|alert, `title`,
  `body`, `sector_id`, `data`, `read_at`) + config
  `shiplog.keep_per_player` = 200.
- **[src/Game/ShipLog.php](src/Game/ShipLog.php)** — `write()`
  non‑bloccante, `recent()` / `page()` / `unread()` / `markRead()`,
  `fromEntryEvents()` (compone e classifica una voce dalle righe‑evento di
  `Combat::onEnterSector` → travel | combat | destroyed), `channel()`
  (etichetta di canale in‑fiction), `gc()` (pota alle ultime N per
  giocatore, dal tick).
- **[src/Controllers/ShipLogController.php](src/Controllers/ShipLogController.php)**
  + rotta `GET /gioco/giornale`: pagina completa paginata
  (`?before=<id>`), segna letto all'apertura.
- **[views/game/shiplog.php](views/game/shiplog.php)** (storico) e
  pannello «Giornale di bordo» in **[views/game/index.php](views/game/index.php)**
  (ultime 6 + badge non‑letti); voce «Giornale» in topbar e `.game-nav`
  (**[views/layout.php](views/layout.php)**).
- **[assets/css/app.css](assets/css/app.css)** — `.shiplog-card` /
  `.sl-entry` (bordo sinistro per severità). [sw.js](sw.js): `v17` → `v18`.
- **[bin/tick.php](bin/tick.php)** — task `shiplog_gc`.
- Hook non‑bloccanti in `Navigation::move`, `Combat` (NPC in
  stazionamento, attacco a nave, pianeta colpito), `Contracts` (consegna /
  taglia) e `Faction` (cambio di tier, cacciatore di taglie).

## 2026-09-03 — Predisposizione config e-mail / notifiche

Scaffolding per una futura notifica all'amministratore quando arriva una
richiesta di iscrizione. Nessun codice consuma ancora queste chiavi.

- **[config/config.example.php](config/config.example.php)** — nuovi
  blocchi `mail` (`transport` = `smtp` | `sendmail` | `log`, parametri
  SMTP, mittente) e `notify` (`new_registration`, `admin_email`,
  `new_registration_mode` = `digest` | `immediate`), con placeholder.

Il config reale fuori dal DocumentRoot riusa l'account Brevo già in uso
dal forum phpBB sullo stesso server (mittente verificato, free tier
300 mail/giorno).

## 2026-09-03 — Fix: pagina Rotte non sfora più su mobile

Emerso durante la verifica mobile di tutte le pagine da loggato (21/21 ok
a 375px tranne questa): `/gioco/rotte` sforava di ~84px in orizzontale.

Causa pre-esistente (dalla fix Rotte del 02-09): `.wrap.wide .game-grid`
imposta `grid-template-columns: repeat(auto-fit, minmax(28rem, 1fr))` e ha
specificità maggiore della regola mobile `.game-grid { 1fr }`, quindi la
traccia minima di 28rem (448px) restava attiva anche sui telefoni.

- **[assets/css/app.css](assets/css/app.css)** — nel blocco
  `@media (max-width: 820px)` la regola a colonna singola ora elenca anche
  `.wrap.wide .game-grid`. Verificato a 375px: rotte e battaglie a colonna
  singola, `documentElement.scrollWidth == body.scrollWidth == 375`.
- [sw.js](sw.js): `v16` → `v17`.

## 2026-09-03 — Rimozione completa della modalità terminale

La skin di gioco a terminale (`/terminale` + `/api/comando`) è stata
eliminata del tutto: poco usata, raddoppiava la superficie di gioco da
tenere in parità con la plancia, e il `TerminalRenderer` era un parser di
comandi da ~1000 righe da riallineare a ogni fase.

Rimossi:

- `src/Game/TerminalRenderer.php` (997 righe), `views/terminal/index.php`,
  `assets/js/terminal.js`.
- Rotte `GET /terminale` e `POST /api/comando` (**[src/routes.php](src/routes.php)**).
- `GameController::terminal()`, `GameApiController::command()` e i relativi
  `use App\Game\TerminalRenderer`.
- CSS `.term-panel` / `#terminal` / `#terminal::after` / `#term-out` /
  `.term-input` / `#term-prompt` / `#term-cmd` e le regole nel blocco
  `@media (max-width: 820px)` (**[assets/css/app.css](assets/css/app.css)**).
- Link «Terminale» nella topbar (**[views/layout.php](views/layout.php)**),
  pulsante «Modalità terminale» in home e link nella Guida
  (**[views/home.php](views/home.php)**, **[views/game/guide.php](views/game/guide.php)**).
- Menzioni nei testi di `home.php` e `README.md`.

Nessuna tabella o chiave `game_config` dedicata → nessuna migrazione.
Verificato: le classi caricano, `GET /terminale` ora è 404, `/gioco` e
`/gioco/guida` rispondono, nessun riferimento residuo nel codice.
[sw.js](sw.js): `v15` → `v16`.

## 2026-09-03 — Fix: lista merci del port teaser su mobile

Seguito della verifica mobile della plancia snellita: sulla plancia
StarDock la lista merci del riquadro porto sforava il pannello su
telefoni stretti (a 375px di pochi px, ritagliata; a 320px la colonna
`%` di stock veniva tagliata). Causa: `.port-teaser-list .cl` con
`min-width: 8rem` su una riga flex che non andava a capo.

- **[assets/css/app.css](assets/css/app.css)** — nel blocco
  `@media (max-width: 820px)`: `.port-teaser-list li { flex-wrap: wrap }`
  e `.port-teaser-list .cl { min-width: 0 }`. Il contenuto va a capo,
  nessuno sforo di pagina (verificato a 320 e 375px).
- [sw.js](sw.js): `v14` → `v15`.

## 2026-09-03 — Barra di navigazione di gioco + rifiniture mobile

Con ~20 pagine di gioco l'unica navigazione era la lista nel pannello
«Comandi» della plancia — da mobile, scomoda.

- **[views/layout.php](views/layout.php)** — nuova `<nav class="game-nav">`
  sotto la topbar (per i giocatori attivi): striscia di link a tutte le
  sezioni, che **scorre orizzontalmente** su schermo stretto.
- **[views/game/index.php](views/game/index.php)** — il pannello laterale
  perde la lista generica (ora nella game-nav) → «Servizi del settore»,
  solo azioni contestuali + riepiloghi; «Computer di bordo» non più aperto
  di default.
- **[assets/css/app.css](assets/css/app.css)** — `.game-nav`; blocco
  `@media <=820`: `.guide-grid`/`.crew-grid`/`.codex-list`/`.slot-grid` a
  colonna singola, `.statusbar .v` con `overflow-wrap`, azioni di
  officina/relitti/offerte/ufficiali che non spingono più il bottone
  fuori dallo schermo.
- [sw.js](sw.js): `v13` → `v14`.

## 2026-09-03 — Passata di bilanciamento (Fasi 7–11)

I moduli si guadagnano, i Cristalli non sono banali, il minerale non
inonda l'economia, gli empori di fazione sono una scelta vera rispetto
alla produzione, la reputazione va mantenuta. Deploy:
`php bin/console.php migrate`.

- **[db/migrations/0021_balance.sql](db/migrations/0021_balance.sql)**
  *(solo `UPDATE` su valori fissi, idempotente)*:
  - **Loot** — `drop_chance_npc` `0.35`→`0.22`, `drop_chance_pvp`
    `0.55`→`0.40`, `event_bounty_luck` `1.4`→`1.2`, `double_drop_pct`
    `0.08`→`0.05`, `salvage_per_rating` `6`→`4.5`, `upgrade_cost_salvage`
    +~25%.
  - **Mining** — `crystal_chance_pct` `45`→`30`, `crystal_per_hit_max`
    `4`→`3`, `ore_per_pass_base` `8`→`6`.
  - **Craft** — `refine_equ_per_component` `2`→`3`,
    `planet_component_per_day` `48`→`36`, nuovo `refine_units_per_turn=12`.
  - **Scansione** — `wreck_module_pct` `35`→`28`, `wreck_module_deep_pct`
    `60`→`48`.
  - **Fazioni** — `kill_gain` `6`→`5`, `decay_per_day` `2`→`3`.
  - **Empori di fazione** — prezzi ~2× (erano sotto il costo di
    produzione della stessa ricetta).
- **[src/Game/Industry.php](src/Game/Industry.php)** — `refine()`: il
  costo in turni scala col lotto (`ceil(qty / refine_units_per_turn)`).
- **[src/Game/SectorFeatures.php](src/Game/SectorFeatures.php)** —
  `mine()`: moltiplicatore Cristalli del profondo `×2` → `×1.5`.

## 2026-09-03 — Onboarding: "primi passi" + Guida rapida

Per i nuovi comandanti, in vista di più giocatori.

- **[db/migrations/0020_onboarding.sql](db/migrations/0020_onboarding.sql)** —
  `players.onboarding_state` (0 attivo / 1 nascosto / 2 completato);
  config `onboarding.reward_credits`.
- **[src/Game/Onboarding.php](src/Game/Onboarding.php)** *(nuovo)* — 7
  "primi passi" (warp, commercio, banca, kill NPC, scansione, modulo,
  ufficiale) **dedotti dai dati esistenti**, nessun progresso da
  memorizzare; `maybeReward()` dà una ricompensa una tantum a
  completamento; `dismiss()`.
- **[GameController](src/Controllers/GameController.php)** — valuta
  l'onboarding in plancia; rotta `POST /gioco/primi-passi/nascondi`.
- **[views/game/index.php](views/game/index.php)** — pannello "Primi
  passi"; briefing del primo accesso riscritto, più breve, con rimando
  alla Guida.
- **[views/game/guide.php](views/game/guide.php)** + `/gioco/guida` —
  riferimento sintetico di tutti i sistemi, ognuno con link; voce
  "Guida" nella topbar e nei Comandi.
- [sw.js](sw.js): `v12` → `v13`.

## 2026-09-02 — Fase 11: profondità economica

Mining, catena produttiva e industria planetaria: il giocatore **genera**
materie prime e **costruisce** moduli su ricetta. Deploy:
`php bin/console.php migrate`.

- **[db/migrations/0019_economy_depth.sql](db/migrations/0019_economy_depth.sql)**
  — `players.crystals`/`components`, `ships.mining_laser`,
  `planets.industry` + `last_industry_at`, `sector_features.kind` +=
  `asteroid`, tabella `recipes` (10), 3 voci Codex, config `mine.*` /
  `craft.*`.
- **[SectorFeatures](src/Game/SectorFeatures.php)** — giacimenti in
  frontiera/profondo; `mine()` richiede il laser, costa turni, rende
  minerale + Cristalli (garantiti sui «metalli rari»), esaurimento a più
  passaggi, `deep_mult` premia il profondo.
- **[src/Game/Industry.php](src/Game/Industry.php)** *(nuovo)* —
  `refine()` (minerale+equip → Componenti), `recipes()`/`craft()`
  (Componenti+Cristalli+Leghe → modulo deterministico, alcune con gate di
  fazione), `togglePlanet()` + `tick()` (i pianeti in industria
  convertono lo `stock_ore` in Componenti per il proprietario).
- **Agganci** — [Shipyard](src/Game/Shipyard.php) hardware
  `mining_laser`; [bin/tick.php](bin/tick.php) task `industry`.
- **UI** — «Estrai» nei giacimenti;
  [pannello «Raffineria & produzione»](views/game/modules.php);
  interruttore industria nella [scheda pianeta](views/game/planet.php);
  comandi terminale `MINE` / `REFINE` / `CRAFT` / `INDUSTRY`.
- [sw.js](sw.js): `v11` → `v12`.

## 2026-09-02 — Fix layout pagina «Rotte» / «Battaglie»

Su schermo largo il contenuto restava in 62rem e la tabella «Ultimi
spostamenti» sforava (pulsante «Ripercorri» tagliato).

- **[views/layout.php](views/layout.php)** + **[app.css](assets/css/app.css)** —
  modificatore `.wrap.wide` (86rem) attivato dai controller di rotte e
  battaglie; dentro, `.game-grid` diventa `auto-fit minmax(28rem,1fr)`
  (2 colonne solo se c'è spazio); utility `.tbl-wrap`.
- **[views/game/routes.php](views/game/routes.php)** — «Ripercorri» →
  «Rotta», data senza `nowrap`.
- **[views/game/battles.php](views/game/battles.php)** — tabella in
  `.tbl-wrap`.
- [sw.js](sw.js): `v10` → `v11`.

## 2026-09-02 — Fase 10: fazioni & reputazione

Quattro potenze — **Federazione Unita**, **Consorzio Ferrengi**,
**Egemonia di Korr**, **Liberi Mondi della Frontiera** — con reputazione
per giocatore da −100 a +100 e 5 soglie di standing. Deploy:
`php bin/console.php migrate`.

- **[db/migrations/0018_factions.sql](db/migrations/0018_factions.sql)** —
  `factions` (con rivale), `regions.faction`, `player_reputation`,
  `faction_log`, `faction_offers` (8 moduli gate friendly/allied); 19
  chiavi `faction.*`.
- **[src/Game/Faction.php](src/Game/Faction.php)** *(nuovo)* — `adjust()`
  con clamp/log/**rivalità**; eventi da commercio, kill NPC (Ferrengi →
  +fed −ferrengi +frontier; pirata → +fed +frontier; civile → −tutti),
  kill giocatore, assalto porto/pianeta, lavoro nel profondo.
  `stardockBlocked()` (fed *ostile* → Cantiere e Banca revocati, la nave
  di soccorso resta) + `amnesty()`. `offers()`/`buyOffer()` empori allo
  StarDock. `tick()` — decadimento giornaliero + **cacciatori di taglie**
  per chi ha rep fed bassa e taglia alta.
- **Agganci** — [Combat.php](src/Game/Combat.php) (rami `def_destroyed` +
  `onEnterSector`: Ferrengi/pirati ignorano chi ha rep alta),
  [Economy::settle](src/Game/Economy.php),
  [SectorFeatures](src/Game/SectorFeatures.php) (deep),
  [AwayMissions](src/Game/AwayMissions.php);
  [ShipyardController](src/Controllers/ShipyardController.php) /
  [BankController](src/Controllers/BankController.php) gate;
  [bin/tick.php](bin/tick.php) task `factions`.
- **UI** — [`/gioco/fazioni`](views/game/factions.php) +
  [FactionController](src/Controllers/FactionController.php); link e riga
  reputazione in plancia; comandi terminale `FAC` / `FAC BUY`; CSS
  `.faction-panel` / `.rep-bar`.
- [sw.js](sw.js): `VERSION` `v9` → `v10`.

## 2026-09-02 — Fase 9: scansione & frontiera

La **scansione** diventa un'azione deliberata (costa turni) che rivela
**relitti**, **depositi**, **anomalie** e **pericoli ambientali** del
settore — e dei vicini con scanner / Scienziato / modulo. Le regioni di
frontiera/profonde ne hanno di più e migliori, ma colpiscono
all'ingresso con **hazard** (radiazioni, tempeste ioniche, pozzi
gravitazionali). Il **Codex** raccoglie le scoperte. Deploy:
`php bin/console.php migrate`.

- **[db/migrations/0017_scanning.sql](db/migrations/0017_scanning.sql)** —
  `sector_features`, `player_feature_state`, `codex_entries` (9) +
  `player_codex`; 26 chiavi `scan.*`.
- **[src/Game/SectorFeatures.php](src/Game/SectorFeatures.php)** *(nuovo)*
  — `tick()` (target per regione, batch cap, scadenze), `scan()` /
  `probe()` (BFS entro il raggio scanner), `salvage()` / `harvest()` /
  `study()` (relitto → Leghe + modulo bias deep + chance ufficiale
  ferito; deposito → crediti + Leghe + carico; anomalia → progresso
  ripetuto, +bonus Scienziato → risolta), `entryHazards()` (mai letali
  da sole; ridotti se la hazard è nota).
- **[src/Game/Codex.php](src/Game/Codex.php)** *(nuovo)* +
  **[Loot::grant()](src/Game/Loot.php)** riusato da relitti/anomalie.
- **Agganci** — [`Navigation::look()`](src/Game/Navigation.php) espone le
  feature scoperte + `region_kind`; `move()` somma il costo del pozzo
  gravitazionale; [`Combat::onEnterSector()`](src/Game/Combat.php) applica
  gli hazard; [bin/tick.php](bin/tick.php) task `features`.
- **UI** — riquadro «Scansione» nella scheda settore;
  [`/gioco/codex`](views/game/codex.php) +
  [CodexController](src/Controllers/CodexController.php); comandi
  terminale `SCAN` / `PROBE` / `SALVAGE|HARVEST|STUDY` / `CODEX`.
- [sw.js](sw.js): `VERSION` `v8` → `v9`.

## 2026-09-02 — Fase 8: equipaggio (versione piena)

Ufficiali con **ruolo**, **livello**, **skill** e un'**abilità attiva**;
occupano i posti dello scafo (`crew_slots`), danno bonus passivi e
alimentano le **missioni away** a skill-check con esiti ramificati.
`permadeath` OFF di default (toggle `crew.permadeath`). Deploy:
`php bin/console.php migrate`.

- **[db/migrations/0016_crew.sql](db/migrations/0016_crew.sql)** —
  `ship_types.crew_slots`; tabelle `officer_archetypes` (12),
  `officers`, `recruit_candidates`, `away_missions`,
  `away_mission_log`, `crew_pending`; 21 chiavi `crew.*`.
- **[src/Game/Crew.php](src/Game/Crew.php)** *(nuovo)* — generazione
  procedurale, pool di reclutamento rotante per giocatore, roster,
  hire/assign/bench/dismiss/heal, XP + level-up con crescita skill,
  **lealtà** (→ abilità tier-2), `useAbility` per ruolo,
  `consumePending`; `passiveBonuses()` con rendimenti decrescenti per
  ruolo.
- **[src/Game/AwayMissions.php](src/Game/AwayMissions.php)** *(nuovo)* —
  pool legato alla regione; risoluzione istantanea skill+livello vs
  soglia → *trionfo / successo / parziale / fallimento / disastro*;
  ricompense scalate (crediti, Leghe, modulo, ufficiale, XP); disastro
  → ferito + danno nave; cooldown per ufficiale.
- **Agganci** — [ShipStats](src/Game/ShipStats.php) (bonus equipaggio
  dopo i moduli); [Combat.php](src/Game/Combat.php) (XP ai kill; abilità
  Mira / Negoziato / scudo-allineamento); [Navigation.php](src/Game/Navigation.php)
  (Rotta rapida / sconto-warp); [Loot.php](src/Game/Loot.php) (Scansione
  profonda → bottino garantito).
- **UI** — [`/gioco/equipaggio`](views/game/crew.php) +
  [CrewController](src/Controllers/CrewController.php);
  [`/gioco/missioni`](views/game/missions.php) +
  [MissionController](src/Controllers/MissionController.php); link e
  riepilogo sulla plancia; comandi terminale `CREW` / `RECRUIT` /
  `MISS`; CSS `.officer-card` / `.xp-bar`.
- [sw.js](sw.js): `VERSION` `v7` → `v8`.

## 2026-09-02 — Fase 7: loot con fasce di rarità e moduli nave

Ogni combattimento vinto può lasciare un **modulo** (5 fasce: Civile /
Militare / Sperimentale / Xeno / Precursore) e produce sempre **Leghe di
recupero**. I moduli si installano negli slot dello scafo e ne
modificano le statistiche effettive. Tutto data-driven da `loot.*`.
Deploy: `php bin/console.php migrate`.

- **[db/migrations/0015_loot_modules.sql](db/migrations/0015_loot_modules.sql)**
  — `ship_types` guadagna 5 colonne slot (0 sulla capsula);
  `players.salvage`; tabelle `item_types` (catalogo 23 moduli v1),
  `player_items` (inventario), `ship_modules` (installati); 18 chiavi
  `game_config` `loot.*`.
- **[src/Game/ShipStats.php](src/Game/ShipStats.php)** *(nuovo)* — overlay
  degli effetti dei moduli, applicato in
  [`PlayerService::ship()`](src/Game/PlayerService.php): `combat_rating`,
  `turns_per_warp`, `holds_total`, `max_shields`, scanner, mantello,
  rigen. scudi. Senza moduli è identico a prima.
- **[src/Game/Loot.php](src/Game/Loot.php)** *(nuovo)* — `rollKill()`:
  materiale sempre (∝ stazza del bersaglio) + drop pesato per fascia con
  bonus regione (frontier/deep), evento *bounty_season* e fortuna dai
  moduli. PvP: pavimento di fascia, esclusione bersagli protetti / rating
  basso, cap 1/giorno per vittima.
- **[src/Game/Modules.php](src/Game/Modules.php)** *(nuovo)* — officina
  StarDock: install / remove / scrap / upgrade (sale di una fascia con
  crediti + Leghe).
- **Agganci** — [Combat.php](src/Game/Combat.php): drop nei rami
  `def_destroyed` di NPC/nave/porto/pianeta; alla distruzione i moduli
  installati si perdono con rimborso parziale in Leghe.
  [Shipyard.php](src/Game/Shipyard.php): al cambio scafo i moduli tornano
  in inventario. [Navigation.php](src/Game/Navigation.php): rigen. scudi
  a fine salto. [BattleLog.php](src/Game/BattleLog.php): `drops` nel
  dettaglio.
- **UI** — nuova pagina [`/gioco/moduli`](views/game/modules.php) +
  [ModuleController](src/Controllers/ModuleController.php) + rotte; link e
  riepilogo scafo sulla plancia; link «Officina moduli» al Cantiere; riga
  bottino nel replay; comando terminale `MOD [FIT|OFF|SCRAP|UP <id>]`;
  chip `.rarity-*` in [app.css](assets/css/app.css).
- [sw.js](sw.js): `VERSION` `v6` → `v7`.

## 2026-09-01 — Capsula di salvataggio + riordino della plancia

**Capsula di salvataggio** — non è più un vicolo cieco. Prima: nave
distrutta → capsula a 0 stive allo StarDock, e senza crediti per
ricomprare uno scafo si restava bloccati.

- **[db/migrations/0014_escape_pod.sql](db/migrations/0014_escape_pod.sql)**
  — la capsula ha **5 stive** (`ship_types` e capsule già in volo);
  nuove chiavi `hardware.pod_holds`, `hardware.rescue_ship_type`. Sul
  deploy: `php bin/console.php migrate`.
- **[src/Game/Combat.php](src/Game/Combat.php)** `destroyShip` — la
  capsula riceve `hardware.pod_holds` stive: si può commerciare in
  piccolo e risalire.
- **[src/Game/Shipyard.php](src/Game/Shipyard.php)** `rescueShip` +
  **[ShipyardController](src/Controllers/ShipyardController.php)** + rotta
  `POST /gioco/cantiere/soccorso` — **nave di soccorso** della
  Federazione: scafo base gratuito quando si è in capsula e i crediti
  non bastano per il modello più economico (la perdita del 50% crediti
  alla morte resta).
- **[views/game/shipyard.php](views/game/shipyard.php)** — banner con
  «Richiedi nave di soccorso»; **[views/game/index.php](views/game/index.php)**
  — pannello «Capsula di salvataggio» con le istruzioni sulla plancia.

**Riordino della plancia** — con la mappa 3D a tutta larghezza in fondo,
la colonna destra in alto era vuota. Ora `.plancia-grid`: a sinistra
(largo) la scheda del settore e tutto ciò che lo riguarda; a destra un
pannello **«Comandi»** (collegamenti rapidi) più «Computer di bordo» e
«Nota / preferito»; sotto, la mappa. La griglia di gioco passa a una
colonna sola sotto gli 820px.
([app.css](assets/css/app.css)) · [sw.js](sw.js) `v5` → `v6`.

## 2026-09-01 — Mappa stellare 3D (canvas, force-directed)

La mappa 2D restava troppo fitta (coordinate dell'universo raggruppate;
lo slider "distanza" non separava i punti vicini). Sostituita con una
vista **3D su `<canvas>`**, nessuna dipendenza.

- **[assets/js/game.js](assets/js/game.js)** — riscritto:
  - **layout force-directed 3D** dal grafo dei warp (repulsione + molle +
    gravità, poi normalizzazione del raggio): i settori collegati si
    distanziano da soli fino a una spaziatura leggibile. Seme
    deterministico per id → forma stabile.
  - camera orbitale: trascina = ruota, rotella = zoom verso il
    puntatore, Shift+trascina / due dita = pan, doppio clic = centra,
    clic su settore adiacente = movimento.
  - proiezione prospettica: nodi e archi sfumano con la profondità.
  - **controlli esterni al riquadro**: slider Rotazione / Inclinazione /
    Spaziatura; Etichette (`solo qui` / `qui + vicini` / `conosciute + #`,
    con anti-sovrapposizione); interruttori rotte, «solo esplorati»,
    «vista 2D»; `+`/`−` e «Adatta». Preferenze in `localStorage`.
- **[assets/css/app.css](assets/css/app.css)** — `.map-card.map-3d` a
  tutta larghezza; `#starmap` contenitore del canvas; barra `.map-orbit`;
  rimosse le regole SVG della vecchia mappa.
- [sw.js](sw.js): `VERSION` `v4` → `v5`.

## 2026-09-01 — Date IT, mappa regolabile, eventi più radi, occhio password, fix «rotte»

- **Date/orario in formato italiano** — nuovi helper
  [`fmt_dt()` / `fmt_date()`](src/Support/helpers.php) (`GG/MM/AAAA HH:MM`,
  ora di Roma). Sostituiti tutti i punti di stampa nelle viste (rotte,
  battaglie, radio, dashboard admin, albo d'oro, pianeti, pannello gioco)
  e nel pannello campanella realtime ([live.js](assets/js/live.js),
  formattazione via regex lato client).
- **Mappa stellare** ([game.js](assets/js/game.js),
  [index.php](views/game/index.php), [app.css](assets/css/app.css)):
  - zoom **verso il puntatore** (rotella e pinch) — la mappa non
    "scappa" più verso l'angolo; tasti `+`/`−` e `Adatta`;
  - barra controlli: **Etichette** (`solo qui` / `qui + vicini` /
    `conosciute + #` — nome se visitato, altrimenti `#numero`; auto-declutter
    quando è troppo rimpicciolita) e **Distanza** (slider che allarga la
    spaziatura fra i punti senza ingrandirli). Entrambe ricordate in
    `localStorage`;
  - etichette con contorno leggibile sopra le rotte, linee verso i vicini
    evidenziate, clamp morbido del pan.
- **Eventi globali meno frequenti** — default e seed a
  `events.interval_min=240`, `events.chance_pct=40`
  ([Events.php](src/Game/Events.php),
  [0009_meta.sql](db/migrations/0009_meta.sql)): ~1 ogni 10 h. Sul live:
  `php bin/console.php config:set events.interval_min 240` e
  `… config:set events.chance_pct 40`.
- **Login — «mostra password»** — pulsante 👁 accanto al campo
  ([login.php](views/auth/login.php), [app.js](assets/js/app.js)); campo
  password con `autocapitalize`/`autocorrect`/`spellcheck` disattivati.
- **Fix sovrapposizioni pagina «rotte»** ([app.css](assets/css/app.css)) —
  in `.game-grid` i pannelli ora possono rimpicciolirsi (`min-width:0`) e
  le tabelle scrollano invece di sforare sulla colonna accanto.
- [sw.js](sw.js): `VERSION` `v3` → `v4`.

## 2026-08-29 — Login affidabile da mobile

Il login falliva da smartphone con credenziali valide su desktop. Tre
cause lato client, tutte più frequenti su mobile, corrette insieme.

- **[src/Core/Session.php](src/Core/Session.php)**
  - `session_regenerate_id(true)` → `(false)` (rotazione periodica e
    `regenerate()` al login): con `true` la vecchia sessione spariva
    subito e un client lento a salvare/inviare il nuovo cookie — tab
    sospese, cambio rete durante il redirect post-login — restava senza
    sessione e rimbalzava sul login senza errore.
  - cookie di sessione: `path` da `/` a `/subspazio/`, così non concorre
    con gli altri siti del dominio per il limite del cookie jar (più
    stretto su mobile). Una tantum: le sessioni col vecchio path vanno
    rifatte.
- **[views/auth/login.php](views/auth/login.php)**,
  **[views/auth/register.php](views/auth/register.php)** — campi
  login/username/email con `autocapitalize="none"`, `autocorrect="off"`,
  `spellcheck="false"`: la tastiera mobile capitalizzava/autocorreggeva
  il testo → «Credenziali non valide» solo da telefono.

## 2026-08-29 — Tema unico scuro (rimossa la modalità chiara)

In modalità chiara restavano tre combinazioni illeggibili, tutte dovute
al blocco `@media (prefers-color-scheme: light)`: la status bar (sfondo
scuro fisso, valori che diventavano blu scuro), gli alert/`event-banner`
(testo chiaro su tinta chiara) e le `pill`/`tag` semantiche (verde/ambra
chiaro su pastello quasi bianco). Il tema "console di plancia" è scuro
per natura; invece di mantenere due palette la UI è ora **solo scura** —
palette già verificata a contrasto WCAG AA.

- **[assets/css/app.css](assets/css/app.css)** — rimosso l'intero blocco
  `@media (prefers-color-scheme: light)`. `html { color-scheme: dark }`
  resta: anche col sistema in light i controlli di form, le scrollbar e
  gli sfondi UA sono in variante scura. Restano i blocchi `@media`
  mobile e `prefers-reduced-motion`.
- **[views/layout.php](views/layout.php)** — `<meta name="color-scheme">`
  `dark light` → `dark`; `theme-color` `#0b0f17` → `#070b12`.
- **[manifest.webmanifest](manifest.webmanifest)** — `background_color` /
  `theme_color` `#0b0f17` → `#070b12`.
- **[sw.js](sw.js)** — `VERSION` `v2` → `v3`.

## 2026-08-29 — Restyle sci-fi della UI + leggibilità

Ridisegno del foglio di stile con due obiettivi: correggere i punti in
cui il testo era poco leggibile (scritte scure su campi scuri) e dare
alla UI un aspetto più ispirato al tema spaziale. Nessuna classe
rinominata; la vista desktop e i blocchi `@media` (mobile + modalità
chiara) restano invariati nel comportamento. Nessun font esterno (CSP).

- **[assets/css/app.css](assets/css/app.css)** — riscritto mantenendo
  tutti i selettori.
  - *Leggibilità*: baseline unica per `input`/`select`/`textarea` (sfondo
    scuro esplicito + colore `--ink`); `option`/`optgroup` con colori
    espliciti (molti browser non li ereditano dal `<select>`, la tendina
    risultava illeggibile); `::placeholder` esplicito; `--ink` e
    `--ink-soft` schiariti (testi secondari da ~4.6:1 a ~8.5:1);
    `::selection` e scrollbar tematizzate; `:focus-visible` con outline;
    `pill`/`tag`/`alert` con fondo tinto oltre al bordo.
  - *Tema*: palette "console di plancia" (ciano/viola su blu-nero);
    `body` con gradiente spaziale + campo stellato in `::before` (deriva
    lentissima, spenta con `prefers-reduced-motion`); `.panel` con ombra
    HUD e barra d'accento sui titoli; `.topbar` sticky a vetro; bottoni
    con glow; `.card`/`.statusbar`/tabelle con etichette monospazio;
    `#starmap` a vignetta stellare; `#terminal` con scanline CRT tenue.
    Font: stack di sistema per il testo, monospazio per dati e terminale.
  - Modalità chiara: tutti i token ridefiniti, struttura identica.
- **[sw.js](sw.js)** — `VERSION` `subspazio-v1` → `subspazio-v2`: il
  service worker rifà il precache del guscio (che include `app.css`) e
  scarta la cache vecchia, altrimenti la PWA installata mostrerebbe il
  CSS precedente.

## 2026-08-29 — Homepage aggiornata + `user:passwd`

- **[views/home.php](views/home.php)** — la sezione "Roadmap" mostrava
  ancora "Fase 0 — In corso" e arrivava solo alla Fase 5. Sostituita con
  "Cosa c'è nel gioco": elenco sintetico delle aree presenti.
- **[assets/css/app.css](assets/css/app.css)** — `.roadmap` stila anche
  `<ul>`.
- **[bin/console.php](bin/console.php)** — nuovo `user:passwd <username>`
  per reimpostare la password di un utente (prompt nascosto o 2º
  argomento; min 10 caratteri; incrementa `session_epoch` per invalidare
  le sessioni). Recupero dell'accesso admin.

## 2026-08-29 — Layout responsive per smartphone/tablet (desktop invariato)

Reso il gioco pienamente usabile su smartphone e tablet in portrait, senza
modificare la vista da monitor PC. Tutte le regole nuove sono confinate in
`@media (max-width: 820px)` e `(max-width: 380px)`; nel foglio base sono
state aggiunte solo due regole che nascondono i nuovi elementi.

- **[views/layout.php](views/layout.php)** — aggiunto il toggle
  "hamburger" nella `.topbar` (checkbox `#nav-toggle` + `label.nav-burger`,
  pure CSS, nessun JS); la `<nav>` ha ora `id="topnav"`.

- **[assets/css/app.css](assets/css/app.css)**
  - `.nav-toggle` / `.nav-burger`: `display:none` nel foglio base
    (inattivi sul desktop); rimosso il vecchio `@media (max-width: 540px)`
    che impilava la topbar.
  - Nuovo `@media (max-width: 820px)`: navbar come menu a comparsa
    verticale (`#nav-toggle:checked ~ nav#topnav`); `.statusbar` da riga a
    griglia 3 colonne; tutti i form `.row`/`.stack`/`.upg-grid`/
    `.hg-controls` impilati con input a tutta larghezza e `font-size:16px`
    (anti-zoom iOS); tabelle `display:block; overflow-x:auto`; tap target
    dei warp più grandi; overlay (`#toast-host`, `.alert-panel`,
    `.mod-more`) a tutta larghezza; ritocchi a `#starmap`, terminale,
    `.ach-grid`, `.registro-links`. Assorbito il breakpoint isolato di
    `.radio-log`.
  - Nuovo `@media (max-width: 380px)`: `.statusbar` a 2 colonne, titoli
    più piccoli.

  La mappa stellare ha già `touch-action:none` e gestione Pointer Events
  (pan + pinch-zoom); il `viewport` meta era già presente nel layout.
  Verificato a 375×812: nessun overflow orizzontale su plancia, cantiere,
  contratti; hamburger funzionante; input a 16px.

## 2026-08-29 — Import iniziale

Prima pubblicazione del progetto. Copia del deployment live (solo codice
applicativo: `src/`, `views/`, `assets/`, `bin/`, `db/migrations/`, `index.php`,
`.htaccess`, `manifest.webmanifest`, `sw.js`, `offline.html`, `deploy/`,
`config/config.example.php`, `db/setup.sql` con password placeholder).

Contenuto: clone completo delle meccaniche di TradeWars 2002 (universo,
navigazione, turni, economia dinamica con contrattazione, banca, combattimento
navi/porti, cantiere e hardware, pianeti con Genesi/Citadel/Quasar,
corporazioni) più il meta-mondo (classifiche, radio subspaziale, NPC
Ferrengi/pirati/mercanti, eventi globali) e le evoluzioni moderne (realtime
SSE, PWA con guscio offline, pannello admin, qualità della vita con replay
battaglie/cronologia rotte/preferiti, stagioni con ladder, traguardi, mercato
nero, alleanze fra corp, contratti/taglie fra giocatori).

- `db/setup.sql`: la password reale del bootstrap è stata sostituita con il
  placeholder `CAMBIAMI`; le credenziali vivono solo nel config fuori dal
  DocumentRoot.
- `README.md`, `LICENSE` (GPL-3.0), `.gitignore`, `CHANGELOG.md`: specifici
  di questa copia pubblica, non sincronizzati dalla live.
