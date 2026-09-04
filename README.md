# SubSpazio

Reinterpretazione web, multiutente e persistente della classica *door* per BBS
**TradeWars 2002**: rotte commerciali, flotte, pianeti e corporazioni in un
universo a settori con un clock interno.

Implementazione **originale** delle meccaniche di gioco: non contiene codice,
testi o artwork della door proprietaria.

- **Stack:** PHP 8 puro (nessun framework, nessuna dipendenza) · MariaDB/MySQL · Apache
- **Interfaccia:** plancia web su API JSON, aggiornamenti in tempo reale, installabile come PWA
- **Licenza:** GPL-3.0-or-later

## Meccaniche di gioco

**Universo & navigazione**
: 1000+ settori con grafo di warp (collegamenti a senso unico, vicoli ciechi,
  Federazione protetta, StarDock), fog-of-war, tracciamento rotte con
  autopilota, mappa stellare 3D con pan/zoom, turni giornalieri (ora di reset
  configurabile), preferiti e note per settore.

**Economia**
: porti classe 1–8 con **prezzi dinamici** domanda/offerta (scorte vs
  capacità + valore base regionale che deriva nel tempo), rigenerazione lazy
  di scorte e tesoreria, **contrattazione a offerta / controproposta**, scambio
  veloce, Banca Intergalattica con interesse composto, **mercato nero** (vendita
  a premio + hardware scontato pagando in allineamento, ripulisce la taglia).

**Navi, hardware & moduli**
: cantiere StarDock — acquisto navi con permuta, potenziamento
  stive/caccia/scudi, hardware (sonde, mine armid/limpet, capsula di
  salvataggio, scanner di densità/olografico, transwarp, occultamento, siluri
  Genesi, laser minerario). **Moduli** in 5 fasce di rarità (Civile → Militare →
  Sperimentale → Xeno → Precursore): si trovano come bottino, occupano slot per
  categoria, si installano/smontano/potenziano (recupero in "Leghe"), e
  sovrappongono i loro bonus alle statistiche della nave.

**Combattimento**
: motore a caccia con scudi, attacco nave-vs-nave (con bottino, esperienza,
  taglia), assalto ai porti con saccheggio, caccia e mine dispiegati
  (offensivo / difensivo / pedaggio) che intercettano all'ingresso nel settore,
  distruzione nave → capsula di salvataggio, gradi e allineamento, protezione
  novizio, **replay round-per-round** di ogni battaglia.

**Equipaggio**
: ufficiali generati da archetipi, 6 ruoli con **bonus passivo** (fusi nelle
  statistiche dopo i moduli) + **abilità attiva**, esperienza e passaggi di
  livello, lealtà con sblocco di un secondo livello di bonus. **Missioni away**
  a skill-check con esiti scalati (trionfo → disastro). Permadeath opzionale
  (spento di default).

**Scansione & frontiera**
: le regioni profonde nascondono relitti, depositi, anomalie, giacimenti di
  asteroidi e pericoli ambientali. **SCAN** costa turni e rivela le feature del
  settore corrente e dei vicini (raggio in base a scanner / ufficiale Scienziato
  / modulo); poi si spoglia / raccoglie / studia. Hazard (radiazioni, tempeste
  ioniche) e pozzi gravitazionali colpiscono all'ingresso, mitigati se già noti.
  **Codex**: diario delle scoperte.

**Fazioni & reputazione**
: quattro potenze (Federazione Unita, Consorzio Ferrengi, Egemonia di Korr,
  Liberi Mondi della Frontiera). Reputazione per giocatore da −100 a +100 in 5
  tier, mossa da commercio, kill, missioni e lavoro nel profondo, con
  spill-over sulle rivali. Sblocca **empori di fazione** allo StarDock; la
  Federazione ostile revoca Cantiere e Banca (con possibilità di **ammenda**) e
  invia **cacciatori di taglie**; decadimento giornaliero.

**Industria & produzione**
: laser minerario per estrarre da un giacimento di asteroidi (minerale +
  Cristalli, a più passaggi); **raffineria** allo StarDock (minerale +
  equipaggiamento → Componenti); **ricette** deterministiche (Componenti +
  Cristalli + Leghe → un modulo preciso) come **lavori dell'Officina** che
  maturano sul tick — la durata scala con la rarità, si annullano con rimborso
  dei materiali; **modalità industria** dei pianeti (scorte di minerale →
  Componenti per il proprietario).

**Pianeti & corporazioni**
: siluri Genesi → pianeti (tipi M/K/O/L/C/H/U con capacità e produzione
  diverse), coloni in categorie con crescita e produzione lazy, imbarco coloni
  dalla Terra (quota giornaliera), **Citadel** livelli 1–6, cannone **Quasar**,
  guarnigione e scudi planetari, assalto planetario con saccheggio e
  bombardamento. **Corporazioni**: fonda/entra/esci, cassa e possesso pianeti
  condivisi, i soci non si sparano addosso, **alleanze** fra corp.

**Contratti fra giocatori**
: **taglie** con cauzione, riscosse in automatico da chi elimina il bersaglio;
  **consegne** di merce con ricompensa. Scadenza gestita dal tick.

**Mondo vivo**
: **classifiche** comandanti e corporazioni (rating combinato ricalcolato dal
  tick), **radio subspaziale** (canali radio / fedcomm / corp / privato / hail
  con badge non letti), **NPC** Ferrengi / pirati / mercanti con
  movimento / ingaggio / respawn, **eventi globali** (shock di mercato,
  brillamento solare, incursione Ferrengi, ondata di pirateria, stagione delle
  taglie) annunciati via radio.

**Meta-gioco**
: **stagioni** con ladder, soft-reset dei comandanti e albo d'oro (i traguardi
  persistono, universo rigenerabile a scelta); **traguardi** verificati sullo
  stato o per evento.

**Giornale di bordo & rientro**
: un **registro incidenti** persistente e sfogliabile per giocatore, con voce
  coerente all'ambientazione — scontri all'ingresso di un settore, hazard,
  contatti NPC, comunicazioni diplomatiche, colonie colpite, esiti dei
  contratti. Al ritorno in plancia dopo un'assenza, un **rapporto di rientro**
  riassume cosa è maturato (voci di giornale, turni ricaricati, produzione
  delle colonie, lavori d'Officina completati, contratti scaduti).

**Onboarding**
: sette "primi passi" dedotti dai dati del giocatore, con ricompensa una
  tantum, e una pagina **Guida** di riferimento a tutti i sistemi.

**Realtime & PWA**
: stream **SSE** (`/api/stream`) per mappa live, toast, campanella degli
  avvisi e badge senza refresh; Web App Manifest + service worker (guscio
  offline) + mappa con pan/zoom touch. *Il service worker richiede HTTPS.*

**Amministrazione**
: pannello `/admin/gioco` — editor della configurazione di gioco, trigger
  manuale degli eventi, spawn/purga NPC, Big Bang (rigenerazione universo),
  moderazione in-game (kick/sospensione/teletrasporto/rettifiche), chiusura
  stagione, statistiche. Registrazione con **approvazione admin** e **notifica
  e-mail** (SMTP) all'amministratore per ogni nuova richiesta di accesso.

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

6. **Clock** — il mondo avanza solo con il tick (NPC, eventi, feature dei
   settori, fazioni, produzione e industria dei pianeti, lavori dell'Officina,
   reset turni, interessi, drift di mercato, scadenza contratti, notifiche
   e-mail, garbage collection):

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
src/Core/              Router, Database (PDO), Request, Response, Session, Csrf, RateLimiter,
                       View, Config, Mailer (SMTP minimale)
src/Auth/              registrazione / login / stato utente (approvazione admin)
src/Game/              logica di gioco: Universe, Navigation, Economy, Haggle, Bank, Shipyard,
                       Combat, Deploy, Loot, Modules, ShipStats, Crew, AwayMissions, Planets,
                       Corp, Contracts, Faction, SectorFeatures, Codex, Industry, Npc, Events,
                       Season, Achievements, BlackMarket, Radio, Leaderboard, Live, ShipLog,
                       Digest, Onboarding, Notifier, TurnManager, Ranks, GameConfig, Ctx
src/Controllers/       Home, Auth, Admin, AdminGame, Game, GameApi, ShipLog, Port, Bank,
                       Shipyard, Module, Combat, Crew, Mission, Scan, Faction, Codex, Planet,
                       Corp, Radio, Leaderboard, Registro, Meta
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
