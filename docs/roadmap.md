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
| **Tabella incontri** — `encounter_table` data-driven, probabilità a ogni warp, 2–3 scelte con esiti; poi se ne aggiungono all'infinito | M motore + S/incontro | `Navigation::move`, plancia, giornale |
| **Canale FedNews / Frontier Broadcast** — un NPC radio che ogni giorno racconta lo stato della galassia | S–M | radio, `Events`, threat clock |
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
| **#2 — Combattimento B1: tipi d'arma con profilo** | ~~scartato~~ — non si fa: snatura il combattimento (nessuna agency nel momento, morra cinese a info nascosta con pochi giocatori, superficie di bilanciamento enorme). In alternativa, se in futuro si vuole texture d'arma: un solo asse «penetrazione scudi» (S), o priorità a EPS (B2). I tipi d'arma veri hanno senso solo con le classi di nave (tema D). |

### Tema B — cosa resta

- **EPS / griglia di potenza** (B2) — ripartisci il reattore fra scudi/armi/motori/sensori; dà una decisione *prima* dello scontro + tributo Star Trek.
- **Guasti ai sottosistemi** (B3) — un colpo mette offline un modulo, si ripara allo StarDock o via Ingegnere.
- (I tipi d'arma con profilo restano fuori: vedi sopra.)

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
