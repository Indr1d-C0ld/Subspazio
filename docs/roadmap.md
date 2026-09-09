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
| **Finire le mine Limpet** — si agganciano allo scafo di chi passa e ti fanno tracciare la preda per N tick | S–M | `ship_limpets` (schema abbozzato), star map |

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
| **#1b — Avatar + logo caricati** (coda di approvazione admin, riuso della coda iscrizioni) | prossimo |
| **#2 — Combattimento B1: tipi d'arma con profilo** | da fare |

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
