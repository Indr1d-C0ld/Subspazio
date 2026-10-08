-- 0061_traguardi : traguardi molto piu' ricchi, contatori di carriera, titoli
--
-- I traguardi erano 18, tutte soglie semplici, e non conoscevano niente di
-- cio' che e' arrivato dopo (fasce, elite, legge, moduli rari, collezioni).
-- Ora 91 traguardi nuovi in sei categorie, a livelli (bronzo, argento,
-- oro, platino) e alcuni segreti, letti da contatori di carriera che
-- sopravvivono alle stagioni. I punti sbloccano titoli onorifici; alcuni
-- traguardi danno un titolo proprio. Prestigio, non potere: nessun vantaggio
-- in partita.

CREATE TABLE IF NOT EXISTS player_stats (
  player_id BIGINT UNSIGNED NOT NULL,
  chiave    VARCHAR(40)     NOT NULL,
  valore    BIGINT          NOT NULL DEFAULT 0,
  PRIMARY KEY (player_id, chiave),
  CONSTRAINT fk_stats_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE achievements ADD COLUMN IF NOT EXISTS categoria VARCHAR(24) NOT NULL DEFAULT 'carriera',
                         ADD COLUMN IF NOT EXISTS livello VARCHAR(10) NULL,
                         ADD COLUMN IF NOT EXISTS segreto TINYINT(1) NOT NULL DEFAULT 0,
                         ADD COLUMN IF NOT EXISTS contatore VARCHAR(40) NULL,
                         ADD COLUMN IF NOT EXISTS soglia BIGINT NULL,
                         ADD COLUMN IF NOT EXISTS titolo VARCHAR(40) NULL;

ALTER TABLE players ADD COLUMN IF NOT EXISTS titolo VARCHAR(40) NULL AFTER motto;

UPDATE achievements SET categoria = 'commercio' WHERE ckey = 'first_trade';
UPDATE achievements SET categoria = 'commercio' WHERE ckey = 'millionaire';
UPDATE achievements SET categoria = 'commercio' WHERE ckey = 'tycoon';
UPDATE achievements SET categoria = 'commercio' WHERE ckey = 'black_market';
UPDATE achievements SET categoria = 'combattimento' WHERE ckey = 'first_kill';
UPDATE achievements SET categoria = 'combattimento' WHERE ckey = 'warlord';
UPDATE achievements SET categoria = 'combattimento' WHERE ckey = 'ferrengi_hunter';
UPDATE achievements SET categoria = 'combattimento' WHERE ckey = 'port_buster';
UPDATE achievements SET categoria = 'sopravvivenza' WHERE ckey = 'pod_survivor';
UPDATE achievements SET categoria = 'esplorazione' WHERE ckey = 'explorer_100';
UPDATE achievements SET categoria = 'esplorazione' WHERE ckey = 'explorer_500';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'first_planet';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'colonizer';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'citadel_master';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'quasar_builder';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'corp_founder';
UPDATE achievements SET categoria = 'legge' WHERE ckey = 'contract_claim';
UPDATE achievements SET categoria = 'carriera' WHERE ckey = 'season_top10';

INSERT INTO achievements (ckey, name, descr, icon, points, sort_order, categoria, livello, segreto, contatore, soglia, titolo) VALUES
  ('scambi_bronzo', 'Bottegaio', 'Concludi 25 scambi ai porti.', '$', 10, 1000, 'commercio', 'bronzo', 0, 'scambi', 25, NULL),
  ('scambi_argento', 'Mercante', 'Concludi 250 scambi ai porti.', '$', 25, 1010, 'commercio', 'argento', 0, 'scambi', 250, NULL),
  ('scambi_oro', 'Armatore', 'Concludi 1.500 scambi ai porti.', '$', 50, 1020, 'commercio', 'oro', 0, 'scambi', 1500, NULL),
  ('scambi_platino', 'Principe dei mercanti', 'Concludi 6.000 scambi ai porti.', '$', 100, 1030, 'commercio', 'platino', 0, 'scambi', 6000, NULL),
  ('volume_bronzo', 'Giro d\'affari', 'Muovi 100.000 cr di merce.', '¤', 10, 1040, 'commercio', 'bronzo', 0, 'volume_commercio', 100000, NULL),
  ('volume_argento', 'Casa commerciale', 'Muovi 2 milioni di cr di merce.', '¤', 25, 1050, 'commercio', 'argento', 0, 'volume_commercio', 2000000, NULL),
  ('volume_oro', 'Consorzio', 'Muovi 25 milioni di cr di merce.', '¤', 50, 1060, 'commercio', 'oro', 0, 'volume_commercio', 25000000, NULL),
  ('volume_platino', 'Banca delle stelle', 'Muovi 200 milioni di cr di merce.', '¤', 100, 1070, 'commercio', 'platino', 0, 'volume_commercio', 200000000, NULL),
  ('orlo_argento', 'Rotte del Buio', 'Concludi 25 scambi nei porti dell\'Orlo.', '⇄', 25, 1080, 'commercio', 'argento', 0, 'scambi_orlo', 25, NULL),
  ('orlo_oro', 'Corsaro dell\'Orlo', 'Concludi 250 scambi nei porti dell\'Orlo.', '⇄', 50, 1090, 'commercio', 'oro', 0, 'scambi_orlo', 250, NULL),
  ('nero_bronzo', 'Cliente abituale', 'Cinque affari al mercato nero.', 'b', 10, 1100, 'commercio', 'bronzo', 0, 'mercato_nero', 5, NULL),
  ('nero_argento', 'Contrabbandiere', 'Cinquanta affari al mercato nero.', 'b', 25, 1110, 'commercio', 'argento', 0, 'mercato_nero', 50, NULL),
  ('pirati_bronzo', 'Scacciapredoni', 'Abbatti 10 predoni.', '☠', 10, 1120, 'combattimento', 'bronzo', 0, 'pirati_abbattuti', 10, NULL),
  ('pirati_argento', 'Flagello dei predoni', 'Abbatti 75 predoni.', '☠', 25, 1130, 'combattimento', 'argento', 0, 'pirati_abbattuti', 75, NULL),
  ('pirati_oro', 'Terrore dei pirati', 'Abbatti 300 predoni.', '☠', 50, 1140, 'combattimento', 'oro', 0, 'pirati_abbattuti', 300, NULL),
  ('pirati_platino', 'Ultima speranza della Frontiera', 'Abbatti 1.000 predoni.', '☠', 100, 1150, 'combattimento', 'platino', 0, 'pirati_abbattuti', 1000, NULL),
  ('ferrengi_argento', 'Esattore di debiti', 'Abbatti 25 navi Ferrengi.', 'F', 25, 1160, 'combattimento', 'argento', 0, 'ferrengi_abbattuti', 25, NULL),
  ('ferrengi_oro', 'Bancarotta Ferrengi', 'Abbatti 150 navi Ferrengi.', 'F', 50, 1170, 'combattimento', 'oro', 0, 'ferrengi_abbattuti', 150, NULL),
  ('ferrengi_platino', 'Incubo del Nagus', 'Abbatti 500 navi Ferrengi.', 'F', 100, 1180, 'combattimento', 'platino', 0, 'ferrengi_abbattuti', 500, NULL),
  ('elite_argento', 'Ammazzagiganti', 'Abbatti un comandante d\'élite.', '♛', 25, 1190, 'combattimento', 'argento', 0, 'elite_abbattute', 1, NULL),
  ('elite_oro', 'Cacciatore di leggende', 'Abbatti 10 comandanti d\'élite.', '♛', 50, 1200, 'combattimento', 'oro', 0, 'elite_abbattute', 10, NULL),
  ('elite_platino', 'Fine delle leggende', 'Abbatti 40 comandanti d\'élite.', '♛', 100, 1210, 'combattimento', 'platino', 0, 'elite_abbattute', 40, NULL),
  ('npc_orlo_argento', 'Battesimo dell\'Orlo', 'Abbatti 10 nemici nell\'Orlo del Buio.', '✦', 25, 1220, 'combattimento', 'argento', 0, 'abbattuti_orlo', 10, NULL),
  ('npc_orlo_oro', 'Signore dell\'Orlo', 'Abbatti 100 nemici nell\'Orlo del Buio.', '✦', 50, 1230, 'combattimento', 'oro', 0, 'abbattuti_orlo', 100, NULL),
  ('pvp_bronzo', 'Primo sangue', 'Abbatti la nave di un altro comandante.', '⚔', 10, 1240, 'combattimento', 'bronzo', 0, 'comandanti_abbattuti', 1, NULL),
  ('pvp_argento', 'Duellante', 'Abbatti 10 navi di altri comandanti.', '⚔', 25, 1250, 'combattimento', 'argento', 0, 'comandanti_abbattuti', 10, NULL),
  ('pvp_oro', 'Asso', 'Abbatti 50 navi di altri comandanti.', '⚔', 50, 1260, 'combattimento', 'oro', 0, 'comandanti_abbattuti', 50, NULL),
  ('porti_bronzo', 'Predone dei porti', 'Espugna un porto.', 'x', 10, 1270, 'combattimento', 'bronzo', 0, 'porti_espugnati', 1, NULL),
  ('porti_oro', 'Saccheggiatore', 'Espugna 25 porti.', 'x', 50, 1280, 'combattimento', 'oro', 0, 'porti_espugnati', 25, NULL),
  ('salti_bronzo', 'Viaggiatore', 'Compi 100 salti di warp.', '»', 10, 1290, 'esplorazione', 'bronzo', 0, 'salti', 100, NULL),
  ('salti_argento', 'Navigatore', 'Compi 2.000 salti di warp.', '»', 25, 1300, 'esplorazione', 'argento', 0, 'salti', 2000, NULL),
  ('salti_oro', 'Vagabondo delle stelle', 'Compi 15.000 salti di warp.', '»', 50, 1310, 'esplorazione', 'oro', 0, 'salti', 15000, NULL),
  ('salti_platino', 'L\'eterno viandante', 'Compi 60.000 salti di warp.', '»', 100, 1320, 'esplorazione', 'platino', 0, 'salti', 60000, NULL),
  ('settori_bronzo', 'Cartografo', 'Scopri 100 settori.', '#', 10, 1330, 'esplorazione', 'bronzo', 0, 'settori_scoperti', 100, NULL),
  ('settori_argento', 'Esploratore', 'Scopri 500 settori.', '#', 25, 1340, 'esplorazione', 'argento', 0, 'settori_scoperti', 500, NULL),
  ('settori_oro', 'Atlante vivente', 'Scopri 2.500 settori, stagione dopo stagione.', '#', 50, 1350, 'esplorazione', 'oro', 0, 'settori_scoperti', 2500, NULL),
  ('fascia_3', 'Oltre la Cintura', 'Raggiungi la Frontiera (fascia III).', '③', 10, 1360, 'esplorazione', 'bronzo', 0, 'fascia_max', 3, NULL),
  ('fascia_5', 'Sull\'Orlo del Buio', 'Raggiungi l\'Orlo del Buio (fascia V).', '⑤', 25, 1370, 'esplorazione', 'argento', 0, 'fascia_max', 5, NULL),
  ('anomalie_bronzo', 'Curioso', 'Risolvi un\'anomalia.', '∿', 10, 1380, 'esplorazione', 'bronzo', 0, 'anomalie_risolte', 1, NULL),
  ('anomalie_argento', 'Scienziato di bordo', 'Risolvi 15 anomalie.', '∿', 25, 1390, 'esplorazione', 'argento', 0, 'anomalie_risolte', 15, NULL),
  ('anomalie_oro', 'Mente dell\'ignoto', 'Risolvi 75 anomalie.', '∿', 50, 1400, 'esplorazione', 'oro', 0, 'anomalie_risolte', 75, NULL),
  ('relitti_bronzo', 'Recuperatore', 'Spoglia 5 relitti.', '⚓', 10, 1410, 'esplorazione', 'bronzo', 0, 'relitti_spogliati', 5, NULL),
  ('relitti_argento', 'Sciacallo', 'Spoglia 50 relitti.', '⚓', 25, 1420, 'esplorazione', 'argento', 0, 'relitti_spogliati', 50, NULL),
  ('relitti_oro', 'Cimitero delle navi', 'Spoglia 250 relitti.', '⚓', 50, 1430, 'esplorazione', 'oro', 0, 'relitti_spogliati', 250, NULL),
  ('estrazioni_bronzo', 'Minatore', 'Estrai 10 volte da un giacimento.', '⛏', 10, 1440, 'esplorazione', 'bronzo', 0, 'estrazioni', 10, NULL),
  ('estrazioni_argento', 'Capominiera', 'Estrai 150 volte.', '⛏', 25, 1450, 'esplorazione', 'argento', 0, 'estrazioni', 150, NULL),
  ('occultati_argento', 'Ombra fra le stelle', 'Compi 100 salti occultato.', '🌫', 25, 1460, 'esplorazione', 'argento', 0, 'salti_occultati', 100, NULL),
  ('occultati_oro', 'Fantasma', 'Compi 1.000 salti occultato.', '🌫', 50, 1470, 'esplorazione', 'oro', 0, 'salti_occultati', 1000, NULL),
  ('moduli_bronzo', 'Raccoglitore', 'Trova 5 moduli.', '⚙', 10, 1480, 'bottino', 'bronzo', 0, 'moduli_trovati', 5, NULL),
  ('moduli_argento', 'Collezionista di parti', 'Trova 50 moduli.', '⚙', 25, 1490, 'bottino', 'argento', 0, 'moduli_trovati', 50, NULL),
  ('moduli_oro', 'Arsenale ambulante', 'Trova 300 moduli.', '⚙', 50, 1500, 'bottino', 'oro', 0, 'moduli_trovati', 300, NULL),
  ('raro_xeno', 'Tecnologia aliena', 'Trova un modulo Xeno.', '◈', 25, 1510, 'bottino', 'argento', 0, 'moduli_xeno', 1, NULL),
  ('raro_precursore', 'Eredità dei Precursori', 'Trova un modulo Precursore.', '◆', 50, 1520, 'bottino', 'oro', 0, 'moduli_precursor', 1, NULL),
  ('raro_precursore_10', 'Archeologo del futuro', 'Trova 10 moduli Precursore.', '◆◆', 100, 1530, 'bottino', 'platino', 0, 'moduli_precursor', 10, NULL),
  ('tre_affissi', 'Pezzo unico', 'Trova un modulo con tre affissi.', '✧', 50, 1540, 'bottino', 'oro', 0, 'affissi_max', 3, NULL),
  ('potenziamenti_bronzo', 'Meccanico', 'Potenzia un modulo all\'Officina.', '↑', 10, 1550, 'bottino', 'bronzo', 0, 'potenziamenti', 1, NULL),
  ('potenziamenti_argento', 'Ingegnere', 'Potenzia 20 moduli.', '↑', 25, 1560, 'bottino', 'argento', 0, 'potenziamenti', 20, NULL),
  ('prodotti_bronzo', 'Artigiano', 'Produci un modulo su ricetta.', '⚒', 10, 1570, 'bottino', 'bronzo', 0, 'moduli_prodotti', 1, NULL),
  ('prodotti_argento', 'Officina d\'arte', 'Produci 25 moduli su ricetta.', '⚒', 25, 1580, 'bottino', 'argento', 0, 'moduli_prodotti', 25, NULL),
  ('progetti_argento', 'Progettista', 'Trova un progetto d\'Officina.', '📐', 25, 1590, 'bottino', 'argento', 0, 'progetti', 1, NULL),
  ('progetti_oro', 'Biblioteca dei progetti', 'Trova tutti e dieci i progetti d\'Officina.', '📐', 50, 1600, 'bottino', 'oro', 0, 'progetti', 10, NULL),
  ('reperti_bronzo', 'Antiquario', 'Trova 5 reperti.', '🏺', 10, 1610, 'bottino', 'bronzo', 0, 'reperti_trovati', 5, NULL),
  ('reperti_argento', 'Mercante d\'antichità', 'Trova 50 reperti.', '🏺', 25, 1620, 'bottino', 'argento', 0, 'reperti_trovati', 50, NULL),
  ('collezione_1', 'Collezionista', 'Completa una collezione di reperti.', '❖', 25, 1630, 'bottino', 'argento', 0, 'collezioni', 1, NULL),
  ('collezione_3', 'Curatore', 'Completa tutte e tre le collezioni.', '❖❖❖', 100, 1640, 'bottino', 'platino', 0, 'collezioni', 3, 'Curatore delle Reliquie'),
  ('consumabili_bronzo', 'Previdente', 'Usa 5 consumabili.', '🎒', 10, 1650, 'bottino', 'bronzo', 0, 'consumabili_usati', 5, NULL),
  ('consumabili_argento', 'Sempre pronto', 'Usa 50 consumabili.', '🎒', 25, 1660, 'bottino', 'argento', 0, 'consumabili_usati', 50, NULL),
  ('ricercato', 'Faccia da ricercato', 'Diventa Ricercato per la Federazione.', '⚖', 10, 1670, 'legge', 'bronzo', 0, 'gradino_max', 2, NULL),
  ('nemico_pubblico', 'Nemico pubblico', 'Raggiungi il gradino di Nemico pubblico.', '☣', 50, 1680, 'legge', 'oro', 1, 'gradino_max', 4, 'Nemico Pubblico'),
  ('arrestato', 'Dietro le sbarre', 'Fatti arrestare da una pattuglia federale.', '⛓', 10, 1690, 'legge', 'bronzo', 1, 'arresti', 1, NULL),
  ('ammenda', 'Fedina pulita', 'Paga un\'ammenda alla Federazione.', '✓', 10, 1700, 'legge', 'bronzo', 0, 'ammende_pagate', 1, NULL),
  ('pattuglie', 'Fuorilegge', 'Abbatti 5 pattuglie federali.', '✶', 50, 1710, 'legge', 'oro', 1, 'pattuglie_abbattute', 5, 'Fuorilegge'),
  ('taglie_bronzo', 'Cacciatore di taglie', 'Riscuoti una taglia federale.', '◎', 10, 1720, 'legge', 'bronzo', 0, 'taglie_riscosse', 1, NULL),
  ('taglie_argento', 'Segugio', 'Riscuoti 10 taglie.', '◎', 25, 1730, 'legge', 'argento', 0, 'taglie_riscosse', 10, NULL),
  ('taglie_oro', 'Giustiziere', 'Riscuoti 50 taglie.', '◎', 50, 1740, 'legge', 'oro', 0, 'taglie_riscosse', 50, NULL),
  ('razziato', 'Tasche vuote', 'Fatti razziare dai predoni.', '⊘', 10, 1750, 'sopravvivenza', 'bronzo', 1, 'razzie_subite', 1, NULL),
  ('razziato_10', 'Calamita per predoni', 'Fatti razziare dieci volte.', '⊘⊘', 25, 1760, 'sopravvivenza', 'argento', 1, 'razzie_subite', 10, NULL),
  ('navi_perse_10', 'Fenice', 'Perdi dieci navi e torna sempre in rotta.', '🔥', 25, 1770, 'sopravvivenza', 'argento', 1, 'navi_perse', 10, 'Fenice'),
  ('mercantili', 'Pirata senza scrupoli', 'Abbatti 25 mercantili.', '⚑', 25, 1780, 'sopravvivenza', 'argento', 1, 'mercantili_abbattuti', 25, NULL),
  ('missioni_bronzo', 'Squadra d\'abbordaggio', 'Porta a termine 3 missioni con successo.', '✪', 10, 1790, 'carriera', 'bronzo', 0, 'missioni_riuscite', 3, NULL),
  ('missioni_argento', 'Ufficiali di razza', 'Porta a termine 30 missioni con successo.', '✪', 25, 1800, 'carriera', 'argento', 0, 'missioni_riuscite', 30, NULL),
  ('missioni_oro', 'Leggenda dell\'equipaggio', 'Porta a termine 150 missioni con successo.', '✪', 50, 1810, 'carriera', 'oro', 0, 'missioni_riuscite', 150, NULL),
  ('ufficiali_bronzo', 'Primo ufficiale', 'Assumi un ufficiale.', '☺', 10, 1820, 'carriera', 'bronzo', 0, 'ufficiali_assunti', 1, NULL),
  ('ufficiali_argento', 'Talent scout', 'Assumi 15 ufficiali.', '☺', 25, 1830, 'carriera', 'argento', 0, 'ufficiali_assunti', 15, NULL),
  ('pianeti_argento', 'Seminatore di mondi', 'Crea 5 pianeti con il siluro Genesi.', '◍', 25, 1840, 'carriera', 'argento', 0, 'pianeti_creati', 5, NULL),
  ('pianeti_oro', 'Architetto planetario', 'Crea 25 pianeti.', '◍', 50, 1850, 'carriera', 'oro', 0, 'pianeti_creati', 25, NULL),
  ('stagioni_bronzo', 'Una stagione alle spalle', 'Gioca una stagione intera fino alla chiusura.', '⌛', 10, 1860, 'carriera', 'bronzo', 0, 'stagioni', 1, NULL),
  ('stagioni_argento', 'Veterano', 'Gioca tre stagioni.', '⌛', 25, 1870, 'carriera', 'argento', 0, 'stagioni', 3, NULL),
  ('stagioni_oro', 'Pilastro della galassia', 'Gioca dieci stagioni.', '⌛', 50, 1880, 'carriera', 'oro', 0, 'stagioni', 10, NULL),
  ('podio', 'Sul podio', 'Chiudi una stagione fra i primi tre.', '▲', 50, 1890, 'carriera', 'oro', 0, 'podi', 1, NULL),
  ('campione', 'Campione della galassia', 'Vinci una stagione.', '♔', 100, 1900, 'carriera', 'platino', 0, 'vittorie', 1, 'Campione')
ON DUPLICATE KEY UPDATE name = VALUES(name), descr = VALUES(descr), icon = VALUES(icon), points = VALUES(points),
  sort_order = VALUES(sort_order), categoria = VALUES(categoria), livello = VALUES(livello), segreto = VALUES(segreto),
  contatore = VALUES(contatore), soglia = VALUES(soglia), titolo = VALUES(titolo);
