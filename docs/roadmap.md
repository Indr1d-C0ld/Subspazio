# SubSpazio — Roadmap post-beta

Consolidato delle integrazioni discusse a settembre 2026, dopo il completamento
della roadmap del clone TW2002 e delle evoluzioni post-roadmap (Fasi 7–11 +
onboarding + giornale di bordo + meccaniche di attesa + notifica iscrizioni) e
l'apertura del beta testing.

**Principio guida:** ogni cosa passa dai verbi che il gioco ha già — turni
(costo), tick (il mondo avanza), plancia (dove decidi), moduli/equipaggio
(costruisci), fazioni (conseguenze), giornale di bordo (narrazione), scansione
(scoperta), corp (sociale). Il più possibile **data-driven** (incontri, profili
d'arma, nodi tecnologici, operazioni) così il contenuto cresce senza nuova
impalcatura.

Dimensioni indicative: **S** ≈ una iterazione, **M** ≈ 2–3, **L** ≈ progetto a
sé.

---

## A · Identità del comandante e della flotta

| Voce | Dim. | Aggancio |
|---|---|---|
| Colori personali (marker mappa, messaggi radio, bordo stemma) + colori di corp | S | star map, radio, corp |
| Stemma di flotta da un set curato di sigilli; stemma di corp | S | classifiche, «navi qui», replay, roster |
| Titoli/onorificenze **derivati** da grado, tier di fazione, traguardi | S | `Ranks`, `Faction`, `Achievements` |
| Nome + **registro** nave (stile NCC-…) + livrea scafo | S | `ships.name` (già presente) |
| Callsign / motto sull'hail | S | radio |
| Avatar + logo **caricati** (limiti dimensione/tipo + approvazione admin) | M | riusa la coda di approvazione iscrizioni |

## B · Profondità tattica del combattimento

| Voce | Dim. | Aggancio |
|---|---|---|
| **Tipi d'arma con profilo** (cinetico / energia / dispersione: penetrazione scudi, danno vs caccia, alpha strike) al posto del `combat_pct` piatto | M | `item_types.effects`, `Combat::duel()` |
| **Griglia di potenza (EPS)** — ripartisci il reattore fra scudi / armi / motori / sensori / supporto | M | overlay su `ShipStats::effective`; dà all'Ingegnere un «sovraccarico» vero |
| **Guasti ai sottosistemi** — un colpo mette offline un modulo; ripari allo StarDock o via Ingegnere sul tick; mira ai sottosistemi come opzione d'attacco | M | `combat_log`, giornale, Officina |
| **Finire le mine Limpet** ✅ fatto — 2026-09-10 — si agganciano allo scafo di chi passa e ti fanno tracciare la preda finché non raggiunge lo StarDock | S–M | `ship_limpets`, star map |

## C · Rigiocabilità e contenuto

| Voce | Dim. | Aggancio |
|---|---|---|
| **Tabella incontri** ✅ fatto — 2026-09-10 (C1) — `encounters` data-driven, tiro a ogni warp, 2–3 scelte con esiti pesati + skill-check; 10 incontri di lancio | M motore + S/incontro | `Navigation::move`, plancia, giornale |
| **Canale FedNews / Frontier Broadcast** ✅ fatto — 2026-09-10 (C3) — bollettino quotidiano nella Radio da stato reale, conduttore NPC che ruota | S–M | radio, `Events`, threat clock |
| **NPC nominati ricorrenti** — capitani con stato, taglia, dialogo; scalano a stagione | M | `Npc`, `Contracts` |
| **Operazioni a tempo** — obiettivi settimanali seminabili dall'admin, barra condivisa, ricompense | M | tick, plancia, `/admin/gioco` |
| **Anomalia della stagione** — una feature con meccanica unica che ruota ogni stagione | M | `SectorFeatures`, `Season` |

## D · Meta di lungo periodo

| Voce | Dim. | Aggancio |
|---|---|---|
| **Trasportatore & abbordaggio** — cattura carico/modulo/nave, beam-out d'emergenza, recupero a distanza, sbarco truppe | M–L | moduli (rarità Xeno/Precursore o gate tecnologico), away missions, assalto planetario |
| **Albero tecnologico** persistente (account o corp) — punti dallo studio delle anomalie; sblocca classi di scafo, trasportatore, occultamento efficiente, prototipo | L | `Codex`, Cantiere, `Corp` |
| **Classi di nave con un ruolo** (scout / corriere / portaerei / scientifica / dreadnought) — stat d'identità, non solo numeri | M | `ship_types` |
| **Controllo territoriale per le corp** — reclami, rinomini, tassi, difendi; classifica territorio | L | `Planets`, `Deploy`, `Corp`, star map |
| **Diplomazia fra corp** — trattati, patti di non aggressione, operazioni congiunte, consiglio galattico che vota un modificatore di stagione | M–L | `Corp`, alleanze (`Corp::areMates`) |
| **Prestige / ritiro** — ritiri un comandante ad alto grado per un perk permanente + muro d'onore | S–M | `Season`, `Achievements` |

## E · La minaccia galattica stagionale — il tetto

Già progettata, rimandata «in attesa di più giocatori». È il **capostipite**:
threat clock, settori corrotti che si espandono, pagina guerra, finale co-op. Si
alimenta di quasi tutto il resto — incontri-minaccia (C), operazioni-minaccia
(C), narrazione via FedNews (C), classi/tecnologie per prepararsi (D). Chiude la
stagione con un esito che modifica la successiva. **L**

---

## Sequenza

1. **Fase 1 — Identità (A).** Piccola, zero rischio di bilanciamento, massimo
   ritorno sociale con i primi tester. Colori + stemmi + titoli + registro nave
   subito; avatar/logo caricati come coda separata.
2. **Fase 2 — Combattimento (B), ~3 slice.** B1 tipi d'arma → B2 griglia di
   potenza → B3 guasti ai sottosistemi → B4 limpet.
3. **Fase 3 — Contenuto (C), in parallelo a B.** Prima il motore della tabella
   incontri + FedNews, poi NPC nominati e operazioni.
4. **Fase 4 — Meta (D).** Trasportatore → classi di nave → albero tecnologico →
   territorio/diplomazia → prestige.
5. **Fase 5 — Minaccia stagionale (E).** Quando c'è una popolazione vera e i
   pezzi di B/C/D la possono alimentare.

## Metodo

Una slice coerente per iterazione: migrazione `00NN` idempotente, classe engine
in `src/Game/`, rotta + controller + view + CSS, test e2e crash-safe, lint,
commit live + `sync_subspazio.sh --push`, CHANGELOG a mano nel repo pubblico,
bump `sw.js` se cambiano asset, memoria aggiornata. Passata di bilanciamento
dopo ogni cluster.

---

## Stato

| Slice | Stato |
|---|---|
| **#1 — Identità del comandante** (colori, stemma, motto, titolo derivato, nome + registro nave) | fatto — 2026-09-10 |
| **#1b — Avatar + logo caricati** (coda di approvazione admin, riuso della coda iscrizioni) | fatto — 2026-09-10 |
| **#1c — Identità nelle liste** (stemma/avatar + nome colorato in «navi qui» / «Forze nel settore»; auto-approvazione upload admin) | fatto — 2026-09-10 |
| **B — Mine Limpet completate** (aggancio allo scafo + tracking preda, rimozione allo StarDock) | fatto — 2026-09-10 |
| **B2 — Griglia di potenza (EPS)** (8 tacche su 4 canali Scudi/Armi/Motori/Sensori, ±25%, ri-taratura = 1 turno) | fatto — 2026-09-10 |
| **B3 — Guasti ai sottosistemi** (un colpo mette offline un modulo; StarDock / Ingegnere / auto-riparazione; sovraccarico EPS come 2ª fonte) | fatto — 2026-09-10 |
| **C1 — Tabella incontri** (`encounters` data-driven, tiro a ogni warp, 2–3 scelte + skill-check, 10 incontri di lancio) | fatto — 2026-09-10 |
| **C3 — FedNews / Frontier Broadcast** (bollettino quotidiano nella Radio da stato reale) | fatto — 2026-09-10 |
| **Dispositivi-fantasma — Occultamento + Transwarp** (meccanica reale a due hardware finora inerti) | fatto — 2026-09-10 |
| **Rifiniture identità/UI** — iconcine sui beni · marca di flotta più varia (6 stemmi + 13 sagome di nave) | fatto — 2026-09-10 |
| **Illustrazioni dei modelli di nave** — `ship_art()` in plancia/classifica/navi qui/Cantiere; fallback alla sagoma SVG finché mancano i PNG | fatto — 2026-09-11 |
| **Aiuto contestuale «?»** — `Help` + partial in CSS puro su ~55 intestazioni di sezione | fatto — 2026-09-11 |
| **fix FedNews** — doppio bollettino ravvicinato (e2e distruttivo vs cron) | fatto — 2026-09-11 |
| **C — resta** | NPC nominati ricorrenti · operazioni a tempo · anomalia della stagione |
| **Coerenza distanze/tempi** — discussa 2026-09-11 (analisi sotto). **Accantonata su decisione dell'utente**: nessuna delle leve A/B/C/E viene perseguita. |
| **#2 — Combattimento B1: tipi d'arma con profilo** | ~~scartato~~ — non si fa: snatura il combattimento (nessuna agency nel momento, morra cinese a info nascosta con pochi giocatori, superficie di bilanciamento enorme). In alternativa, se in futuro si vuole texture d'arma: un solo asse «penetrazione scudi» (S). I tipi d'arma veri hanno senso solo con le classi di nave (tema D). |

### Tema B — completo

I tipi d'arma con profilo restano fuori (vedi sopra). Se in futuro si vuole
tornare sul combattimento: un solo asse «penetrazione scudi» (S), oppure
aspettare le classi di nave del tema D.

### B3 — dettaglio di quanto consegnato

- Migrazione `0029_subsystems` — `ship_modules.broken_at DATETIME NULL`;
  config `subsys.break_chance` 10, `strain_chance` 4, `repair_cost_each` 450,
  `auto_repair_hours` 18, `engineer_fix_chance` 25.
- `src/Game/Subsystems.php` — `maybeBreak()` (chance × colpo incassato, ×2 se
  pesante; mette offline un modulo funzionante a caso), `maybeStrain()`
  (per warp, se un canale EPS è al massimo; mitigata dall'Ingegnere; preferisce
  il comparto stressato), `brokenList/brokenCount`, `repairAll()` (StarDock, a
  pagamento), `tick()` (auto-riparazione oltre il TTL + l'Ingegnere di bordo
  rimette in linea un modulo per tick).
- `ShipStats::effective()` — un modulo `broken_at IS NOT NULL` **non
  contribuisce** agli effetti; espone `$ship['mod_broken']` e `broken` per
  ogni voce di `mod_list`.
- Innesti in `Combat`: `attackShip` (attaccante → flash, difensore → giornale),
  Quasar, mine Armid, scontro coi caccia in `onEnterSector`. Innesto EPS-strain
  in `Navigation::move`. Task `subsystems` in `bin/tick.php`.
- UI: striscia rossa in plancia se hai moduli fuori uso; badge «fuori uso» +
  barrato nella pagina Moduli; sezione «Riparazioni» al Cantiere con
  `POST /gioco/cantiere/riparazioni`; nota «rischio di guasti per
  sovraccarico» sul canale EPS al massimo. `sw.js` → v28.
- e2e `scratchpad/test_subsys.php` (17 check).

### C1 — dettaglio di quanto consegnato

- Migrazione `0030_encounters` — tabelle `encounters` (template data-driven:
  `weight`, `conditions` JSON, `choices` JSON, `cooldown_min`, `once`,
  `enabled`) e `player_encounters` (pending/resolved/expired per giocatore);
  config `encounter.chance` 15, `encounter.cooldown_min` 10,
  `encounter.per_encounter_repeat_min` 720. **10 incontri di lancio** seminati
  (tono ME / TNG / MoO).
- `src/Game/Encounters.php` — `maybeSpawn($player)` (uno alla volta, cooldown
  globale, tiro `chance`, filtro `eligible()` su regione/allineamento/exp/stive,
  cooldown per-incontro, `once`, estrazione pesata → riga pending);
  `pending($pid)` per il pannello; `resolve($pid,$choice)` (skill-check
  `officerSkill + d6 ≥ dc` → esiti taggati `if:pass|fail`, estrazione pesata,
  `applyEffects()` con whitelist rigida — crediti/exp/leghe/cristalli/
  componenti/allineamento/turni/fazioni/scudi/caccia/carico/modulo — tutto
  clampato, riassunto leggibile); `expireStale()` (riparti senza scegliere →
  il pending scade); `gc()` (pending dimenticati > 6h).
- `Navigation::move` — dopo `onEnterSector`: `expireStale` + `maybeSpawn`,
  teaser negli entry-events. `GameController::index` passa `encounter`.
- `views/game/index.php` — pannello `.encounter-card` (titolo, corpo, bottoni
  scelta con «prova di <ruolo>» se skill-check). `EncounterController` +
  `POST /gioco/incontro`. Task `encounters_gc` in `bin/tick.php`. `sw.js` → v32.
- e2e `scratchpad/test_encounters.php` (12 check) + smoke HTTP (pannello +
  resolve «spoglia» → +40 leghe, giornale).
- **Aggiungere incontri = righe SQL in `encounters`** (o un form admin, da fare).

### C3 — dettaglio di quanto consegnato

- Migrazione `0031_fednews` — tabella `fednews` (archivio: `anchor`,
  `headlines` JSON, `body`, `created_at`); config `fednews.enabled` 1,
  `fednews.interval_hours` 24.
- `src/Game/FedNews.php` — `tick()` (se è passato l'intervallo dall'ultimo,
  compone e pubblica); `compose()` pesca da stato reale: evento di mercato
  attivo (`events`), kill PvP recente (`combat_log`), nuova colonia
  (`planets.created_at`), vertice classifica, nuove registrazioni, + una riga
  di servizio fissa; se è tutto tranquillo, una riga «rotte sgombre».
  `latest()` per il teaser (null se più vecchio di 2× l'intervallo).
- Il bollettino esce come messaggio Radio sul canale `fedcomm`
  (`from_name` = conduttore che ruota fra 3), quindi compare nella Radio
  con badge. Teaser `.fednews-card` in cima alla plancia (fino a 4 titoli +
  link alla Radio). Task `fednews` in `bin/tick.php`. `sw.js` → v34.
- e2e `scratchpad/test_fednews.php` (9 check).

### Aiuto contestuale «?» — dettaglio di quanto consegnato

- `src/Game/Help.php` — registro `TEXT` (chiave `<schermata>.<slug>` → una riga).
- `views/partials/help.php` — marcatore «?» con tendina in **CSS puro**
  (hover/focus, niente JS → CSP-safe); non rende nulla se la chiave manca.
- CSS `.help`/`.help-q`/`.help-pop` (theme-aware, si ribalta a destra < 640 px).
- Applicato a ~55 intestazioni di sezione in tutte le schermate di gioco
  (plancia, porto, banca, EPS, Cantiere, moduli, equipaggio, missioni,
  contratti, pianeti, corp, radio, classifica, fazioni, codex, registri,
  traguardi, albo, mercato nero, profilo). I tasti ereditano il contesto
  dalla «?» della loro sezione. `sw.js` → v38.

### Illustrazioni dei modelli di nave — dettaglio di quanto consegnato

- Helper `ship_art($typeKey)` in `helpers.php`: URL di
  `assets/ships/<ckey>.png` se il file esiste (chiave sanificata, `is_file`
  in cache per-richiesta), altrimenti `null`. `assets/ships/README.md`
  elenca i 13 nomi attesi.
- `views/partials/ship_art.php`: `<img.ship-art>` se il PNG c'è, altrimenti
  `partial('crest', ['crest' => 'nave:<type>'])` (sagoma vettoriale).
- `Navigation::look` players_here → `ship_key`; `Leaderboard::topPlayers`
  JOIN ships/ship_types → `ship_key`, `ship_type`.
- Usato in `player_tag.php` (navi qui/forze), `leaderboard.php`,
  `index.php` (riga «Scafo» pannello laterale), `shipyard.php` (catalogo).
- CSS `.ship-art`, `.hull-line`. **In attesa dei 13 PNG dall'utente**
  (trasparenti, quadrati, in `assets/ships/`).

### fix FedNews — doppio bollettino

- Causa: `test_fednews.php` azzerava `fednews` e girava insieme al cron; la
  guardia di `FedNews::tick()` guardava solo l'ultima riga d'archivio.
- Fix: seconda guardia sull'ultimo messaggio `fedcomm` con corpo
  `NOTIZIARIO DELLA FEDERAZIONE%` (sopravvive al troncamento di `fednews`).
  Test e2e riscritto: prende `storage/tick.lock`, non distruttivo. Duplicato
  sul live ripulito a mano.

### Rifiniture identità/UI — dettaglio di quanto consegnato

- **Iconcine sui beni** (`Economy::ICONS`/`icon()`/`labelIcon()`): ⛏️ Minerale ·
  🌿 Organico · 🔧 Equipaggiamento · 👥 Coloni. In: porto (colonna Merce),
  teaser porto in plancia, tabella produzione pianeta, «Coloni a bordo»,
  select merce di mercato nero e contratti, lista contratti di consegna.
  Cella «Stive» della barra di stato con mini-riepilogo del carico a bordo.
  `sw.js` → v36.
- **Marca di flotta più varia**: `Identity::CRESTS` +6 (teschio, fenice,
  ancora, fulmine, nova, chiave); `SHIP_MARKS`/`SHIP_MARK_LABELS` = le 13
  `ship_types.ckey` selezionabili come marca col valore `nave:<ckey>`.
  Nuovo `views/partials/ship_sprite.php` (13 `<symbol>` profilo laterale,
  `currentColor`), incluso nel layout dopo `crest_sprite`.
  `Identity::validCrest()`/`symbolId()`/`crestLabel()`; `crest()`/`save()`
  accettano le sagome. Migrazione `0033_crest_marks` (`players.crest` e
  `corporations.crest` `VARCHAR(24)`→`(32)`). Editor profilo con
  sotto-griglia «Sagome di nave» + anteprima live (`profile.js` gestisce
  il prefisso `nave:`). `crest.php`/`player_tag.php` risolvono la marca
  via `symbolId`/`validCrest`. Silhouette nel catalogo del Cantiere.
  CSS `.crest-subhead`/`.ship-mark`. `sw.js` → v37.

### Dispositivi-fantasma — dettaglio di quanto consegnato

- Migrazione `0032_devices` — `ships.cloaked TINYINT`; config
  `cloak.warp_turn_penalty` 1, `cloak.fedspace_forbidden` 1,
  `transwarp.turn_cost` 5.
- `src/Game/Cloak.php` — `has()`, `isActive()`,
  `seesCloaked($scanner)` (vero solo con scanner olografico),
  `toggle($player,$ship,$on)` (rifiuta in Fedspace, ShipLog),
  `drop($shipId,$reason)` (decloak forzato, torna true se era occultata).
- **Occultamento**: da cloaked sei fuori dai sensori — ti vede solo chi ha
  scanner olografico nel tuo settore (`Navigation::look` filtra i cloaked),
  e in `Combat::onEnterSector` scivoli oltre caccia schierati e NPC senza
  ingaggio. In cambio: `+cloak.warp_turn_penalty` a `turns_per_warp`
  (`ShipStats::effective`), vietato in Fedspace, non ferma mine né Quasar,
  e **cade** se apri il fuoco (`attackShip/Port/Npc`), dispieghi
  (`Deploy`), attracchi allo StarDock o entri in Fedspace
  (`onEnterSector`), o salti in Transwarp. `attackShip` rifiuta comunque
  un bersaglio occultato.
- **Transwarp**: `Navigation::transwarp($player,$ship,$toSector)` — salto
  diretto a un settore già in `player_visited_sectors`, ignora le rotte,
  costo fisso `transwarp.turn_cost` turni, `move_log.mode='transwarp'`,
  smaschera. Estratta `Navigation::arrive()` (sequenza post-arrivo:
  intercettazioni, strain EPS, incontri, live, giornale) condivisa con
  `move()`.
- Rotte `POST /gioco/occulta` (`GameController::cloak`) e
  `POST /gioco/transwarp` (`GameController::transwarp`).
- UI plancia: toggle in «Armi e dispiegamento», form Transwarp in
  «Computer di bordo», badge «🌫 Occultato» nella barra di stato, note
  d'aiuto. Sezione «Occultamento & Transwarp» nella guida in-game.
  `sw.js` → v35.
- e2e `scratchpad/test_devices.php` (29 check, tutto verde).

### B2 — dettaglio di quanto consegnato

- Migrazione `0028_eps` — `ships.eps_shields/eps_weapons/eps_engines/eps_sensors`
  (TINYINT, default 2); config `eps.pips_total` 8, `eps.step_pct` 12.5,
  `eps.realloc_turn_cost` 1.
- `src/Game/PowerGrid.php` — `read/mults/validate/save/describe`. Nominale =
  pips_total/4 = 2; max per canale = 4; `mult(pips) = 1 + (pips-2)·12.5%`
  (0 → −25%, 4 → +25%). `save()` valida (somma esatta = 8, canale 0–4),
  costa 1 turno (0 se allocazione identica), **adegua subito capacità e
  carica scudi** al nuovo canale Scudi. Vietato in capsula.
- `ShipStats::effective()` — overlay finale: Armi × `combat_rating`; Scudi ×
  `max_shields` e `mod_shield_regen`; Motori ±1 a `turns_per_warp` agli
  estremi (utile solo per scafi con tpw ≥ 2); Sensori sposta di un gradino
  lo scanner effettivo (none↔density↔holo). Espone `$ship['eps']` (descrittore
  UI) e `$ship['eps_nominal']`.
- `EpsController` + `GET/POST /gioco/eps`; `views/game/eps.php` (campi numerici
  0–4, `assets/js/eps.js` li arricchisce con +/−, budget live, anteprima
  effetti — **JS esterno: la CSP blocca gli script inline**). Voce «Griglia»
  in game-nav; striscia EPS read-only nel pannello laterale della plancia.
- `Navigation::look()` ora accetta la nave effettiva (2° param) per lo
  scanner potenziato/declassato da EPS; callers aggiornati.
- Anche `views/game/profilo.php` spostato a `assets/js/profile.js` (stesso
  motivo CSP). `sw.js` → v27. e2e `scratchpad/test_eps.php` (27 check).

### #1 — dettaglio di quanto consegnato

- Migrazione `0025_identity` — `players.color/crest/motto`, `ships.registry`,
  `corporations.color/crest` (per lo slice corp futuro).
- `App\Game\Identity` — palette curata di 12 accenti, 12 stemmi, `save()` con
  validazione, `title()` derivato da `Ranks` + tier `Faction` (suffisso
  «Fuorilegge» / «Alleato …»).
- Partial riusabili: `partials/crest_sprite` (12 `<symbol>` SVG, incluso nel
  layout), `partials/crest`, `partials/idchip`. Helper `partial()`.
- Pagina `/gioco/profilo` (editor con anteprima live) + voce in game-nav.
- Stemma + nome colorato nella status bar di plancia e nella classifica
  comandanti; registro/nome nave nella cella «Nave».
- `sw.js` → v21.

### #1b — dettaglio di quanto consegnato

- Migrazione `0026_media` — tabella `media_assets` (owner_type/owner_id/kind,
  status pending|approved|rejected, mime/ext/bytes/width/height/sha1/path,
  review_note/reviewed_by/reviewed_at).
- `App\Game\MediaAsset` — `storeUpload()` valida (tipo, peso effettivo = min
  fra config e `upload_max_filesize`/`post_max_size`, dimensioni 32..4096),
  **ricodifica con GD** in PNG (avatar ritagliato quadrato ≤512, logo a
  proporzioni ≤512 — spoglia EXIF/payload), scrive in `storage/uploads/`
  (fuori dal web), registra `pending` sostituendo il pending precedente.
  `approve()` ritira l'approvato precedente; `reject(note)`; `removeOwn()`;
  `current()` = solo `approved`; `queue()`/`forOwner()` per le UI.
- `App\Core\Request::file()` — accesso tipizzato a `$_FILES`.
- `MediaController` — `GET /media/c/{id}/{kind}` (approvato, `public,max-age=300`,
  ETag/304), `GET /gioco/profilo/media/{kind}` (proprio pending, `private`),
  `GET /admin/media/{id}/file` (qualunque asset, solo admin).
- `ProfileController::uploadMedia`/`removeMedia` + rotte `POST /gioco/profilo/media`
  e `.../rimuovi`; sezione «Immagini caricate» nel profilo (anteprima, stato,
  motivo del rifiuto).
- `AdminController` — coda «Immagini in attesa» nella dashboard +
  `POST /admin/media/{id}/approva|rifiuta` (audit `media.approve`/`media.reject`).
- Display: l'avatar approvato sostituisce lo stemma in `idchip` (plancia,
  classifica); il logo di flotta compare accanto al nome in plancia.
- `.gitignore` → `storage/uploads/*` (solo `.gitkeep` versionato). `sw.js` → v22.

### B (Limpet) — dettaglio di quanto consegnato

- Migrazione `0027_limpet` — solo manopole in `game_config`:
  `limpet.ttl_hours` (12), `limpet.max_tracked` (15), `limpet.field_cap` (50).
  Lo schema (`ship_limpets`, `sector_mines.type='limpet'`, `ships.mines_limpet`)
  esisteva dal `0007` ma nessuno lo leggeva.
- `App\Game\Limpet` — `onEnter()` (aggancio all'ingresso: una per proprietario,
  consuma dal campo, non ridoppia, salta gli alleati, tetto prede col distacco
  del più vecchio), `scrape()` (StarDock), `tracked()` (posizione live della
  preda, l'occultamento non aiuta), `tagCount()`, `trackedSectorIds()`, `gc()`.
- `Combat::onEnterSector` — blocco Limpet dopo le Armid (nessun danno) + scrape
  allo StarDock prima del `return` per Fedspace.
- `Deploy::deployMines` — tetto `limpet.field_cap` per settore/proprietario.
- `bin/tick.php` — task `limpets_gc`.
- Plancia: pannello «Prede tracciate» (handle, tipo nave, settore + rotta, TTL
  residuo) e avviso «hai N Limpet agganciate» per la preda.
- Mappa stellare: `Navigation::mapData` espone `tracked` (id settori);
  `game.js` disegna un anello tratteggiato ambra sui settori delle prede.
- Giornale + `Live::alert` al proprietario a ogni aggancio; `combat_log`
  `kind='mines'` `detail={limpet:1}`.
- `sw.js` → v23. e2e `scratchpad/test_limpet.php` (22 check).

### #1c — dettaglio di quanto consegnato

- `Navigation::look` — `players_here` porta ora `color`, `crest`, `has_avatar`
  (LEFT JOIN `media_assets` per l'avatar approvato).
- `views/partials/player_tag.php` — etichetta compatta riusabile: avatar (se
  approvato) o stemma + nome colorato + `(tipo nave)` + 🛡 protezione novizio.
- `views/game/index.php` — sia «Altre navi qui» (scheda settore) sia «Forze
  nel settore → Navi:» usano `player_tag`. `.who-chip` ridisegnato a
  `inline-flex`.
- **Auto-approvazione admin**: `MediaAsset::storeUpload($autoApprove)` — un
  upload fatto da un utente `role='admin'` entra direttamente `approved`
  (ritira il precedente via `promote()`, `review_note='auto-approvato (admin)'`);
  `ProfileController::uploadMedia` passa `Auth::isAdmin()`, messaggio flash
  differenziato. `approve()` rifattorizzato per condividere `promote()`.
- `MediaAsset::ensureDir` — chmod `02775` best-effort sui livelli sotto
  `storage/uploads/` + file a `0664`, così web (www-data) e CLI (proprietario
  del repo, stesso gruppo via setgid) possono entrambi gestire i file.
- Mappa: **niente roster globale** (romperebbe la nebbia di guerra); l'unica
  identità sulla mappa resta l'anello Limpet delle prede tracciate.
- `sw.js` → v24. e2e `scratchpad/test_media.php` esteso (auto-approvazione).

---

## Coerenza distanze / tempi — analisi (2026-09-11)

**Il problema sollevato**: «nel tempo in cui un giocatore fa 1 warp, un altro
ne fa 10» — sembra incoerente che il tempo reale trascorso non pesi in modo
uniforme sulla posizione dei giocatori.

**Stato attuale della piattaforma**
- Simulazione *lazy* dai timestamp: le azioni del giocatore sono istantanee,
  il mondo (produzione, NPC, eventi, drift di mercato, decadimento fazioni)
  avanza sul cron ogni minuto.
- Economia dei turni: `turns.per_day` = **2500**, **reset secco alle 03:00**
  (nessun accumulo/carry-over; chi non gioca li perde, chi gioca tanto in una
  sessione può bruciarli e poi aspetta fino alle 03:00).
- Warp = 1 turno (fino a 4 per l'Interdictor). L'autopilota somma i salti.
- «Navi qui» / bersagli PvP = puntatore statico `players.sector_id`: puoi
  attaccare chi ha lasciato lì la nave e si è disconnesso ore prima.

**Cosa NON fare** (romperebbe il loop async «entra, fai i turni, esci» e
punirebbe i 5 giocatori sparsi): movimento posizionale realtime, tick di
fisica per-giocatore, timer di viaggio stile OGame che bloccano la plancia.

**Leve disponibili**
- **A — I turni SONO il clock (esplicitarlo).** Tutti ricevono gli stessi
  2500/giorno; chi gioca 10 sessioni non va più veloce, spende solo prima la
  stessa dotazione. Costo ~nullo: audit + documentare l'economia, eventualmente
  passare da reset secco a refill a scaglioni con tetto (`max_banked`) per
  premiare meno il «tutto in una volta».
- **B — La distanza costa di più sulle tratte lunghe.** Es. «calore del drive»:
  i turni/warp salgono con i salti consecutivi senza attracco, o carburante.
  Dà texture «distanza = tempo» ma **rischio tedio** (EPS + guasti già
  aggiungono attrito) → sconsigliata ora.
- **C — Finestra di presenza async.** Un giocatore è «presente/intercettabile»
  in un settore solo per una *grace window* dopo la sua ultima azione lì
  (es. 10 min, da `last_move_at`/`last_seen_at`); scaduta, è «in transito» e
  non attaccabile (oppure l'attacco diventa un colpo differito a cui può
  rispondere al rientro). **Risolve davvero** l'incoerenza «imboscata a chi è
  offline da 3 ore» senza timer né blocchi. Sforzo medio, resa alta.
- **D — Rallentare il mondo, non il giocatore.** Già così: azioni istantanee,
  processi del mondo sul wall-clock. La coerenza percepita è «torni dopo un
  giorno e la galassia si è mossa» — già rinforzata dal Rapporto di rientro.
- **E — Stardata condivisa.** Un orologio di galassia visibile che avanza col
  tempo reale; i turni sono il tuo budget personale contro di esso. Pura
  UI/fiction, lega insieme il tutto. Costo basso.

**Raccomandazione (a suo tempo)**: pacchetto A + C + E; B scartata per il tedio,
D già fatto.

**Esito (2026-09-11)**: l'utente ha deciso di **non perseguire** il tema —
nessuna delle leve A/B/C/E. La piattaforma resta com'è: azioni istantanee,
turni giornalieri come unico equalizzatore, mondo che avanza sul cron. Sezione
tenuta come nota storica.
