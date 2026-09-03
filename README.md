# SubSpazio

Reinterpretazione web, multiutente e persistente della classica *door* per BBS
**TradeWars 2002**: rotte commerciali, flotte, pianeti e corporazioni in un
universo a settori con un clock interno.

Implementazione **originale** delle meccaniche di gioco: non contiene codice,
testi o artwork della door proprietaria.

- **Stack:** PHP 8 puro (nessun framework, nessuna dipendenza) · MariaDB/MySQL · Apache
- **Interfaccia:** plancia web su API JSON, aggiornamenti in tempo reale, installabile come PWA
- **Licenza:** GPL-3.0-or-later

## Funzionalità

**Universo & navigazione**
: 1000+ settori con grafo di warp (collegamenti a senso unico, vicoli ciechi,
  Federazione protetta, StarDock), fog-of-war, tracciamento rotte e autopilota,
  turni giornalieri.

**Economia**
: porti classe 1–8 con **prezzi dinamici** domanda/offerta (scorte vs
  capacità + valore base regionale che deriva nel tempo), rigenerazione lazy,
  **contrattazione a offerta / controproposta**, scambio veloce, Banca
  Intergalattica con interesse composto, mercato nero.

**Combattimento**
: cantiere StarDock (acquisto navi con permuta, potenziamento
  stive/caccia/scudi, hardware: sonde, mine armid/limpet, capsula, scanner,
  transwarp, occultamento, siluri Genesi), motore a caccia con scudi, attacco
  nave-vs-nave, assalto ai porti, mine e caccia dispiegati con intercettazione
  all'ingresso, capsula di salvataggio, gradi e allineamento, protezione
  novizio.

**Pianeti**
: siluri Genesi → pianeti (tipi M/K/O/L/C/H/U), coloni in categorie con crescita
  e produzione, Citadel livelli 1–6, cannone Quasar, guarnigione e scudi
  planetari, assalto planetario con saccheggio e bombardamento.

**Mondo vivo**
: classifiche comandanti e corporazioni, **radio subspaziale** (canali
  radio/fedcomm/corp/privato/hail), NPC Ferrengi/pirati/mercanti con
  movimento/ingaggio/respawn, eventi globali (shock di mercato, brillamenti,
  incursioni, stagione delle taglie).

**Meta-gioco**
: **stagioni** con ladder, reset periodico e albo d'oro (i traguardi
  persistono); **traguardi**; **alleanze** fra corporazioni; **contratti** fra
  giocatori (taglie riscosse in automatico da chi uccide il bersaglio,
  consegne di merce).

**Realtime & PWA**
: stream **SSE** (`/api/stream`) per mappa live, toast, campanella degli
  avvisi e badge senza refresh; Web App Manifest + service worker (guscio
  offline) + mappa con pan/zoom touch. *Il service worker richiede HTTPS.*

**Amministrazione**
: pannello `/admin/gioco` — editor della configurazione di gioco, trigger
  manuale degli eventi, spawn/purga NPC, Big Bang (rigenerazione universo),
  moderazione in-game (kick/sospensione/teletrasporto/rettifiche), chiusura
  stagione, statistiche.

## Requisiti

- PHP 8.1+ (`pdo_mysql`, `gd` per la generazione delle icone, `openssl`)
- MariaDB 10.4+ / MySQL 8+
- Apache con `mod_rewrite` (il vhost deve avere `AllowOverride All`, oppure
  si usa `deploy/apache-subspazio.conf`)

Nessuna libreria di terze parti, nessun Composer.

## Installazione

1. **Codice** — posiziona il progetto sotto il DocumentRoot, es.
   `/var/www/html/subspazio` (servito come `http://host/subspazio/`).

2. **Configurazione** — copia `config/config.example.php` in una posizione
   **fuori dal DocumentRoot** e valorizza i segreti. Ordine di ricerca:
   `$SUBSPAZIO_CONFIG` → `/etc/subspazio/config.php` →
   `/data/subspazio-config/config.php` → `config/config.php`.

3. **Database**

   ```bash
   # personalizza la password in db/setup.sql (deve combaciare con db.pass nel config)
   sudo mariadb < db/setup.sql
   php bin/console.php migrate
   ```

4. **Amministratore**

   ```bash
   php bin/console.php make:admin <username>
   ```

5. **Universo**

   ```bash
   php bin/console.php universe:generate      # genera settori + porti
   php bin/console.php universe:stats
   ```

6. **Clock** — il mondo avanza solo con il tick (NPC, eventi, produzione dei
   pianeti, reset turni, interessi, drift di mercato, scadenza contratti):

   ```
   * * * * * /usr/bin/php /var/www/html/subspazio/bin/tick.php >> /var/www/html/subspazio/storage/logs/cron.log 2>&1
   ```

7. **(Opzionale) Apache dedicato** — `deploy/apache-subspazio.conf` per URL
   puliti + hardening delle directory (poi `app.pretty_urls => true` nel
   config).

Gli utenti si registrano da `/registrati`; un amministratore li approva dalla
dashboard. Alla prima visita di `/gioco` viene creato il comandante.

## Console

| Comando | |
|---|---|
| `php bin/console.php migrate` | applica le migrazioni SQL |
| `db:fresh` | elimina tutte le tabelle e ri-migra |
| `make:admin <user> [email]` | crea/promuove un amministratore |
| `user:approve <user>` · `user:list` | gestione utenti |
| `universe:generate [--force] [--sectors=N]` | genera universo + porti |
| `ports:generate [--force]` · `universe:stats` | economia |
| `economy:drift` · `bank:accrue` | passi manuali di simulazione |
| `config:get [chiave]` · `config:set <chiave> <valore>` | configurazione di gioco |

## Struttura

```
index.php              front controller unico (routing via PATH_INFO / FallbackResource)
src/Core/              Router, Database (PDO), Request, Response, Session, Csrf, RateLimiter, View, Config
src/Auth/              registrazione / login / stato utente (approvazione admin)
src/Game/              logica di gioco: Universe, Economy, Combat, Planets, Corp, Radio, Npc,
                       Events, Season, Achievements, Contracts, BlackMarket, Live, ...
src/Controllers/       Home, Auth, Admin, Game, GameApi, Port, Shipyard, Combat, Planet, Corp,
                       Radio, Leaderboard, Registro, Meta, AdminGame
src/Cli/Migrator.php   migratore SQL minimale
src/routes.php         tabella delle rotte
views/                 template PHP (layout, auth/*, game/*, admin/*, errors/*)
assets/                css/js statici + icone PWA
db/migrations/         *.sql versionati    ·    db/setup.sql = bootstrap DB/utente
bin/                   console.php, migrate.php, tick.php
deploy/                apache-subspazio.conf
```

## Sicurezza

Password con Argon2id, token CSRF, rate limiting su login/registrazione e
radio, azioni di gioco server-authoritative e transazionali, hardening delle
directory non pubbliche via `.htaccess`. I segreti (credenziali DB) vivono
**fuori dal DocumentRoot**; `db/setup.sql` nel repository contiene un
placeholder.

## Licenza

GNU General Public License v3.0 or later — vedi [LICENSE](LICENSE).
