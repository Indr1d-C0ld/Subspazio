# Changelog

Registro delle modifiche sincronizzate dal deployment live a questo repo.
Ogni voce elenca i file toccati e cosa/perché è cambiato — stesso dettaglio
riportato nel messaggio del commit corrispondente.

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
