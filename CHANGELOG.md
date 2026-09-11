# Changelog

Registro delle modifiche sincronizzate dal deployment live a questo repo.
Ogni voce elenca i file toccati e cosa/perché è cambiato — stesso dettaglio
riportato nel messaggio del commit corrispondente.

## 2026-09-11 — Pass di coerenza: icone di sezione, tooltip non tagliati, stemmi

- **Profilo** — rimossa la sotto-griglia «Sagome di nave». La marca di
  flotta torna a essere **solo uno stemma astratto**; i valori
  `nave:<ckey>` restano validi lato codice (`Identity::validCrest` /
  `symbolId`) perché `ship_art` li riusa internamente.
  **[src/Game/Identity.php](src/Game/Identity.php)** — `CRESTS` +10
  (`scudo`, `spada`, `ala`, `bussola`, `ingranaggio`, `serpente`,
  `atomo`, `diamante`, `luna`, `sole`) → **28 stemmi**; relativi
  `<symbol>` in **[views/partials/crest_sprite.php](views/partials/crest_sprite.php)**,
  etichette in `CREST_LABELS`.
- **Aiuto «?» — dimensione fissa** ovunque: rimossi gli override
  `font-size` per `h1`/`h2`/`summary`; l'icona è ora `1.15 rem` in
  qualunque contesto (**[assets/css/app.css](assets/css/app.css)**).
- **Aiuto «?» — mai tagliato**:
  **[assets/js/help.js](assets/js/help.js)** (nuovo) al hover/focus
  riposiziona la tendina come `position: fixed`, la clampa al viewport
  (margine 12 px) e la ribalta sopra se sotto non c'è spazio → non viene
  più troncata da `overflow`/bordi di sezione. Senza JS resta il
  fallback CSS in-flow. `html.js-help` attiva la modalità; chiude su
  scroll/resize/Esc. Caricato in **[views/layout.php](views/layout.php)**,
  in `SHELL` nel **[sw.js](sw.js)**.
- **Icona su ogni intestazione** — ogni `<h1>`/`<h2>`/`<summary>` delle
  schermate di gioco ha ora `<span class="sec-ic">…</span>` col suo
  emoji davanti al titolo (~85 punti, incl. le 12 voci della guida via
  helper `$sec`). Vocabolario coerente (📰 🚀 ⚔️ 🏦 🪐 🛠️ 📖 …).
  CSS `.sec-ic` per allineamento e dimensione uniformi.
- **[sw.js](sw.js)** — cache `subspazio-v40`.

## 2026-09-11 — Le 13 illustrazioni di nave sono arrivate

- **[assets/ships/](assets/ships/)** — i 13 PNG (512×512, trasparenti), uno
  per `ship_types.ckey`. `ship_art()` li serve ora al posto della sagoma
  SVG in plancia (riga «Scafo»), classifica, «navi qui» e catalogo del
  Cantiere.
- **[views/game/leaderboard.php](views/game/leaderboard.php)** /
  **[views/game/shipyard.php](views/game/shipyard.php)** /
  **[views/game/index.php](views/game/index.php)** — dimensioni ritoccate
  per leggibilità: classifica 30 px, catalogo 34 px, riga «Scafo» 40 px
  («navi qui» resta 28 px).
- **[sw.js](sw.js)** — cache `subspazio-v39`.

## 2026-09-11 — Aiuto contestuale «?» su ogni sezione

- **[src/Game/Help.php](src/Game/Help.php)** — registro centrale dei testi
  d'aiuto (chiave `<schermata>.<slug>`), una riga per voce.
- **[views/partials/help.php](views/partials/help.php)** — marcatore «?»
  con tendina in **CSS puro** (hover/focus, nessun JS → compatibile con
  la CSP). Non rende nulla se la chiave non esiste.
- **[assets/css/app.css](assets/css/app.css)** — `.help`/`.help-q`/
  `.help-pop`; la tendina è theme-aware e si ribalta a destra sotto i
  640 px.
- Marcatori applicati a ~55 intestazioni di sezione: plancia (settore,
  warp, forze, servizi, mappa, giornale, rapporto di rientro, prede,
  primi passi, incontro, notiziario, sonda, armi, occultamento, computer
  di bordo, nota), porto, banca, EPS, Cantiere
  (riparazioni/potenziamenti/hardware/navi), moduli
  (slot/inventario/officina/raffineria/lavori), equipaggio, missioni,
  contratti, pianeti e pianeta (produzione/citadel/assalto), corp,
  radio, classifica, fazioni, codex, registri, traguardi, albo, mercato
  nero, profilo.

## 2026-09-11 — Illustrazione del modello di nave posseduto

Predispone il rendering delle illustrazioni di nave (una per modello) in
plancia, classifica, «navi qui» e catalogo del Cantiere. Finché i PNG
non ci sono, l'interfaccia ripiega automaticamente sulla sagoma SVG.

- **[src/Support/helpers.php](src/Support/helpers.php)** — helper
  `ship_art($typeKey)`: URL di `assets/ships/<ckey>.png` se il file
  esiste (chiave sanificata, `is_file` in cache per-richiesta), altrimenti
  `null`.
- **[views/partials/ship_art.php](views/partials/ship_art.php)** — `<img>`
  se il PNG c'è, altrimenti `partial('crest', ['crest' => 'nave:<type>'])`
  (sagoma vettoriale).
- **[assets/ships/README.md](assets/ships/README.md)** — i 13 nomi file
  attesi (= `ship_types.ckey`).
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `look()`
  players_here porta `ship_key`.
- **[src/Game/Leaderboard.php](src/Game/Leaderboard.php)** —
  `topPlayers()` JOIN ships/ship_types → `ship_key`, `ship_type`.
- **[views/partials/player_tag.php](views/partials/player_tag.php)**,
  **[views/game/leaderboard.php](views/game/leaderboard.php)**,
  **[views/game/index.php](views/game/index.php)** (riga «Scafo»),
  **[views/game/shipyard.php](views/game/shipyard.php)** — usano il
  partial.
- **[assets/css/app.css](assets/css/app.css)** — `.ship-art`,
  `.hull-line`.

## 2026-09-11 — FedNews: niente doppio bollettino

Il notiziario era uscito due volte a un minuto di distanza: un e2e che
azzera la tabella `fednews` girava insieme al cron e la guardia di
`FedNews::tick()` controllava solo l'ultima riga d'archivio (sparita).

- **[src/Game/FedNews.php](src/Game/FedNews.php)** — seconda guardia
  sull'ultimo messaggio `fedcomm` con corpo
  `NOTIZIARIO DELLA FEDERAZIONE%`: sopravvive a un troncamento di
  `fednews` e blocca comunque il reinvio entro l'intervallo. (Il test
  e2e ora prende `storage/tick.lock` e non è più distruttivo; il
  messaggio Radio duplicato è stato ripulito a mano sul live.)

## 2026-09-10 — Marca di flotta più varia: nuovi stemmi + sagome di nave

La scelta della marca (che compare al posto dell'avatar quando non se ne
carica uno) passa da 12 a **31 opzioni**: 6 stemmi astratti in più e le
**13 sagome dei vascelli di gioco**.

- **[src/Game/Identity.php](src/Game/Identity.php)** — `CRESTS` +6
  (`teschio`, `fenice`, `ancora`, `fulmine`, `nova`, `chiave`);
  `CREST_LABELS`. Nuovi `SHIP_MARKS` + `SHIP_MARK_LABELS` = le 13
  `ship_types.ckey`, selezionabili come marca col valore `nave:<ckey>`.
  `validCrest()` (stemma astratto **o** `nave:<ckey>`), `symbolId()`
  (→ `crest-<k>` | `ship-<k>`), `crestLabel()`. `crest()` e `save()`
  ora accettano anche le sagome.
- **[views/partials/ship_sprite.php](views/partials/ship_sprite.php)** —
  nuovo: 13 `<symbol id="ship-…">` (profilo laterale, `currentColor`,
  solo riempimenti pieni per restare leggibili a 24–30 px). Incluso nel
  layout subito dopo `crest_sprite`.
- **[views/partials/crest_sprite.php](views/partials/crest_sprite.php)**
  — i 6 nuovi `<symbol>`.
- **[db/migrations/0033_crest_marks.sql](db/migrations/0033_crest_marks.sql)**
  — `players.crest` e `corporations.crest` da `VARCHAR(24)` a
  `VARCHAR(32)` (i valori `nave:<ckey>` sono più lunghi).
- **[views/game/profilo.php](views/game/profilo.php)** — la sezione
  «Marca di flotta» ora ha la griglia degli stemmi **più** una
  sotto-griglia «Sagome di nave»; etichette da `Identity::crestLabel()`.
- **[assets/js/profile.js](assets/js/profile.js)** — l'anteprima live
  gestisce il prefisso `nave:` (→ `#ship-…`).
- **[views/partials/crest.php](views/partials/crest.php)** /
  **[views/partials/player_tag.php](views/partials/player_tag.php)** —
  risolvono la marca via `Identity::symbolId()` / `validCrest()` (prima
  scartavano ogni valore `nave:` e ripiegavano sul default).
- **[views/game/shipyard.php](views/game/shipyard.php)** — silhouette
  accanto a ogni modello nel catalogo navi.
- **[assets/css/app.css](assets/css/app.css)** — `.crest-subhead`,
  `.ship-mark`.
- **[sw.js](sw.js)** — cache `subspazio-v37`.

## 2026-09-10 — Iconcine sui beni commerciati

Un'iconcina per ogni tipo di bene, per leggere a colpo d'occhio le liste
di commercio: **⛏️ Minerale · 🌿 Organico · 🔧 Equipaggiamento · 👥 Coloni**.

- **[src/Game/Economy.php](src/Game/Economy.php)** — `ICONS` (mappa
  bene→emoji, include `colonists` che non è una commodity di mercato ma
  occupa stive), `icon()` (stringa vuota se sconosciuto), `labelIcon()`
  («⛏️ Minerale»).
- **[views/game/port.php](views/game/port.php)** — colonna «Merce» con
  icona davanti all'etichetta.
- **[views/game/index.php](views/game/index.php)** — teaser del porto
  nella scheda settore con icona; cella «Stive» della barra di stato con
  **mini-riepilogo del carico** (icona + quantità per ogni tipo a bordo)
  e `title` col dettaglio esteso.
- **[views/game/planet.php](views/game/planet.php)** — tabella «Coloni e
  produzione» con icona per categoria; riga «👥 Coloni a bordo».
- **[views/game/planets.php](views/game/planets.php)** — «👥 Coloni a
  bordo» nella barra di stato.
- **[views/game/blackmarket.php](views/game/blackmarket.php)** /
  **[views/game/contracts.php](views/game/contracts.php)** — `<option>`
  del select merce con icona; lista contratti di consegna via
  `Economy::labelIcon()`.
- **[assets/css/app.css](assets/css/app.css)** — `.cargo-mini`.
- **[sw.js](sw.js)** — cache `subspazio-v36`.

## 2026-09-10 — Occultamento + Transwarp (i due dispositivi-fantasma)

Due hardware già in vendita al Cantiere (`dev_cloak` 35k, `dev_transwarp`
28k) ma finora **inerti** ricevono una meccanica reale.

- **[db/migrations/0032_devices.sql](db/migrations/0032_devices.sql)** —
  `ships.cloaked TINYINT` (stato ON/OFF); config
  `cloak.warp_turn_penalty` (1), `cloak.fedspace_forbidden` (1),
  `transwarp.turn_cost` (5).
- **[src/Game/Cloak.php](src/Game/Cloak.php)** — nuovo motore:
  `has()`, `isActive()`, `seesCloaked($scanner)` (solo scanner
  olografico), `toggle($player,$ship,$on)` (rifiuta in Fedspace,
  ShipLog), `drop($shipId,$reason)` (decloak forzato).
- **[src/Game/ShipStats.php](src/Game/ShipStats.php)** — `effective()`
  aggiunge `cloak.warp_turn_penalty` a `turns_per_warp` quando la nave
  è occultata.
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `look()`
  seleziona `s.cloaked` per mappa e `players_here` e **filtra i
  cloaked** se il viewer non ha scanner olografico. Estratta
  `arrive()` (sequenza post-arrivo) condivisa da `move()` e dal nuovo
  **`transwarp($player,$ship,$toSector)`**: salto diretto a un settore
  già presente in `player_visited_sectors`, ignora le rotte, costo
  fisso `transwarp.turn_cost` turni, `move_log.mode='transwarp'`,
  smaschera un'eventuale nave occultata.
- **[src/Game/Combat.php](src/Game/Combat.php)** — `onEnterSector`: da
  occultato scivoli oltre caccia schierati e NPC senza ingaggio; lo
  StarDock e l'ingresso in Fedspace fanno cadere l'occultamento.
  `attackShip` rifiuta un bersaglio occultato; aprire il fuoco
  (nave/porto/NPC) smaschera l'attaccante.
- **[src/Game/Deploy.php](src/Game/Deploy.php)** — dispiegare caccia o
  mine fa cadere l'occultamento.
- **[src/Controllers/GameController.php](src/Controllers/GameController.php)**
  / **[src/routes.php](src/routes.php)** — `POST /gioco/occulta`
  (`cloak`), `POST /gioco/transwarp` (`transwarp`).
- **[views/game/index.php](views/game/index.php)** — `<details>`
  «Occultamento» dedicato (sempre reso quando la nave ha `dev_cloak`; in
  Fedspace il pulsante «Attiva» è disabilitato con spiegazione, si apre
  da solo quando l'occultamento è attivo), form Transwarp in «Computer
  di bordo», badge «🌫 Occultato» nella barra di stato, note d'aiuto.
- **[views/game/guide.php](views/game/guide.php)** — nuova sezione
  «Occultamento & Transwarp».
- **[assets/css/app.css](assets/css/app.css)** — stile `.cloak-on`.
- **[sw.js](sw.js)** — cache `subspazio-v35`.

## 2026-09-10 — FedNews / Frontier Broadcast (roadmap C3)

Il tick, se è passato `fednews.interval_hours` (24 h) dall'ultimo,
**compone un bollettino dallo stato reale del gioco** e lo pubblica sul
canale Radio `fedcomm`, firmato da un conduttore NPC che ruota fra tre.

- **[db/migrations/0031_fednews.sql](db/migrations/0031_fednews.sql)** —
  tabella `fednews` (archivio: `anchor`, `headlines` JSON, `body`,
  `created_at`); config `fednews.enabled` (1),
  `fednews.interval_hours` (24).
- **[src/Game/FedNews.php](src/Game/FedNews.php)** — `tick()` con guardia
  sull'intervallo; `compose()` pesca da: evento di mercato attivo
  (`events`), kill PvP recente (`combat_log`), nuova colonia
  (`planets.created_at`), comandante in vetta alla classifica, nuove
  registrazioni, più una riga di servizio fissa — con fallback «rotte
  sgombre, mercati stabili» quando è tutto tranquillo. `latest()` per il
  teaser (nascosto se più vecchio di 2× l'intervallo).
- **[bin/tick.php](bin/tick.php)** — nuovo task `fednews`.
- **[src/Controllers/GameController.php](src/Controllers/GameController.php)**
  / **[views/game/index.php](views/game/index.php)** — teaser
  `.fednews-card` in cima alla plancia (fino a 4 titoli + link alla
  Radio, dove compare il bollettino completo con badge).
- **[assets/css/app.css](assets/css/app.css)** — stili `.fednews-*`.
- **[sw.js](sw.js)** — cache `subspazio-v34`.

## 2026-09-10 — Plancia: colonne allineate, riquadro laterale riordinato, legenda mappa

- **[assets/css/app.css](assets/css/app.css)** — `.game-grid > .panel { margin: 0 }`: la scheda settore (colonna sinistra) e il riquadro laterale (destra) ora partono alla **stessa altezza** — prima il margine superiore del `.panel` di sinistra le disallineava.
- **[views/game/index.php](views/game/index.php)** — «Servizi del settore»: i tre paragrafi *run-on* diventano una `<dl class="side-stats">` con righe **«ETICHETTA: valore»** (Scafo, Moduli, Equipaggio, Risorse, Griglia EPS, Reputazione), ordinate e leggibili anche su mobile (`dt` a larghezza fissa, valori che vanno a capo).
- **[views/game/index.php](views/game/index.php)** — legenda della mappa stellare: aggiunti **«adiacente»** (anello ciano attorno ai settori raggiungibili) e **«preda Limpet»** (anello ambra) ora sempre presente come riferimento.
- **[sw.js](sw.js)** — cache `subspazio-v33`.

## 2026-09-10 — Tabella incontri (roadmap C1)

A ogni warp, con probabilità `encounter.chance` e rispettando un cooldown,
l'engine estrae un **incontro** compatibile col contesto (regione,
allineamento, esperienza, stive) e lo mette *in sospeso*: sulla plancia
compare un pannello con **2–3 scelte**, ognuna con esiti pesati ed
eventuale **skill-check d'equipaggio**. Il contenuto è data-driven:
aggiungere incontri = righe nella tabella `encounters`.

- **[db/migrations/0030_encounters.sql](db/migrations/0030_encounters.sql)**
  — tabelle `encounters` (template: `weight`, `conditions` JSON,
  `choices` JSON, `cooldown_min`, `once`, `enabled`) e
  `player_encounters` (stato per giocatore: pending / resolved /
  expired). Config `encounter.chance` (15), `encounter.cooldown_min`
  (10), `encounter.per_encounter_repeat_min` (720). **10 incontri di
  lancio** seminati, con echi di Mass Effect / Star Trek: TNG / Master of
  Orion.
- **[src/Game/Encounters.php](src/Game/Encounters.php)** — motore:
  `maybeSpawn()` (uno alla volta, cooldown globale + per-incontro, tiro
  `chance`, filtro `eligible()`, `once`, estrazione pesata),
  `pending()` per il pannello, `resolve()` (skill-check
  `skill officiale + d6 ≥ dc` con esiti taggati `if: pass|fail`,
  estrazione pesata, `applyEffects()` a **whitelist rigida e clampata**
  — crediti, esperienza, leghe, cristalli, componenti, allineamento,
  turni, reputazione di fazione, scudi, caccia, carico, modulo — più un
  riassunto leggibile), `expireStale()` (riparti senza scegliere →
  l'occasione svanisce), `gc()` (pending dimenticati oltre 6 h).
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `move()` dopo
  `onEnterSector`: `expireStale` + `maybeSpawn`, con un teaser negli
  entry-events.
- **[src/Controllers/GameController.php](src/Controllers/GameController.php)**
  / **[views/game/index.php](views/game/index.php)** — pannello
  `.encounter-card` sulla plancia (titolo, corpo, bottoni-scelta con
  «prova di <ruolo>» quando c'è uno skill-check).
- **[src/Controllers/EncounterController.php](src/Controllers/EncounterController.php)**
  / **src/routes.php** — `POST /gioco/incontro`.
- **[bin/tick.php](bin/tick.php)** — task `encounters_gc`.
- **[assets/css/app.css](assets/css/app.css)** — stili `.encounter-*`.
- **[sw.js](sw.js)** — cache `subspazio-v32`.

## 2026-09-10 — Tooltip su avatar/logo + logo visibile ovunque

- **[views/partials/media_hover.php](views/partials/media_hover.php)** —
  nuovo partial: avatar o logo con **anteprima ingrandita al passaggio del
  mouse o al focus** (CSS puro, `.media-hover::after` con la variabile
  `--pop`; stessa immagine, nessuna richiesta in più). `tabindex=0` per
  tastiera e tap.
- Il **logo di flotta** ora compare anche in **classifica**, in **«Altre
  navi qui»** e nel `player_tag` (piccolo, con hover).
  **[src/Game/Navigation.php](src/Game/Navigation.php)** e
  **[src/Game/Leaderboard.php](src/Game/Leaderboard.php)** espongono
  `has_logo` (LEFT JOIN su `media_assets` `kind='logo'` +
  `fileExists`).
- **[views/game/index.php](views/game/index.php)** — rimossa la lista
  «Navi:» **duplicata** dentro «Forze nel settore»: quel riquadro ora
  elenca solo caccia / mine / NPC; le presenze stanno in «Altre navi qui»
  in cima alla scheda settore.
- **[assets/css/app.css](assets/css/app.css)** — avatar 22→28 px («navi
  qui»), 24→30 px (status bar), 26→30 px (classifica); logo 1,9→2 rem
  (max 8,5 rem), cella «Flotta» 2,4 rem. Stili `.media-hover`.
- **[views/partials/player_tag.php](views/partials/player_tag.php)**,
  **[views/game/leaderboard.php](views/game/leaderboard.php)** — usano il
  nuovo partial.
- **[sw.js](sw.js)** — cache `subspazio-v30`.

## 2026-09-10 — Avatar e logo più grandi

- **[assets/css/app.css](assets/css/app.css)**, **[views/partials/player_tag.php](views/partials/player_tag.php)**, **[views/game/index.php](views/game/index.php)** — l'avatar passa da 15→22 px nella lista «navi qui» / forze, 20→26 px in classifica, 18→24 px nella status bar di plancia; il logo di flotta da 1,4→1,9 rem (max 7 rem). **[sw.js](sw.js)** → v29.

## 2026-09-10 — Guasti ai sottosistemi — roadmap B3

Un colpo incassato in combattimento — o il **sovraccarico della griglia
di potenza** (un canale EPS al massimo) — può mettere **OFFLINE** un
modulo installato: finché è fuori uso non fornisce i suoi effetti. Si
ripara allo **StarDock** (a pagamento), l'**Ingegnere** di bordo ne
rimette in linea sul tick, e dopo qualche ora un modulo si ripristina da
solo (jury-rig).

- **[db/migrations/0029_subsystems.sql](db/migrations/0029_subsystems.sql)**
  — `ship_modules.broken_at DATETIME NULL`; config `subsys.break_chance`
  (10), `subsys.strain_chance` (4), `subsys.repair_cost_each` (450),
  `subsys.auto_repair_hours` (18), `subsys.engineer_fix_chance` (25).
- **[src/Game/Subsystems.php](src/Game/Subsystems.php)** — motore:
  `maybeBreak()` (chance in base al colpo, ×2 se pesante; mette offline un
  modulo funzionante a caso), `maybeStrain()` (per warp, se un canale EPS
  è al massimo; mitigata dall'Ingegnere; preferisce il comparto stressato
  — armi→weapon, scudi→defense, motori→drive, sensori→computer),
  `brokenList`/`brokenCount`, `repairAll()` (StarDock, `repair_cost_each`
  cr a modulo), `tick()` (ripristino automatico oltre il TTL +
  l'Ingegnere di bordo rimette in linea un modulo per tick, con chance
  scalata dalla skill).
- **[src/Game/ShipStats.php](src/Game/ShipStats.php)** — `effective()`:
  un modulo `broken_at IS NOT NULL` **non contribuisce** agli effetti;
  nuovo `$ship['mod_broken']` (conteggio) e `broken` per ogni voce di
  `mod_list`.
- **[src/Game/Combat.php](src/Game/Combat.php)** — inneschi: `attackShip`
  (attaccante → nel messaggio; difensore → giornale di bordo), cannone
  Quasar, mine Armid, scontro coi caccia dispiegati in `onEnterSector`.
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `move()`
  innesca lo strain EPS dopo l'arrivo, con la skill dell'Ingegnere
  assegnato.
- **[bin/tick.php](bin/tick.php)** — nuovo task `subsystems`.
- **[src/Controllers/ShipyardController.php](src/Controllers/ShipyardController.php)**
  / **src/routes.php** — `POST /gioco/cantiere/riparazioni` →
  `Subsystems::repairAll`.
- **[views/game/index.php](views/game/index.php)** — striscia rossa in
  plancia quando hai moduli fuori uso.
- **[views/game/modules.php](views/game/modules.php)** — badge «fuori
  uso» e nome barrato sui moduli offline.
- **[views/game/shipyard.php](views/game/shipyard.php)** — sezione
  «Riparazioni» con l'elenco dei moduli fuori uso e il costo totale.
- **[views/game/eps.php](views/game/eps.php)** /
  **[assets/js/eps.js](assets/js/eps.js)** — nota «rischio di guasti per
  sovraccarico» sul canale EPS portato al massimo.
- **[assets/css/app.css](assets/css/app.css)** — `.subsys-warn`,
  `.repair-box`, `.module-row.broken`, `.eps-strain`.
- **[sw.js](sw.js)** — cache `subspazio-v28`.

## 2026-09-10 — Griglia di potenza (EPS) — roadmap B2

Il reattore fornisce un budget fisso di **8 tacche** da ripartire su
quattro canali — **Scudi / Armi / Motori / Sensori** — dove potenziarne
uno significa toglierlo a un altro. Ogni tacca di scostamento dal nominale
(2) sposta la stat del canale del 12,5%; a ±2 tacche fa ±25%. È una
decisione che prendi *prima* di uno scontro o di un viaggio; ri-tararla
costa 1 turno (tributo a «potenza agli scudi» di Star Trek).

- **[db/migrations/0028_eps.sql](db/migrations/0028_eps.sql)** — colonne
  `ships.eps_shields/eps_weapons/eps_engines/eps_sensors` (TINYINT,
  default 2); config `eps.pips_total` (8), `eps.step_pct` (12.5),
  `eps.realloc_turn_cost` (1). Idempotente.
- **[src/Game/PowerGrid.php](src/Game/PowerGrid.php)** — motore:
  `read/mults/validate/save/describe`. Nominale = pips_total/4 = 2, tetto
  per canale = 4; `mult(pips) = 1 + (pips − 2)·step_pct/100`. `save()`
  valida (somma esatta = 8, canale 0–4), costa 1 turno (0 se l'allocazione
  è identica), e **adegua subito capacità e carica degli scudi** al nuovo
  livello del canale Scudi. Vietato in capsula di salvataggio.
- **[src/Game/ShipStats.php](src/Game/ShipStats.php)** — `effective()`
  applica la griglia come **overlay finale**: Armi × `combat_rating`;
  Scudi × `max_shields` e rigenerazione; Motori ±1 `turns_per_warp` agli
  estremi (rilevante per scafi da 2+ turni/warp); Sensori sposta di un
  gradino lo scanner effettivo (`none↔density↔holo`). Espone
  `$ship['eps']` (descrittore per la UI) e `$ship['eps_nominal']`.
- **[src/Controllers/EpsController.php](src/Controllers/EpsController.php)**,
  **src/routes.php** — `GET`/`POST /gioco/eps`.
- **[views/game/eps.php](views/game/eps.php)** — pannello con quattro campi
  numerici 0–4; **[assets/js/eps.js](assets/js/eps.js)** li arricchisce con
  `+`/`−`, budget «potenza distribuita» live e anteprima degli effetti
  (progressive enhancement: senza JS i campi restano numerici e il server
  valida la somma).
- **[views/layout.php](views/layout.php)** — voce «Griglia» nella game-nav.
- **[views/game/index.php](views/game/index.php)** — striscia EPS
  read-only (🛡⚔🚀📡 + tacche) nel pannello laterale della plancia, con
  link «Rialloca».
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `look()` accetta
  la nave effettiva come 2° parametro, così lo scanner potenziato/declassato
  dalla griglia si riflette su cosa vedi nel settore; `GameController` e
  `GameApiController` aggiornati.
- **CSP**: la policy `script-src 'self'` blocca gli `<script>` inline.
  Per questo `eps.js` è esterno; nello stesso passaggio il preview live di
  **[views/game/profilo.php](views/game/profilo.php)** è stato spostato in
  **[assets/js/profile.js](assets/js/profile.js)**.
- **[assets/css/app.css](assets/css/app.css)** — stili `.eps-*`.
- **[sw.js](sw.js)** — cache `subspazio-v27`.

## 2026-09-10 — Fix: le immagini caricate vivono fuori dall'albero git

Avatar e logo caricati potevano sparire dopo un'operazione git o un
redeploy: `storage/uploads/` sta dentro il working tree ed è gitignorato
(solo `.gitkeep` tracciato), quindi un `git clean -fdx` o un checkout
pulito lo azzera.

- **[src/Game/MediaAsset.php](src/Game/MediaAsset.php)** —
  `uploadsRoot()` ora legge la chiave config **`paths.uploads`** se
  presente (in produzione va impostata a una dir **fuori dall'albero di
  git**, come il file di config; fallback a `<root>/storage/uploads` solo
  per lo sviluppo). `current()` verifica che il file esista ancora su
  disco: se manca ritorna `null`, così la plancia / `idchip` / classifica
  / «navi qui» **degradano allo stemma** invece di mostrare un'immagine
  rotta. Nuovi `fileExists()` e `flushCache()`. `forOwner()` espone un
  flag `missing`. `promote()` ritira il precedente approvato con una
  query DB (non più via `current()`, che ora filtra per file presente),
  evitando doppioni dopo un ri-upload.
- **[src/Game/Leaderboard.php](src/Game/Leaderboard.php)**,
  **[src/Game/Navigation.php](src/Game/Navigation.php)** — `has_avatar`
  controlla anche l'esistenza del file (`ma.path` + `fileExists`).
- **[views/game/profilo.php](views/game/profilo.php)** — se un'immagine
  approvata ha perso il file: badge «non disponibile» + invito a
  ricaricarla, invece di un'anteprima rotta.
- **[views/game/index.php](views/game/index.php)** — il logo di flotta
  passa a una **cella dedicata `.sb-logo`** nella status bar: prima era
  incastrato nella cella del nome, e quando l'immagine non caricava il
  suo `alt` sfondava il layout. Immagini decorative con `alt=""`.
- **[assets/css/app.css](assets/css/app.css)** — regole `.sb-logo` /
  `.fleet-logo`; `.sb-id .v` va a capo se stretto.
- **[config/config.example.php](config/config.example.php)** — blocco
  `paths.uploads` documentato.
- **[sw.js](sw.js)** — cache `subspazio-v25`.

## 2026-09-10 — Identità nelle liste + auto-approvazione admin (roadmap #1c)

Follow-up piccolo dell'identità: lo stemma (o l'avatar approvato) e il nome
colorato del comandante compaiono ora anche nelle liste di settore, non
solo in classifica e sulla propria plancia. E l'admin non deve più
approvare i propri upload.

- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `look()`:
  `players_here` porta `color`, `crest`, `has_avatar` (LEFT JOIN
  `media_assets` sull'avatar approvato).
- **[views/partials/player_tag.php](views/partials/player_tag.php)** —
  nuovo partial: etichetta compatta con avatar (se approvato) o stemma +
  nome colorato + `(tipo nave)` + 🛡 se in protezione novizio.
- **[views/game/index.php](views/game/index.php)** — sia «Altre navi qui»
  (scheda settore) sia «Forze nel settore → Navi:» usano `player_tag`.
- **[assets/css/app.css](assets/css/app.css)** — `.who-chip` ridisegnato
  `inline-flex` con emblema + nome.
- **Mappa stellare**: deciso di **non** mostrare un roster globale dei
  giocatori (romperebbe la nebbia di guerra / la tensione della caccia);
  l'unica identità sulla mappa resta l'anello ambra delle prede Limpet.
- **[src/Game/MediaAsset.php](src/Game/MediaAsset.php)** —
  `storeUpload($autoApprove)`: un upload fatto da un utente `role='admin'`
  entra direttamente `approved` (ritira il precedente via il nuovo
  `promote()`, `review_note='auto-approvato (admin)'`); `approve()`
  rifattorizzato per condividere `promote()`. `ensureDir()` ora forza
  `02775` (setgid + scrittura di gruppo) sui livelli sotto
  `storage/uploads/` e i file a `0664`, così web (`www-data`) e CLI
  (proprietario del repo, stesso gruppo via setgid) possono entrambi
  gestire i file.
- **[src/Controllers/ProfileController.php](src/Controllers/ProfileController.php)**
  — `uploadMedia` passa `Auth::isAdmin()`; messaggio flash differenziato
  («caricata e approvata» vs «in attesa di approvazione»).
- **[sw.js](sw.js)** — cache `subspazio-v24`.
- **Combattimento — tipi d'arma con profilo (#2): scartato.** Snatura il
  combattimento (nessuna agency nel momento — è una scelta di loadout
  pre-fatta; morra cinese a informazione nascosta con pochi giocatori;
  superficie di bilanciamento enorme senza telemetria; innesto di genere
  estraneo a TradeWars). Se in futuro si vorrà texture d'arma: un solo
  asse «penetrazione scudi» (piccolo), oppure priorità alla griglia di
  potenza EPS. I tipi d'arma veri hanno senso solo con le classi di nave
  (tema D). Vedi `docs/roadmap.md`.

## 2026-09-10 — Mine Limpet completate (roadmap post-beta, tema B)

Lo schema c'era dal `0007` (`ship_limpets`, `sector_mines.type='limpet'`,
`ships.mines_limpet`) ma nessuno lo leggeva: le Limpet si compravano e si
dispiegavano, poi restavano inerti. Ora funzionano: **non fanno danno**, si
agganciano allo scafo di chi entra nel settore e permettono al proprietario
di seguirne la posizione finché la preda non raggiunge lo StarDock (o la
mina scade).

- **[db/migrations/0027_limpet.sql](db/migrations/0027_limpet.sql)** — solo
  manopole in `game_config`: `limpet.ttl_hours` (12), `limpet.max_tracked`
  (15), `limpet.field_cap` (50). Idempotente.
- **[src/Game/Limpet.php](src/Game/Limpet.php)** — motore nuovo:
  - `onEnter()` — all'ingresso in un settore ogni campo Limpet non alleato
    aggancia **una** mina allo scafo (una per proprietario; non ne consuma
    una seconda se già agganciata; salta chi entra se alleato). Se il
    proprietario è al tetto di prede, stacca la più vecchia.
  - `scrape()` — allo StarDock i tecnici rimuovono tutte le Limpet.
  - `tracked()` — le prede che seguo, con posizione **live** (JOIN su
    `players.sector_id`: l'occultamento non nasconde dalle Limpet) e TTL
    residuo.
  - `tagCount()`, `trackedSectorIds()`, `gc()` (distacco automatico oltre
    `ttl_hours`, dal tick).
- **[src/Game/Combat.php](src/Game/Combat.php)** — `onEnterSector`: blocco
  Limpet subito dopo le mine Armid (nessun danno); lo scrape allo StarDock
  gira **prima** del `return` anticipato per lo spazio Federazione.
- **[src/Game/Deploy.php](src/Game/Deploy.php)** — `deployMines` applica il
  tetto `limpet.field_cap` per settore/proprietario.
- **[bin/tick.php](bin/tick.php)** — nuovo task `limpets_gc`.
- **[views/game/index.php](views/game/index.php)** — pannello «Prede
  tracciate» in plancia (handle, tipo nave, settore + pulsante Rotta, TTL
  residuo) e avviso «hai N mine Limpet agganciate» per chi è tracciato.
- **[src/Game/Navigation.php](src/Game/Navigation.php)** — `mapData()`
  espone `tracked` (id dei settori delle prede).
- **[assets/js/game.js](assets/js/game.js)** — la mappa stellare disegna un
  anello ambra tratteggiato sui settori dove si trova una preda; legenda
  aggiornata.
- **[assets/css/app.css](assets/css/app.css)** — `.limpet-card`,
  `.limpet-list`, `.limpet-warn`, marcatore di legenda.
- Giornale di bordo + `Live::alert` al proprietario a ogni aggancio;
  `combat_log` `kind='mines'`, `detail={"limpet":1}`.
- **[sw.js](sw.js)** — cache `subspazio-v23`.

## 2026-09-10 — Avatar e logo di flotta caricati (roadmap post-beta, slice #1b)

Seconda slice del tema Identità: i comandanti possono caricare un avatar
personale e un logo di flotta, che passano da una **coda di approvazione
dell'amministratore** prima di diventare visibili agli altri (stesso
schema mentale della coda iscrizioni).

- **[db/migrations/0026_media.sql](db/migrations/0026_media.sql)** — tabella
  `media_assets`: `owner_type`/`owner_id`/`kind` (avatar|logo),
  `status` (pending|approved|rejected), `mime`/`ext`/`bytes`/`width`/
  `height`/`sha1`/`path`, campi di revisione. Idempotente.
- **[src/Game/MediaAsset.php](src/Game/MediaAsset.php)** — motore:
  `storeUpload()` valida tipo (PNG/JPEG/WebP/GIF), peso (il minimo fra il
  valore di config e `upload_max_filesize`/`post_max_size` del php.ini) e
  dimensioni (32–4096 px per lato), poi **ricodifica con GD** in PNG —
  l'avatar ritagliato quadrato ≤512, il logo a proporzioni ≤512 — così da
  spogliare EXIF e payload nascosti. I file finiscono in
  `storage/uploads/` (fuori dal web). `approve()` promuove e ritira
  l'approvato precedente; `reject($note)` archivia con motivazione;
  `removeOwn()` per il proprietario; `current()` restituisce **solo**
  l'asset approvato; `queue()`/`forOwner()` alimentano le UI. `purgeFile()`
  non cancella un file ancora referenziato da un'altra riga (dedup sha1).
- **[src/Core/Request.php](src/Core/Request.php)** — `file()`: accesso
  tipizzato a una voce di `$_FILES`.
- **[src/Controllers/MediaController.php](src/Controllers/MediaController.php)**
  — serve le immagini (i file non sono raggiungibili via URL diretto):
  `GET /media/c/{id}/{kind}` (solo approvato, `public, max-age=300`, ETag +
  `304`), `GET /gioco/profilo/media/{kind}` (il proprio pending, `private`),
  `GET /admin/media/{id}/file` (qualsiasi asset, solo admin, per la
  moderazione).
- **[src/Controllers/ProfileController.php](src/Controllers/ProfileController.php)**
  / **[views/game/profilo.php](views/game/profilo.php)** — sezione
  «Immagini caricate»: upload per avatar e logo, anteprima dello stato
  (approvato / in attesa), motivo dell'eventuale rifiuto, rimozione. Rotte
  `POST /gioco/profilo/media` e `.../media/rimuovi`.
- **[src/Controllers/AdminController.php](src/Controllers/AdminController.php)**
  / **[views/admin/dashboard.php](views/admin/dashboard.php)** — riquadro
  «Immagini in attesa» con anteprima e Approva/Rifiuta (con motivazione
  via `prompt`); azioni tracciate nell'audit log (`media.approve`,
  `media.reject`). Rotte `POST /admin/media/{id}/approva|rifiuta`.
- **[views/partials/idchip.php](views/partials/idchip.php)** — l'avatar
  approvato sostituisce lo stemma nella targhetta d'identità (quindi in
  plancia e classifica); parametro `avatar` per forzare/saltare la
  risoluzione.
- **[src/Game/Leaderboard.php](src/Game/Leaderboard.php)** /
  **[views/game/leaderboard.php](views/game/leaderboard.php)** —
  `topPlayers()` fa `LEFT JOIN` su `media_assets` ed espone `pid` +
  `has_avatar`; la classifica mostra l'avatar o lo stemma.
- **[views/game/index.php](views/game/index.php)** — status bar di plancia:
  avatar al posto dello stemma quando approvato, logo di flotta accanto al
  nome del comandante.
- **[assets/css/app.css](assets/css/app.css)** — `.idchip-avatar`,
  `.ld-avatar`, `.fleet-logo`, `.media-slots`/`.media-slot`/`.media-preview`
  (editor), `.media-review`/`.media-review-card` (coda admin).
- **[config/config.example.php](config/config.example.php)** — blocco
  `media` (`max_bytes`).
- **[.gitignore](.gitignore)** — `storage/uploads/*` (solo `.gitkeep`
  versionato).
- **[sw.js](sw.js)** — cache `subspazio-v22`.

## 2026-09-10 — Identità del comandante (roadmap post-beta, slice #1)

Prima slice della roadmap post-beta: personalizzazione leggera del
comandante e della flotta, tutta agganciata a sistemi già esistenti
(`Ranks`, `Faction`, classifiche, plancia).

- **[db/migrations/0025_identity.sql](db/migrations/0025_identity.sql)** —
  nuove colonne: `players.color/crest/motto`, `ships.registry`,
  `corporations.color/crest` (queste ultime pronte per lo slice corp).
  Idempotente (`ADD COLUMN IF NOT EXISTS`).
- **[src/Game/Identity.php](src/Game/Identity.php)** — nuovo motore:
  `PALETTE` di 12 accenti curati, `CRESTS` di 12 stemmi, `save()` con
  validazione (colore in palette, stemma nell'elenco, motto ≤ 80,
  nome nave ≤ 40, registro `^[A-Z0-9-]{1,16}$` reso maiuscolo, salta
  nave/registro se sei in capsula), `title()` **derivato** da grado +
  tier di fazione (suffisso «Fuorilegge» se ostile alla Fed, «Alleato …»
  al tier massimo).
- **[src/Controllers/ProfileController.php](src/Controllers/ProfileController.php)**
  — `show`/`save` per `/gioco/profilo`.
- **[src/routes.php](src/routes.php)** — rotte `GET`/`POST /gioco/profilo`.
- **[views/partials/crest_sprite.php](views/partials/crest_sprite.php)** —
  12 `<symbol>` SVG (viewBox 0 0 24 24, disegnati con `currentColor`),
  inclusi una volta nel layout per gli utenti attivi.
- **[views/partials/crest.php](views/partials/crest.php)**,
  **[views/partials/idchip.php](views/partials/idchip.php)** — stemma e
  targhetta d'identità riusabili.
- **[src/Support/helpers.php](src/Support/helpers.php)** — helper
  `partial($name, $data)` → `View::renderPartial('partials/'.$name)`.
- **[views/game/profilo.php](views/game/profilo.php)** — editor con
  anteprima live (colore, stemma, motto, nome + registro nave).
- **[views/layout.php](views/layout.php)** — sprite degli stemmi + voce
  «Profilo» nella game-nav.
- **[views/game/index.php](views/game/index.php)** — status bar di
  plancia: stemma + nome comandante colorato (link al profilo), cella
  «Nave» con nome + registro.
- **[src/Game/Leaderboard.php](src/Game/Leaderboard.php)** /
  **[views/game/leaderboard.php](views/game/leaderboard.php)** —
  `topPlayers()` espone `color`/`crest`; la classifica mostra lo stemma e
  il nome colorato.
- **[assets/css/app.css](assets/css/app.css)** — `.crest`, `.idchip`,
  griglie `.swatch-grid`/`.crest-grid` per l'editor.
- **[sw.js](sw.js)** — cache `subspazio-v21`.
- **[docs/roadmap.md](docs/roadmap.md)** — roadmap post-beta formalizzata
  (temi A–E, sequenza in 5 fasi, metodo, stato).

## 2026-09-04 — Pagina di accesso: elenco funzionalità aggiornato

L'elenco «Cosa c'è nel gioco» sulla home si era fermato al nucleo
(Fasi 1–6) e non rifletteva più il gioco reale.

- **[views/home.php](views/home.php)** — elenco esteso ai sistemi
  post-roadmap: equipaggio, scansione & frontiera, fazioni & reputazione,
  industria & produzione, moduli, giornale di bordo & rientro, primi
  passi. 14 voci raggruppate, intro «Sedici sistemi, tutti già attivi in
  questa beta». Tolto «installabile come app» dalla voce Tecnologia
  (l'installazione PWA richiede HTTPS). Aggiunta la riga «In beta testing»
  sotto le azioni di registrazione.

## 2026-09-04 — README: ritmo del gioco, ispirazioni, stato beta

Il README copriva già le 16 meccaniche; aggiunte le parti che mancavano
rispetto alla presentazione per i beta-tester.

- **[README.md](README.md)** — nuova sezione **«Come si gioca»** (flusso
  registrazione → approvazione → primo comandante → protezione novizio;
  ritmo doppio turni + tick; rientro; stagioni) e **«Ispirazioni»**
  (TradeWars 2002, OGame, Master of Orion, Star Trek: TNG, Mass Effect).
  Riga di stato «in beta testing»; nota di onestà sulla PWA (installazione
  e offline richiedono HTTPS, non attivo).

## 2026-09-04 — Fix: Radio subspaziale illeggibile su mobile

Segnalato dall'utente: la voce «Radio» del menu di gioco appariva confusa
su smartphone. Il testo dei messaggi era schiacciato in una colonna di
soli 48px invece di occupare la riga.

Causa: nel blocco `@media (max-width: 820px)`, `.radio-log li` passa da 4
a 2 colonne (`3rem 1fr`), ma con 3 figli visibili (canale, mittente, corpo
— l'orario è `display:none`) il grid auto-piazzava il corpo del messaggio
nella prima colonna della seconda riga, cioè negli stessi 48px del
canale.

- **[assets/css/app.css](assets/css/app.css)** — `.radio-log .rl-body {
  grid-column: 1 / -1; }` nel blocco mobile: il corpo del messaggio va a
  capo su tutta la larghezza, sotto la riga canale + mittente. Verificato
  in browser a 375px: `rl-body` passa da 48px a 319px (piena larghezza),
  nessuno sforo di pagina.
- Rimossa anche una regola morta e contraddittoria: `.radio-compose .row`
  compariva sia nell'elenco che forza `flex-direction: column` sia, poco
  sotto, in una regola separata che lo rimetteva a `row; flex-wrap: wrap`
  — quest'ultima vinceva per ordine. L'effetto era mascherato da
  `.row label { width: 100% }`, ma il codice era inutilmente
  contraddittorio; il campo «A» (per hail/privato) ora si impila
  correttamente sotto «Canale».
- [sw.js](sw.js): `v19` → `v20`.

## 2026-09-04 — Notifica e-mail per le richieste di iscrizione

Quando un utente si registra (`status='pending'`) l'amministratore riceve
una e-mail. L'invio parte **dal tick**, mai dal percorso della richiesta:
un errore SMTP non tocca il form pubblico.

- **[db/migrations/0024_notify.sql](db/migrations/0024_notify.sql)** —
  `users.reg_notified_at` (coda implicita: `NULL` = da segnalare).
- **[src/Core/Mailer.php](src/Core/Mailer.php)** — client SMTP minimale
  senza dipendenze: STARTTLS su 587 (o TLS implicito su 465), `AUTH LOGIN`,
  testo semplice, un destinatario. `transport='log'` scrive su
  `storage/logs` invece di inviare.
- **[src/Game/Notifier.php](src/Game/Notifier.php)** — `tick()` legge i
  `pending` non ancora segnalati e, secondo `notify.new_registration_mode`:
  `immediate` = una mail per richiesta; `digest` (default) = una mail
  cumulativa quando la richiesta più vecchia supera
  `notify.digest_delay_min` minuti. Marca `reg_notified_at` solo sugli
  invii riusciti; rispetta il toggle `notify.new_registration`.
- **[bin/tick.php](bin/tick.php)** — task `notify`.
- **[config/config.example.php](config/config.example.php)** —
  `app.public_url` (link nelle e-mail) e `notify.digest_delay_min`. Il
  config reale fuori dal DocumentRoot ha le credenziali Brevo (riuso
  dell'account del forum phpBB sullo stesso server).

## 2026-09-03 — Meccaniche di attesa

Cose che maturano nel tempo reale sul tick + un riassunto di cosa è
successo mentre eri via. **Nessun timer di volo**: il warp resta
istantaneo, il costo è il turno.

**Rapporto di rientro**

- **[db/migrations/0023_waiting.sql](db/migrations/0023_waiting.sql)** —
  `players.last_digest_at`.
- **[src/Game/Digest.php](src/Game/Digest.php)** — `forView()` compone un
  riassunto (voci di giornale accumulate col conteggio dei critici, turni
  ricaricati se è passato il ciclo giornaliero, colonie che hanno
  prodotto, lavori d'Officina completati, contratti scaduti) quando sei
  stato via oltre `digest.min_away_min` (20 min); `null` se sotto soglia o
  se non è successo nulla. Si vede una volta per assenza.
- Pannello «Rapporto di rientro» in cima alla plancia
  (**[views/game/index.php](views/game/index.php)**,
  **[src/Controllers/GameController.php](src/Controllers/GameController.php)**).

**Lavori dell'Officina a tempo**

- **[db/migrations/0023_waiting.sql](db/migrations/0023_waiting.sql)** —
  tabella `craft_jobs` + config `craft.max_jobs` (3) e `craft.job_minutes`
  per rarità (`civ:4,mil:12,exp:35,xeno:90,precursor:180`).
- **[src/Game/Industry.php](src/Game/Industry.php)** — `craft()` non
  consegna più il modulo all'istante: scala i costi subito e mette in coda
  un lavoro che matura fra N minuti. `craftJobs()` (lista), `cancelJob()`
  (rimborso pieno dei materiali, non dei turni), `craftJobsTick()`
  (consegna i moduli maturati + voce di giornale «Officina: … completato»
  + alert). Nuovo task tick `craft_jobs`; rotta
  `POST /gioco/moduli/annulla-lavoro`.
- **[views/game/modules.php](views/game/modules.php)** — sezione «Officina
  — lavori in corso» (visibile ovunque, i lavori proseguono lontano dallo
  StarDock; l'avvio è solo al dock); il bottone ricetta diventa «Avvia».
  Striscia «Officina» in plancia col conteggio lavori/pronti.
- **[assets/css/app.css](assets/css/app.css)** — `.digest-card` /
  `.officina-strip` / `.craft-jobs`. [sw.js](sw.js): `v18` → `v19`.

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
