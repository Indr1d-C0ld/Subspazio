# SubSpazio

Reinterpretazione web, multiutente e persistente della classica *door* per BBS
**TradeWars 2002**: rotte commerciali, flotte, pianeti e corporazioni in un
universo a settori con un clock interno.

## Il gioco in breve

Si parte allo StarDock di Sol con una Merchant Cruiser, mille crediti e un
budget di turni al giorno. Attorno a Sol la galassia è divisa in **cinque
fasce di rischio**: nella Cintura i predoni sono deboli e chi perde viene
razziato, non distrutto; nell'Orlo del Buio i porti pagano il 20% in più, il
bottino è raro, e le flotte Ferrengi superano i centomila caccia. Ogni viaggio
verso l'esterno è una scommessa fra guadagno e rischio.

- **Commerciare** fra porti a prezzi di domanda e offerta, contrattando, con la
  banca e il mercato nero; contratti e taglie fra comandanti.
- **Combattere** predoni, Ferrengi, flotte e comandanti d'élite, o altri
  comandanti; assaltare porti e pianeti.
- **Fare i conti con la legge**: chi aggredisce civili accumula notorietà, e
  pattuglie e squadre federali tarate sulla sua nave gli danno la caccia. La
  taglia sulla sua testa la paga lui: chi lo abbatte la incassa, confiscata dai
  crediti a bordo e poi dalla banca del ricercato; uno al verde non rende
  nulla, e la nave di soccorso gratuita, abbattuta, non vale né esperienza né
  bottino. I mercantili viaggiano scortati, fuggono e chiamano soccorso.
- **Equipaggiare la nave** con 88 moduli in 18 famiglie, da Civile a
  Precursore, con affissi casuali; consumabili monouso, reperti da collezionare,
  progetti che sbloccano ricette d'Officina.
- **Esplorare** relitti, anomalie e giacimenti; occultarsi con una riserva di
  energia, sapendo che si può essere scoperti.
- **Colonizzare** con i siluri Genesi, fortificare con Citadel e Quasar, unirsi
  in corporazioni e alleanze; costruire reputazione con quattro fazioni.
- **Lasciare il segno**: stagioni con classifica e albo d'oro, oltre cento
  traguardi a livelli (alcuni segreti) con contatori di carriera che restano, e
  titoli onorifici accanto al nome.

Tutto in tempo reale nel browser, dal telefono come dal computer, anche come
app installata. Ogni regola è regolabile dal pannello di amministrazione.

Implementazione **originale** delle meccaniche di gioco: non contiene codice,
testi o artwork della door proprietaria.

- **Stack:** PHP 8 puro (nessun framework, nessuna dipendenza) · MariaDB/MySQL · Apache
- **Interfaccia:** plancia web su API JSON, aggiornamenti in tempo reale (SSE),
  responsive per telefono/tablet/desktop; predisposta come PWA (installazione,
  guscio offline e Web Push richiedono HTTPS)
- **Stato:** in **beta testing**
- **Licenza:** GPL-3.0-or-later

## Meccaniche di gioco

- **Universo & navigazione** — 1000+ settori con grafo di warp (collegamenti a
  senso unico, vicoli ciechi, Federazione protetta, StarDock), fog-of-war,
  tracciamento rotte con autopilota, mappa stellare 3D con pan e zoom, turni
  giornalieri (ora di reset configurabile), preferiti e note per settore
  (modificabili e rimovibili dal Registro rotte, ovunque sia la nave).

- **Economia** — porti classe 1–8 con **prezzi dinamici** domanda/offerta
  (scorte contro capacità, più un valore base regionale che deriva nel tempo),
  rigenerazione lazy di scorte e tesoreria, **contrattazione** a offerta e
  controproposta, scambio veloce, Banca Intergalattica con interesse composto,
  **mercato nero**: compra a premio sul prezzo equo del porto locale — ma non
  la merce che il porto del settore vende, così non esiste arbitraggio sul
  posto — e vende hardware scontato; ogni affare costa allineamento, e per
  una cifra ripulisce la taglia. Il commercio non consuma turni.

- **Navi, hardware & moduli** — cantiere StarDock: acquisto navi con permuta
  (stive, caccia e scudi passano alla nuova nave fino al suo tetto, e ciò che
  eccede ed era stato comprato si rimborsa a listino), potenziamento di stive,
  caccia e scudi, hardware (sonde, mine armid e limpet,
  capsula di salvataggio, scanner di densità e olografico, transwarp,
  occultamento, siluri Genesi, laser minerario). I **moduli** hanno 5 fasce di
  rarità (Civile, Militare, Sperimentale, Xeno, Precursore): si trovano come
  bottino, occupano slot per categoria, si installano, smontano e potenziano
  (con recupero in «Leghe»), e sovrappongono i loro bonus alle statistiche
  della nave. Il catalogo conta **88 modelli in 18 famiglie**, ognuna con un
  modello per rarità (l'occultamento solo dalle tre più alte):

  | Slot | Famiglie |
  |---|---|
  | Armi | cannoni (combattimento), hangar (tetto dei caccia), interdizione (i mercantili non fuggono) |
  | Difesa | scudi (tetto), rigenerazione, deflettori, corazza (danni subiti), schermi (mine, Quasar, pericoli) |
  | Propulsione | propulsori (turni per warp), manovra (elusione degli agganci) |
  | Computer | sensori, guerra elettronica (danni da NPC e caccia), analisi (fortuna del bottino), transponder (notorietà dei crimini) |
  | Utility | stive, recupero (Leghe), occultamento, officina (caccia prodotti ogni ora) |

  L'**occultamento** è un attraversamento, non un rifugio: il dispositivo ha
  una riserva di 8 cariche (una per salto, ne torna una ogni 10 minuti; a
  riserva vuota la nave riappare), ogni aggancio può scoprire la nave (dal 5%
  nella Cintura al 40% nell'Orlo, +20% contro pattuglie ed élite, meno coi
  modelli rari del dispositivo), e qualunque interazione col settore —
  commercio, mercato nero, pianeti, relitti, depositi, estrazione, anomalie,
  scansione — lo fa cadere.

  Potenziare un modulo lo porta alla rarità successiva **della sua famiglia**.
  I moduli trovati come bottino hanno spesso **affissi** casuali (da 0–1 sui
  Civili a 2–3 sui Precursori) che aggiungono un effetto e un epiteto al nome
  («Railgun a massa dell'Assalto e della Fortuna»); il potenziamento li
  conserva, riscalati sulla nuova rarità. Corazza e disturbo elettronico
  insieme attenuano al massimo il 60% dei colpi, la schermatura il 90% dei
  danni ambientali, l'elusione evita al massimo il 60% degli agganci.

  **Consumabili** monouso cadono dai nemici abbattuti (25% per la probabilità
  di bottino della fascia, rarità secondo la fascia) e si usano dalla plancia:
  nanoriparatori (scudi al massimo), container di caccia (un decimo del
  tetto), carica EMP (nessun aggancio al prossimo ingresso ostile),
  acceleratore di warp (salto gratuito), esca olografica (30 minuti senza
  agganci da fermo), nucleo in sovraccarico (+25% al prossimo attacco), codice
  di amnistia contraffatto (−60 di notorietà), sonda di recupero Precursore
  (modulo garantito dal prossimo nemico). Non si sprecano: un nanoriparatore a
  scudi pieni non si consuma.

  **Reperti**: quindici oggetti rari in tre collezioni (Archivio della Prima
  Federazione, Cimeli del Consorzio Ferrengi, Reliquie dei Precursori), dal
  bottino (12% per la probabilità della fascia) e dai relitti (il doppio). Si
  vendono all'antiquario dello StarDock (da 1.500 a 120.000 cr) o si tengono:
  una collezione completa paga una volta sola 25.000 / 80.000 / 250.000 cr,
  esperienza e un modulo almeno Militare / Sperimentale / Precursore.
  **Progetti**: dieci ricette d'Officina per moduli Xeno e Precursore che si
  sbloccano solo trovandone il progetto, garantito dai comandanti d'élite dalla
  Frontiera in fuori e raro (3%) dagli altri nemici. Reperti e progetti stanno
  nel Codex.

- **Combattimento** — motore a caccia con scudi (una nave senza caccia resta
  vulnerabile finché ha scudi da consumare), attacco nave contro nave con
  bottino ed esperienza, assalto ai porti con saccheggio, assalto ai pianeti
  con saccheggio e bombardamento (l'ala lanciata combatte, il resto dei caccia
  resta a bordo), caccia e mine dispiegati (offensivi, difensivi o a pedaggio,
  con un tetto) che intercettano all'ingresso nel settore, distruzione della
  nave con capsula di salvataggio, gradi e allineamento, protezione novizio,
  **replay round per round** di ogni battaglia.
  **Taglie**: chi uccide comandanti onesti accumula una taglia, che incassa
  chi lo abbatte. Ogni taglia la paga chi ce l'ha sulla testa: la Federazione
  la confisca ai suoi crediti a bordo e poi alla sua banca, fino all'importo,
  e da un ricercato al verde non si ricava nulla (versata dal nulla, un
  secondo account si faceva ricercato apposta). Chi si difende e distrugge
  l'attaccante riceve l'uccisione e i contratti sulla sua testa. Abbattere una
  capsula di salvataggio, o la nave di soccorso gratuita dello StarDock finché
  non se ne compra una vera, non vale come uccisione: niente esperienza né
  moduli.

- **Equipaggio** — ufficiali generati da archetipi, 6 ruoli con **bonus
  passivo** (fuso nelle statistiche dopo i moduli) e **abilità attiva**,
  esperienza e passaggi di livello, lealtà che sblocca un secondo livello di
  bonus. **Missioni away** a skill-check con esiti scalati, dal trionfo al
  disastro. Permadeath opzionale, spento di default.

- **Fasce di rischio** — attorno a Sol (lo StarDock) lo spazio è diviso in
  **cinque fasce concentriche**, per distanza sulla mappa galattica: Cintura di
  Sol, Anello dei Coloni, Frontiera, Marche Remote, Orlo del Buio. Più ci si
  allontana, più le minacce sono forti e frequenti e più tutto rende:

  | Fascia | Caccia dei predoni | Ingaggio | Porti pagano | Se perdi contro un NPC |
  |---|---|---|---|---|
  | I · Cintura di Sol | 80–250 | 15% | +0% | razzia |
  | II · Anello dei Coloni | 250–650 | 30% | +5% | razzia |
  | III · Frontiera | 1.500–5.000 | 45% | +10% | distruzione |
  | IV · Marche Remote | 6.000–25.000 + Ferrengi 15.000–50.000 | 60% | +15% | distruzione |
  | V · Orlo del Buio | 25.000–90.000 + Ferrengi 50.000–160.000 | 75% | +20% | distruzione |

  Dalla Frontiera gli ostili possono viaggiare in **flotte** di due-quattro
  navi (20% / 40% / 60%) che si muovono insieme; chi ne attacca una se le
  ritrova tutte addosso, una dopo l'altra. In ogni fascia dalla II vive almeno
  un **comandante d'élite** (due nell'Orlo): una volta e mezza il massimo della
  fascia, rating più alto, cassa quadrupla, esperienza tripla e un modulo
  **garantito** (almeno Militare in II, Sperimentale in III, Xeno in IV-V);
  la radio ne annuncia l'avvistamento e l'abbattimento.

  Ogni predone nasce in una fascia che ne fissa forza e crediti a bordo, e si
  muove solo nella sua o una più in fuori, **mai verso Sol**; i Ferrengi vivono
  solo nelle fasce IV–V. La nave iniziale respinge i predoni della Cintura. Nelle
  prime due fasce chi perde viene **razziato, non distrutto**: i predoni
  prendono il carico e il 10% dei crediti a bordo (che ritrova chi li abbatte),
  la nave resta senza caccia né scudi, e per 30 minuti i predoni di quelle fasce
  lo lasciano stare. Con la distanza crescono anche esperienza (da ×0,5 a ×3),
  probabilità di bottino e **rarità dei moduli** (nella Cintura quasi solo
  civili, nell'Orlo sperimentali, xeno e precursori), ricchezza di relitti,
  depositi, anomalie e giacimenti, difficoltà e premi delle missioni away. I
  pericoli ambientali cominciano dalla fascia II. La plancia mostra la fascia
  del settore; ogni bottone di warp porta il numero della fascia d'arrivo, e un
  salto che ne sale due o più in una volta (le corsie federali a lungo raggio
  portano da Sol fino all'Orlo) chiede conferma. La mappa stellare può colorare
  i settori per fascia. Tutti i valori sono nella famiglia `fasce` del pannello.

- **Scansione & frontiera** — più lontano da Sol, relitti, depositi, anomalie,
  giacimenti di asteroidi e pericoli ambientali sono più ricchi e più duri. La **scansione**
  costa turni e rivela le anomalie del settore corrente e di quelli vicini (il
  raggio dipende da scanner, ufficiale Scienziato o modulo); poi si spoglia, si
  raccoglie o si studia. Hazard (radiazioni, tempeste ioniche) e pozzi
  gravitazionali colpiscono all'ingresso, mitigati se già noti. Il **Codex**
  tiene il diario delle scoperte.

- **Fazioni & reputazione** — quattro potenze: Federazione Unita, Consorzio
  Ferrengi, Egemonia di Korr, Liberi Mondi della Frontiera. La reputazione per
  giocatore va da −100 a +100 su 5 tier, mossa da commercio, kill, missioni e
  lavoro nel profondo, con spill-over sulle rivali. Il commercio conta **per il
  valore scambiato**, non per il numero di scambi: in media un punto ogni
  5.000 crediti nella regione della fazione, al massimo 3 per singolo scambio
  (gli scambi piccoli contano in proporzione). Sblocca **empori di
  fazione** allo StarDock; la Federazione ostile revoca Cantiere e Banca (con
  possibilità di **ammenda**) e invia **cacciatori di taglie**; decade ogni
  giorno.

- **Legge federale** — aggredire civili è un crimine: mercantili (15 punti,
  +30 se distrutti), porti (40), pianeti altrui (20, bombardati 50),
  comandanti onesti (25), pattuglie federali (80). La **notorietà** dimezza
  ogni giorno da sola e cresce più in fretta per i **recidivi** (ogni crimine
  delle ultime 72 ore pesa il 25% in più sul successivo). A gradini:

  | Gradino | Da | Cosa succede |
  |---|---|---|
  | Sospetto | 25 | la Federazione ti tiene d'occhio |
  | Ricercato | 100 | le pattuglie ti attaccano a vista, una squadra d'intercettazione ti caccia, taglia di 300 cr per punto, confiscata a te, per chi ti abbatte |
  | Pericoloso | 250 | due squadre, lo StarDock chiude i servizi |
  | Nemico pubblico | 500 | tre squadre pesanti, in ogni fascia |

  Le **pattuglie** fanno la ronda nelle fasce I–III; le **squadre
  d'intercettazione** partono a due o tre salti dal ricercato, sono tarate sui
  caccia della sua nave (×0,7 … ×1,4) e lo inseguono lungo la rotta più breve.
  Le pattuglie non razziano: arrestano, e la nave è perduta. Arresto o
  abbattimento per la taglia riportano il colpevole sotto la soglia di
  Ricercato, e una squadra lascia stare chi non è più ricercato. L'**ammenda** (pagina Fazioni, da ovunque) azzera la notorietà a
  400 cr per punto, moltiplicati per la recidiva. Tutto regolabile nella
  famiglia `legge`.

- **Mercantili** — viaggiano con una **scorta armata** proporzionata alla
  fascia (da 300–800 caccia nella Cintura a 20.000–60.000 nell'Orlo), portano
  pochi contanti (30%) e molta merce (da rivendere). Sotto attacco possono
  **fuggire** in un settore vicino (35%, meno con un proiettore
  d'interdizione) e lanciano una **richiesta di soccorso**: una pattuglia
  parte a due o tre salti e insegue l'aggressore per 30 minuti, ricercato o no
  (sempre nella Cintura, 30% nell'Orlo). Famiglia `mercanti`.

- **Industria & produzione** — laser minerario per estrarre da un giacimento di
  asteroidi (minerale e Cristalli, a più passaggi); **raffineria** allo
  StarDock (minerale + equipaggiamento → Componenti); **ricette**
  deterministiche (Componenti + Cristalli + Leghe → un modulo preciso), avviate
  come **lavori dell'Officina** che maturano sul tick (durata proporzionale
  alla rarità, annullabili con rimborso dei materiali); **modalità industria**
  dei pianeti, che converte le scorte di minerale in Componenti per il
  proprietario.

- **Pianeti & corporazioni** — siluri Genesi che generano pianeti (tipi
  M/K/O/L/C/H/U, con capacità e produzione diverse), coloni in categorie con
  crescita e produzione lazy, imbarco coloni dalla Terra (quota giornaliera),
  **Citadel** livelli 1–6, cannone **Quasar**, guarnigione e scudi planetari,
  assalto planetario con saccheggio e bombardamento. Le **corporazioni** si
  fondano e si lasciano, condividono cassa e possesso dei pianeti, i soci non
  si sparano addosso, e possono stringere **alleanze**. Quando esce l'ultimo
  socio la corporazione si scioglie e **la cassa torna a lui** (la pagina lo
  avvisa prima di confermare).

- **Contratti fra giocatori** — **taglie** con cauzione, riscosse in automatico
  da chi elimina il bersaglio; **consegne** di merce con ricompensa. La
  scadenza è gestita dal tick.

- **Mondo vivo** — **classifiche** di comandanti e corporazioni (rating
  combinato ricalcolato dal tick); **radio subspaziale** con i canali radio,
  fedcomm, corp, privato e hail, e badge dei messaggi non letti; **NPC**
  Ferrengi, pirati e mercanti che si muovono, ingaggiano e rinascono sul tick,
  ognuno nella sua fascia di rischio;
  **eventi globali** (shock di mercato, brillamento solare, incursione
  Ferrengi, ondata di pirateria, stagione delle taglie) annunciati via radio.

- **Meta-gioco** — **stagioni** con ladder e albo d'oro. Alla chiusura
  ripartono da zero crediti, navi, pianeti, materiali, tesori delle
  corporazioni, lavori d'Officina, reputazione, notorietà, consumabili e
  reperti; restano traguardi, ufficiali e moduli (che tornano in inventario).
  Con la **ripartenza totale** si azzerano anche moduli, ufficiali, progetti,
  collezioni e corporazioni. L'universo si rigenera a scelta. Mentre si azzera
  il gioco è fermo: il clock aspetta e le pagine rispondono «Fine stagione in
  corso» (l'amministratore entra); se la chiusura si interrompe, il gioco resta
  chiuso invece di riaprire a metà. Banditi e sospesi non compaiono in
  classifica né nell'albo.

- **Traguardi** — **109**, in sette categorie (commercio, combattimento,
  esplorazione, bottino, legge, sopravvivenza, carriera), a livelli bronzo /
  argento / oro / platino, alcuni **segreti** (si scoprono solo ottenendoli).
  Li sbloccano **contatori di carriera** che sopravvivono alle stagioni
  (scambi e volume, nemici abbattuti per tipo, élite, salti e settori, fascia
  più lontana, razzie subite, moduli trovati per rarità, collezioni, taglie,
  gradino di notorietà…), con la barra d'avanzamento in pagina. Danno
  **prestigio, non potere**: i punti sbloccano titoli onorifici (da
  «Navigatore» a «Custode delle stelle») e alcuni traguardi un titolo proprio
  («Nemico Pubblico», «Fenice», «Campione»…), da mostrare accanto al nome in
  plancia, nei settori e in classifica.

- **Giornale di bordo & rientro** — un **registro incidenti** persistente e
  sfogliabile per giocatore, con voce coerente all'ambientazione: scontri
  all'ingresso di un settore, hazard, contatti NPC, comunicazioni diplomatiche,
  colonie colpite, esiti dei contratti. Al ritorno in plancia dopo un'assenza,
  un **rapporto di rientro** riassume cosa è maturato: voci di giornale, turni
  ricaricati, produzione delle colonie, lavori d'Officina completati, contratti
  scaduti.

- **Onboarding** — sette «primi passi» dedotti dai dati del giocatore, con una
  ricompensa una tantum, e una pagina **Guida** di riferimento a tutti i
  sistemi.

- **Realtime & PWA** — stream **SSE** (`/api/stream`) per mappa live, toast,
  campanella degli avvisi e badge senza refresh. Ogni evento si vede una volta
  sola: lo stream considera già visto ciò che esiste quando si apre, e la scheda
  ricorda cosa ha mostrato e da dove riprendere, così cambiare schermata non
  ripresenta notifiche vecchie. Ogni comandante tiene aperti al massimo tre
  stream (`live.stream_paralleli`): il quarto chiude il più vecchio, di solito
  una pagina già lasciata, che si riaggancia se torna in primo piano. Web App Manifest, service
  worker (guscio offline) e mappa con pan e zoom touch. Il service worker
  richiede HTTPS.

- **Account & posta** — iscrizione con **autovalidazione dell'indirizzo**: chi
  si registra riceve un collegamento monouso e attiva l'account da sé, senza
  passare da un amministratore. Stessa meccanica per il **recupero della
  password**, che alla conferma chiude anche le sessioni già aperte. In tabella
  finisce solo l'impronta `sha256` del gettone, mai il gettone. Tutta la posta
  passa da una **coda con ritentativi** (attese crescenti) e un tetto
  giornaliero configurabile: se l'SMTP non risponde il messaggio resta in coda
  e riparte da solo.

- **Profilo & immagini** — colore, stemma, motto, nome e registro della nave.
  Avatar e logo di flotta si caricano dal profilo: il browser mostra un
  **riquadro in cui trascinare e ingrandire** l'immagine per scegliere
  l'inquadratura, e parte esattamente quello che si vede nel riquadro. Senza
  JavaScript parte il file com'è e il server fa un ritaglio quadrato centrato.
  Le immagini vengono ricodificate lato server (EXIF e payload spogliati) e
  pubblicate subito; l'amministratore può rimuoverle dopo, e una chiave
  riporta l'intera coda di moderazione se si preferisce il vaglio preventivo.

- **Amministrazione** — pannello `/admin/gioco`: editor della configurazione di
  gioco con ripristino dei default, trigger manuale degli eventi, spawn e purga
  degli NPC, Big Bang (rigenerazione dell'universo), moderazione in-game (kick,
  sospensione, teletrasporto, rettifiche, editor del profilo altrui, rimozione
  delle immagini), chiusura stagione, salute del clock, statistiche. Notifica
  e-mail all'amministratore per ogni nuova iscrizione — una notizia, non una
  richiesta da vagliare.

## Come si gioca

Ci si registra da `/registrati` (nome utente di 3–32 caratteri, indirizzo
valido, password di almeno 9 caratteri — la lunghezza minima è configurabile).
Arriva un collegamento di conferma: aprendolo l'account si attiva da sé, senza
che nessuno debba approvarlo. Alla prima visita di `/gioco` viene creato il
comandante, con una nave iniziale allo StarDock, i «primi passi» in evidenza e
**48 ore di protezione novizio**.

Il gioco ha un ritmo doppio:

- **Turni** — un budget di azioni al giorno che si ricarica alle 03:00 (fuso
  configurabile, default `Europe/Rome`). Warp, combattimento, scansione,
  estrazione e le abilità dell'equipaggio consumano turni; il commercio no.
- **Tick** — ogni minuto un cron fa avanzare il mondo: NPC, eventi, feature dei
  settori, fazioni, produzione e industria dei pianeti, lavori dell'Officina,
  scadenza dei contratti, interessi, drift di mercato, notifiche, garbage
  collection.

Stare offline non fa perdere nulla: al rientro in plancia il **rapporto di
rientro** riassume cosa è maturato. Periodicamente una **stagione** si chiude
con un soft-reset dei comandanti (traguardi e albo d'oro restano).

## Ispirazioni

L'ossatura è quella di **TradeWars 2002**, la *door* per BBS: settori, warp,
turni, porti, StarDock, flotte, corporazioni. Sopra ci mette il ritmo asincrono
di **OGame** (il mondo che cresce offline, i lavori a tempo), lo spirito 4X di
**Master of Orion** (colonie, industria, tecnologie e moduli, potenze
galattiche), il tono di plancia di **Star Trek: The Next Generation** (radio
subspaziale, comunicazioni diplomatiche, giornale di bordo, ufficiali con i loro
ruoli) e, da **Mass Effect**, l'equipaggio con ruoli e lealtà, la reputazione a
livelli con fazioni rivali e le missioni fuori dalla nave con esiti che pesano.

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

   Se il codice è più nuovo dello schema (aggiornato senza `migrate`) il clock
   non parte: annota «IN ATTESA: migrazioni non applicate» in
   `storage/logs/tick.log` e riprende da solo dopo la migrazione.

7. **Posta** — la verifica dell'indirizzo e il recupero password hanno bisogno
   di un SMTP funzionante. Valorizza `mail.transport => 'smtp'` con host,
   porta, utente e password del tuo relay, `mail.from_email`, e — importante —
   **`app.public_url` in `https`**: nei messaggi viaggia un gettone monouso, e
   in chiaro chi ascolta la rete entra al posto del giocatore. Con
   `mail.transport => 'log'` (default) i messaggi finiscono in `storage/logs/`
   invece di partire: comodo in sviluppo, inutilizzabile in produzione.

8. **(Opzionale) Apache dedicato** — `deploy/apache-subspazio.conf` per URL
   puliti + hardening delle directory (poi `app.pretty_urls => true` nel
   config).


Gli utenti si registrano da `/registrati` e si attivano da soli confermando
l'indirizzo. Alla prima visita di `/gioco` viene creato il comandante.

## Configurazione

La configurazione sta su **due livelli distinti**, e la differenza conta:

| | Dove | Cosa ci va | Come si cambia |
|---|---|---|---|
| **Infrastruttura** | file PHP fuori dal DocumentRoot | segreti e ambiente: database, SMTP, sessioni, percorsi | si modifica il file |
| **Gioco** | tabella `game_config` | bilanciamento e regole: costi, probabilità, soglie, tempi | dal pannello `/admin/gioco` o da console, **a caldo** |

Il primo richiede accesso al server ed è materia di installazione. Il secondo è
il pannello di regolazione del gioco: 320 chiavi, tutte modificabili senza
riavviare nulla e senza toccare il codice.

### Livello 1 — il file di configurazione

Copia `config/config.example.php` **fuori dal DocumentRoot** e valorizzalo.
Ordine di ricerca dell'applicazione:

```
$SUBSPAZIO_CONFIG  →  /etc/subspazio/config.php  →  /data/subspazio-config/config.php  →  config/config.php
```

| Chiave | A che serve |
|---|---|
| `app.name` | nome mostrato in pagina, nel titolo e nelle e-mail |
| `app.env`, `app.debug` | `production` / `development`; con `debug` gli errori vengono mostrati invece che nascosti |
| `app.timezone` | fuso del gioco — decide quando cade il reset dei turni (default `Europe/Rome`) |
| `app.base_path` | sottopercorso se il gioco non sta in radice (es. `/subspazio`); vuoto = dedotto |
| `app.pretty_urls` | `true` con il vhost dedicato, che toglie `index.php` dagli URL |
| **`app.public_url`** | URL assoluto del gioco. **Deve essere `https`**: ci si costruiscono i collegamenti di verifica e recupero password, che contengono un gettone monouso |
| `db.*` | host, porta, nome, utente, password, charset |
| `security.session_name`, `security.session_ttl` | nome del cookie e durata della sessione (default 8 ore) |
| `paths.root` | radice del progetto. **Lasciala vuota**: viene dedotta, ed è quasi sempre la cosa giusta |
| `paths.uploads` | radice dei file caricati dagli utenti. **Tienila fuori dall'albero di git e dal web**, così un redeploy o un `git clean` non può cancellare i contenuti dei giocatori. Va creata a mano, `2775`, gruppo del web server |
| `media.max_bytes` | limite per immagine (il limite effettivo è il minimo fra questo e `upload_max_filesize`/`post_max_size` del php.ini) |
| `mail.transport` | `log` (scrive in `storage/logs/`, per lo sviluppo) oppure `smtp` |
| `mail.smtp_*`, `mail.from_*` | relay e mittente |
| `notify.admin_email` | dove arrivano le notifiche di nuova iscrizione e gli allarmi del clock |
| `notify.new_registration`, `notify.new_registration_mode`, `notify.digest_delay_min` | se e come avvisare l'amministratore: `immediate` o `digest` (riepilogo cumulativo) |

### Livello 2 — le regole del gioco (`game_config`)

Ogni chiave ha un valore, un tipo e un **valore di default**, e si cambia dal
pannello `/admin/gioco` (con un pulsante «↺» che riporta al default) o da
console:

```bash
php bin/console.php config:get                 # tutte
php bin/console.php config:get combat          # solo la famiglia
php bin/console.php config:set newbie.protect_hours 72
```

Le 320 chiavi per famiglia:

| Famiglia | N. | Cosa regola |
|---|---|---|
| `economy` | 23 | prezzi base regionali, deriva del mercato, sconto d'acquisto, ricarico di vendita, bande della contrattazione |
| `scan` | 26 | costo in turni di scansione/sonda/raccolta/studio, densità delle feature in frontiera (fasce I–III) e frontiera profonda (IV–V), rese e bonus |
| `crew` | 20 | costo di assunzione, livelli, lealtà, cure, costo e raffreddamento delle abilità, missioni away |
| `mercanti` | 7 | scorta dei mercantili per fascia (caccia e rating), quota di contanti, probabilità di fuga, probabilità di soccorso per fascia, durata e forza della pattuglia di soccorso |
| `legge` | 18 | soglie dei gradini di notorietà, dimezzamento, peso di ogni crimine, recidiva, taglia e ammenda per punto, squadre e loro forza per gradino, ronde e loro fasce, probabilità d'ingaggio delle pattuglie |
| `faction` | 22 | guadagni e perdite di reputazione (quella da commercio in proporzione al valore scambiato), soglie dei tier, ammenda, cacciatori di taglie (e loro tetto), decadimento |
| `elite` | 6 | comandanti d'élite per fascia, moltiplicatori di caccia, crediti ed esperienza, probabilità di rinascita, rarità minima del modulo garantito |
| `fasce` | 21 | soglie delle cinque fasce (frazioni del raggio della galassia), per ciascuna: predoni (quanti, caccia, rating), Ferrengi, crediti a bordo, probabilità d'ingaggio, esperienza, premio dei porti, probabilità e rarità del bottino, ricchezza delle feature, difficoltà delle missioni; razzie (fino a quale fascia, quota di crediti, tregua), prima fascia dei pericoli, avviso sui salti |
| `loot` | 19 | probabilità di drop per sorgente, rarità (in PvP), doppio drop, recupero in Leghe, costi di potenziamento, probabilità di consumabili, reperti e progetti |
| `hardware` | 18 | listino del Cantiere: sonde, mine, capsula, scanner, transwarp, occultamento, Genesi, laser |
| `combat` | 13 | costo in turni dell'attacco, danni di caccia e mine, taglie, bottino, assalto ai porti |
| `planet` | 13 | capacità e produzione per tipo, crescita dei coloni, Citadel, Quasar (con livello massimo), bombardamento |
| `craft` | 10 | raffineria (ricette e rese), durata dei lavori d'Officina, industria dei pianeti |
| `npc` | 7 | popolazione di Ferrengi e mercanti, ritmo di movimento e di nascita, esperienza per uccisione, regione madre dei Ferrengi |
| `mine` | 8 | giacimenti di asteroidi: rese, cristalli, costo in turni, densità per fascia |
| `universe` | 6 | numero di settori, estensione della Federazione, parametri del generatore |
| `blackmarket` | 5 | premio di vendita, sconto hardware, allineamento speso, pulizia della taglia |
| `subsys` | 5 | guasti ai sottosistemi: probabilità, riparazione, Ingegnere |
| `auth` | 5 | durata dei collegamenti di verifica e recupero, freno e tetto sui rinvii, giorni dopo cui un'iscrizione non confermata decade |
| `mail` | 4 | ritentativi, tetto giornaliero, messaggi per battito, potatura della coda |
| `turns` | 4 | turni al giorno e ora del reset |
| `season` | 4 | numero di stagione, dimensione dell'albo, cosa azzerare alla chiusura |
| `cloak` | 6 | occultamento: penalità di warp, divieto in Fedspace, riserva di cariche e ricarica, probabilità di essere scoperti per fascia e contro pattuglie ed élite |
| `contract`, `corp`, `eps`, `events`, `encounter`, `limpet`, `live`, `radio`, `rating`, `tick`, `bank`, `fednews`, `ranks` | 2–4 ciascuna | contratti e taglie, corporazioni, griglia di potenza, eventi globali, incontri a warp, mine limpet, stream SSE, radio, classifica, salute del clock, banca, notiziario, soglie di allineamento |
| `deploy`, `digest`, `game`, `limits`, `media`, `nav`, `newbie`, `onboarding`, `player`, `registration`, `security`, `shiplog`, `transwarp` | 1 ciascuna | tetto del pedaggio, rapporto di rientro, stato del gioco, freno sulle azioni, approvazione automatica delle immagini, autopilota, protezione novizio, ricompensa dei primi passi, dotazione iniziale, iscrizioni aperte/chiuse, lunghezza minima della password, capienza del giornale, costo del transwarp |

Le più utili da conoscere subito:

| Chiave | Default | |
|---|---|---|
| `registration.open` | `verify` | `verify` = aperte con conferma dell'indirizzo · `closed` = chiuse |
| `security.password_min_length` | `9` | lunghezza minima della password. Il **valore consigliato** in tabella resta `10`, quindi il pulsante «↺» la rialza: è deliberato, su una soglia di sicurezza la raccomandazione non scende con la concessione. `Auth::minPasswordLength()` applica comunque un pavimento a 6, così un refuso non può azzerare il controllo |
| `newbie.protect_hours` | `48` | durata della protezione novizio |
| `turns.per_day`, `turns.reset_hour` | `2500`, `3` | budget di azioni e ora del rifornimento |
| `media.auto_approve` | `1` | `0` riporta avatar e logo alla coda di moderazione |
| `mail.tetto_24h` | `140` | tetto di invii al giorno. **Abbassalo se il tuo account SMTP è condiviso con un altro servizio**: ciascuno conta solo i propri |
| `limits.player_actions_per_min` | `120` | freno sulle azioni di gioco per giocatore |
| `auth.pending_ttl_days` | `7` | un'iscrizione mai confermata decade dopo questi giorni dall'ultimo collegamento spedito, e il nome utente torna libero |
| `deploy.toll_max` | `5000` | pedaggio massimo dei caccia schierati: viene prelevato in automatico a chi entra e può pagarlo |
| `player.start_credits`, `player.start_ship` | | dotazione del comandante appena creato |

**Una nota sul pulsante «↺»**: riporta la chiave al suo `default_value`, che è
il valore *di progetto*, non quello che avevi prima. Se hai tarato qualcosa a
mano e vuoi poterci tornare, annotatelo — oppure cambia anche il default in
tabella.

## Prove

```bash
php tests/run.php                # tutte
php tests/run.php economica      # solo i file col nome che contiene "economica"
```

Suite di integrazione senza dipendenze, 606 verifiche in 28 file: integrità
economica, concorrenza, banca/contratti/Officina, combattimento, navigazione,
nave e moduli, pianeti, porti, equipaggio, mondo, percorsi di gioco normali,
universo, clock, sessioni e turni, difese, iscrizione e posta, immagini,
configurazione, schema, notifiche in tempo reale, fasce di rischio, legge federale, bottino, il quarto audit, e le regole decise per mercato nero, uccisioni e
stagioni. Ogni correzione ha una prova costruita per fallire sul codice di
prima. Quando la gara sta nel database, la prova fa la prima richiesta a mano
in una transazione aperta (blocca la riga e la cambia) e lancia la seconda in
un processo separato, confermando solo quando quella aspetta: l'intreccio è
sempre lo stesso, senza affidarsi al caso. Le prove di concorrenza lanciano processi separati con una barriera
comune (`tests/_corsa.php`): due richieste dello stesso giocatore nello stesso
istante sono un caso reale, non teorico.

**Attenzione**: non esiste un database di prova separato. Le prove girano su
quello configurato, ma creano e distruggono solo righe sintetiche col prefisso
`__test_`, e ripuliscono anche in caso di errore. Da non lanciare mentre è in
corso una sessione di gioco affollata: aprono transazioni su tabelle vive.

## Console

| Comando | |
|---|---|
| `php bin/console.php migrate` | applica le migrazioni SQL |
| `db:fresh` | elimina tutte le tabelle e ri-migra |
| `make:admin <user> [email]` | crea/promuove un amministratore |
| `user:approve <user>` · `user:list` | gestione utenti |
| `user:passwd <user>` | reimposta la password (chiede conferma, invalida le sessioni aperte) |
| `universe:generate [--force] [--sectors=N]` | genera universo + porti |
| `ports:generate [--force]` · `universe:stats` | economia |
| `economy:drift` · `bank:accrue` | passi manuali di simulazione |
| `config:get [chiave]` · `config:set <chiave> <valore>` | configurazione di gioco |

## Struttura

```
index.php              front controller unico (routing via PATH_INFO / FallbackResource)
src/Core/              Router, Database (PDO), Request, Response, Session, Csrf, RateLimiter,
                       View, Config, Mailer (SMTP minimale), Posta (coda con ritentativi)
src/Auth/              iscrizione, autovalidazione dell'indirizzo, recupero password,
                       gettoni monouso (Auth), testi dei messaggi (AuthMail)
src/Game/              logica di gioco: Universe, Navigation, Economy, Haggle, Bank, Shipyard,
                       Combat, Deploy, Loot, Modules, ShipStats, Crew, AwayMissions, Planets,
                       Corp, Contracts, Faction, SectorFeatures, Codex, Industry, Npc, Events,
                       Season, Achievements, BlackMarket, Radio, Leaderboard, Live, ShipLog,
                       Digest, Onboarding, Notifier, TurnManager, Ranks, GameConfig, Ctx,
                       Wallet (movimenti atomici), TickHealth (salute del clock),
                       MediaAsset (immagini caricate), Cloak, Limpet, PowerGrid, Subsystems
src/Controllers/       Home, Auth, Admin, AdminGame, Game, GameApi, ShipLog, Port, Bank,
                       Shipyard, Module, Combat, Crew, Mission, Scan, Faction, Codex, Planet,
                       Corp, Radio, Leaderboard, Registro, Meta
src/Cli/Migrator.php   migratore SQL minimale
src/routes.php         tabella delle rotte
views/                 template PHP (layout, auth/*, game/*, admin/*, errors/*)
assets/                css/js statici + icone PWA
                       (js/ritaglio.js = inquadratura dell'avatar lato browser)
tests/                 suite di integrazione, `php tests/run.php`
db/migrations/         *.sql versionati    ·    db/setup.sql = bootstrap DB/utente
bin/                   console.php, migrate.php, tick.php (il clock)
deploy/                apache-subspazio.conf
```

## Sicurezza

- **Password** con Argon2id (`m=65536,t=4,p=2`). Il percorso di login costa lo
  stesso tempo che l'utente esista o no, così i tempi di risposta non dicono
  chi è iscritto.
- **Gettoni monouso** per verifica dell'indirizzo e recupero password: in
  tabella finisce solo l'impronta `sha256`, con scadenza, e chiederne uno nuovo
  invalida il precedente. Il recupero incrementa `session_epoch`, che chiude le
  sessioni già aperte — se la password è stata cambiata perché qualcun altro se
  l'era presa, lasciargli la sessione aperta vanificherebbe il cambio.
- **Niente enumerazione**: rinvio della verifica e recupero password rispondono
  allo stesso modo che l'indirizzo esista o no.
- **Token CSRF** su ogni form, **rate limiting** su login, iscrizione, radio e
  azioni di gioco.
- **Azioni server-authoritative e transazionali**: i vincoli su crediti, turni,
  stive e risorse a colpo singolo stanno nella `WHERE` dell'`UPDATE`, dove il
  database li applica una volta sola — non su una copia in memoria letta a
  inizio richiesta. Due richieste concorrenti dello stesso comandante non
  possono spendere lo stesso saldo né raccogliere due volte lo stesso deposito.
- **Immagini caricate** ricodificate lato server con GD (EXIF e payload
  spogliati), MIME riconosciuto dal contenuto e non dal nome, limiti di
  dimensione e lato.
- **Hardening** delle directory non pubbliche via `.htaccess`; i file caricati
  vivono fuori dal web e fuori da git.
- **I segreti vivono fuori dal DocumentRoot**; `db/setup.sql` nel repository
  contiene un placeholder.

## Licenza

GNU General Public License v3.0 or later — vedi [LICENSE](LICENSE).
