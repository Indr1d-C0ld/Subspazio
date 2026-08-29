# Changelog

Registro delle modifiche sincronizzate dal deployment live a questo repo.
Ogni voce elenca i file toccati e cosa/perché è cambiato — stesso dettaglio
riportato nel messaggio del commit corrispondente.

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
