-- 0030_encounters : Slice C1 — tabella incontri data-driven. A ogni warp,
--   con probabilita' encounter.chance e rispettando un cooldown, l'engine
--   estrae un incontro compatibile col contesto (regione, allineamento,
--   fazioni, stive) e lo mette "in sospeso": sulla plancia compare un pannello
--   con 2-3 scelte, ognuna con esiti pesati ed eventuale skill-check
--   d'equipaggio. Motore: src/Game/Encounters.php.
--   NB: le stringhe SQL sono in singoli apici -> ogni apostrofo del testo va
--   raddoppiato ('') e gli accenti sono caratteri UTF-8 veri.

CREATE TABLE IF NOT EXISTS encounters (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ckey         VARCHAR(48) NOT NULL,
  title        VARCHAR(120) NOT NULL,
  body         TEXT NOT NULL,
  weight       SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  conditions   JSON NULL,
  choices      JSON NOT NULL,
  cooldown_min SMALLINT UNSIGNED NULL,
  once         TINYINT NOT NULL DEFAULT 0,
  enabled      TINYINT NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enc_ckey (ckey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_encounters (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id    BIGINT UNSIGNED NOT NULL,
  encounter_id INT UNSIGNED NOT NULL,
  sector_id    INT UNSIGNED NOT NULL,
  status       ENUM('pending','resolved','expired') NOT NULL DEFAULT 'pending',
  choice_key   VARCHAR(24) NULL,
  outcome_text VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME NULL,
  KEY idx_pe_player (player_id, status),
  KEY idx_pe_repeat (player_id, encounter_id, created_at),
  CONSTRAINT fk_pe_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_enc FOREIGN KEY (encounter_id) REFERENCES encounters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('encounter.chance',                  '15',  'int'),
  ('encounter.cooldown_min',            '10',  'int'),
  ('encounter.per_encounter_repeat_min','720', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue;

INSERT INTO encounters (ckey, title, body, weight, conditions, choices) VALUES
(
  'mercantile_deriva',
  'Mercantile alla deriva',
  'Un cargo civile galleggia inerte, luci di posizione fioche. Sul canale d''emergenza una voce roca chiede assistenza per riavviare i motori.',
  14,
  JSON_OBJECT('min_align', -400),
  JSON_ARRAY(
    JSON_OBJECT('key','aiuta','label','Presta soccorso','skill',JSON_OBJECT('role','engineer','dc',4),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','Rimetti in moto il reattore. L''equipaggio ringrazia e la Federazione registra il gesto.','effects',JSON_OBJECT('faction',JSON_OBJECT('fed',4),'credits',600,'experience',15)),
        JSON_OBJECT('weight',2,'if','fail','text','Armeggi per un''ora senza risultato. Ti salutano con un cenno stanco.','effects',JSON_OBJECT('experience',4)))),
    JSON_OBJECT('key','saccheggia','label','Approfitta della loro impotenza',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','Trasferisci a bordo quanto trovi nelle stive. Un lavoro sporco.','effects',JSON_OBJECT('cargo',JSON_OBJECT('equipment',12),'alignment',-25,'faction',JSON_OBJECT('fed',-6))))),
    JSON_OBJECT('key','ignora','label','Prosegui la rotta',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Non sono affari tuoi. Ti allontani.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'sonda_precursore',
  'Sonda dormiente',
  'Un manufatto affusolato, lungo quanto una scialuppa, ruota lento su se stesso. Materiali che non riconosci. Nessun segnale ostile.',
  10,
  JSON_OBJECT('not_fedspace', true, 'region_kind', JSON_ARRAY('frontier','deep')),
  JSON_ARRAY(
    JSON_OBJECT('key','studia','label','Analizzala a distanza di sicurezza','skill',JSON_OBJECT('role','scientist','dc',5),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','I sensori catturano schemi di risonanza preziosi. Ne ricavi cristalli e dati.','effects',JSON_OBJECT('crystals',3,'components',2,'experience',20)),
        JSON_OBJECT('weight',2,'if','fail','text','Un impulso ti acceca gli strumenti per qualche minuto. Niente di grave, niente di utile.','effects',JSON_OBJECT('nothing',true)))),
    JSON_OBJECT('key','recupera','label','Aggancia e portala via',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',2,'text','Un collezionista allo StarDock pagherebbe bene un pezzo del genere.','effects',JSON_OBJECT('credits',1400)),
        JSON_OBJECT('weight',1,'text','Nel maneggiarla, una scarica investe lo scafo.','effects',JSON_OBJECT('shields',-300)))),
    JSON_OBJECT('key','lascia','label','Meglio non svegliare il can che dorme',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Segni la posizione sul giornale e prosegui.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'pattuglia_fed',
  'Pattuglia della Federazione: controllo carico',
  'Due corvette con insegne della Flotta ti intimano l''alt per un''ispezione di routine.',
  12,
  JSON_OBJECT('is_fedspace', true),
  JSON_ARRAY(
    JSON_OBJECT('key','collabora','label','Apri le stive all''ispezione',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','Tutto in regola. Ti augurano buona rotta e annotano la collaborazione.','effects',JSON_OBJECT('faction',JSON_OBJECT('fed',3))))),
    JSON_OBJECT('key','mancia','label','Offri una tassa di semplificazione',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',2,'text','L''ufficiale intasca e ti fa cenno di passare. Sbrigativo.','effects',JSON_OBJECT('credits',-450)),
        JSON_OBJECT('weight',1,'text','L''ufficiale si offende. Multa e nota sul registro.','effects',JSON_OBJECT('credits',-900,'faction',JSON_OBJECT('fed',-5))))),
    JSON_OBJECT('key','fuggi','label','Spingi i motori e semina la pattuglia',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','Li distanzi, ma il tuo transponder ormai e'' schedato.','effects',JSON_OBJECT('turns',-2,'faction',JSON_OBJECT('fed',-10),'alignment',-15)))))
),
(
  'relitto_battaglia',
  'Relitto di battaglia',
  'Scafi spezzati e detriti alla deriva, resti di uno scontro recente. Fra i rottami potrebbe esserci di tutto.',
  13,
  JSON_OBJECT('not_fedspace', true, 'needs_free_hold', true),
  JSON_ARRAY(
    JSON_OBJECT('key','spoglia','label','Recupera materiale dai rottami',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'text','Leghe, componenti e qualche cassa intatta. Buon bottino.','effects',JSON_OBJECT('salvage',40,'components',2)),
        JSON_OBJECT('weight',1,'text','Un serbatoio cede mentre tagli una paratia. Lo scafo incassa il colpo.','effects',JSON_OBJECT('shields',-250,'salvage',15)))),
    JSON_OBJECT('key','superstiti','label','Cerca superstiti','skill',JSON_OBJECT('role','medic','dc',4),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',2,'if','pass','text','Estrai due naufraghi da una capsula semi-allagata. Li lasci alla prima stazione. Gesto notato.','effects',JSON_OBJECT('faction',JSON_OBJECT('fed',5),'experience',18)),
        JSON_OBJECT('weight',2,'if','fail','text','Nessuno vivo. Solo silenzio e freddo.','effects',JSON_OBJECT('experience',5)))),
    JSON_OBJECT('key','prosegui','label','Lascia perdere e riparti',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Non e'' il tuo cimitero. Vai oltre.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'contrabbandiere_ferrengi',
  'Contrabbandiere del Consorzio',
  'Una nave dal profilo irregolare ti affianca. Un mercante ferrengi apre un canale: ho merce che non troverai al listino, amico. Diamo un''occhiata?',
  11,
  JSON_OBJECT('not_fedspace', true),
  JSON_ARRAY(
    JSON_OBJECT('key','compra','label','Dai un''occhiata alla mercanzia',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'text','Fai un affare: un modulo sottobanco a prezzo di saldo.','effects',JSON_OBJECT('credits',-800,'module','mil','faction',JSON_OBJECT('ferrengi',3))),
        JSON_OBJECT('weight',1,'text','La merce e'' spazzatura ricondizionata. Hai buttato i crediti.','effects',JSON_OBJECT('credits',-500)))),
    JSON_OBJECT('key','denuncia','label','Segnala la posizione alla Federazione',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','La Flotta ringrazia per la soffiata. Il Consorzio, molto meno.','effects',JSON_OBJECT('faction',JSON_OBJECT('fed',6,'ferrengi',-12),'credits',300)))),
    JSON_OBJECT('key','ignora','label','Chiudi il canale',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Peggio per te, borbotta prima di sganciarsi.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'falso_sos',
  'Segnale di soccorso',
  'Un SOS automatico pulsa da un settore di detriti poco distante. Il pattern e'' regolare, quasi troppo.',
  10,
  JSON_OBJECT('not_fedspace', true),
  JSON_ARRAY(
    JSON_OBJECT('key','scansiona','label','Scansiona la sorgente prima di avvicinarti','skill',JSON_OBJECT('role','scientist','dc',4),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','I sensori rivelano firme di calore nascoste fra i rottami: un''imboscata. Cambi rotta in tempo.','effects',JSON_OBJECT('experience',12)),
        JSON_OBJECT('weight',1,'if','fail','text','Lettura confusa. Decidi di non rischiare comunque.','effects',JSON_OBJECT('nothing',true)))),
    JSON_OBJECT('key','rispondi','label','Rispondi e avvicinati',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',2,'text','Tre predoni sbucano dai detriti e ti scaricano addosso una salva prima che tu reagisca.','effects',JSON_OBJECT('shields',-500,'fighters',-8)),
        JSON_OBJECT('weight',1,'text','Era davvero un naufrago. Lo tiri a bordo: riconoscente e al verde.','effects',JSON_OBJECT('faction',JSON_OBJECT('fed',3))))),
    JSON_OBJECT('key','ignora','label','Ignora il segnale',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Tieni la rotta. Il segnale svanisce dietro di te.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'corriere_avaria',
  'Corriere in avaria',
  'Un corriere postale con una falla nella linea del refrigerante ti fa segno. Il pilota e'' giovane e spaventato.',
  10,
  JSON_OBJECT('not_fedspace', true, 'min_align', -200),
  JSON_ARRAY(
    JSON_OBJECT('key','ripara','label','Manda una squadra a bordo','skill',JSON_OBJECT('role','engineer','dc',5),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','Tamponi la falla e riallinei l''iniettore. Il ragazzo insiste per pagarti.','effects',JSON_OBJECT('credits',700,'alignment',10,'experience',15)),
        JSON_OBJECT('weight',2,'if','fail','text','Fai il possibile con quello che c''e''. Reggera'' fino alla prossima stazione, forse.','effects',JSON_OBJECT('alignment',5)))),
    JSON_OBJECT('key','requisisci','label','Requisisci il carico per sicurezza',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','Ti prendi le casse e lo lasci alla deriva. Non ti guarda nemmeno.','effects',JSON_OBJECT('cargo',JSON_OBJECT('equipment',10),'alignment',-20)))),
    JSON_OBJECT('key','prosegui','label','Non hai tempo da perdere',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Passi oltre. Speri che qualcun altro si fermi.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'nave_fantasma',
  'Nave fantasma',
  'Una vecchia nave da esplorazione procede a velocita'' di crociera, luci accese, nessuna risposta. Sui sensori l''equipaggio non risulta.',
  8,
  JSON_OBJECT('not_fedspace', true, 'region_kind', JSON_ARRAY('deep','frontier')),
  JSON_ARRAY(
    JSON_OBJECT('key','aggancia','label','Aggancia ed esplora','skill',JSON_OBJECT('role','scientist','dc',6),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',2,'if','pass','text','Nel diario di bordo trovi coordinate e annotazioni che valgono materiale raro.','effects',JSON_OBJECT('crystals',4,'components',3,'experience',25)),
        JSON_OBJECT('weight',2,'if','fail','text','Corridoi che si ripetono, echi che non dovrebbero esserci. Torni indietro scosso e a mani vuote.','effects',JSON_OBJECT('turns',-1)))),
    JSON_OBJECT('key','distanza','label','Tieniti a distanza e prosegui',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Alcune porte e'' meglio non aprirle.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'diplomatico_bloccato',
  'Delegato bloccato',
  'Uno yacht diplomatico dell''Egemonia ha esaurito il carburante. A bordo, un delegato impaziente diretto a un negoziato.',
  9,
  JSON_OBJECT('not_fedspace', true),
  JSON_ARRAY(
    JSON_OBJECT('key','passaggio','label','Offri un passaggio fino alla stazione','skill',JSON_OBJECT('role','diplomat','dc',4),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','Durante il viaggio il delegato apprezza i tuoi modi. L''Egemonia se ne ricordera''.','effects',JSON_OBJECT('faction',JSON_OBJECT('hegemony',7),'experience',12)),
        JSON_OBJECT('weight',2,'if','fail','text','Viaggio silenzioso e teso. Lo sbarchi senza troppi ringraziamenti.','effects',JSON_OBJECT('faction',JSON_OBJECT('hegemony',2))))),
    JSON_OBJECT('key','compenso','label','Un passaggio, ma non gratis',
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',1,'text','Paga il pizzo, ma se ne va con la faccia scura.','effects',JSON_OBJECT('credits',900,'faction',JSON_OBJECT('hegemony',-4))))),
    JSON_OBJECT('key','rifiuta','label','Non e'' un taxi',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Lo lasci ad aspettare qualcun altro.','effects',JSON_OBJECT('nothing',true)))))
),
(
  'campo_detriti',
  'Corridoio di detriti',
  'Un fronte di rottami taglia la tua rotta. Aggirarlo costa tempo, attraversarlo costa nervi.',
  12,
  JSON_OBJECT('not_fedspace', true),
  JSON_ARRAY(
    JSON_OBJECT('key','aggira','label','Rallenta e aggira il campo',
      'outcomes', JSON_ARRAY(JSON_OBJECT('weight',1,'text','Manovra prudente. Perdi tempo ma nessun graffio.','effects',JSON_OBJECT('turns',-1)))),
    JSON_OBJECT('key','attraversa','label','Attraversa a tutta velocita','skill',JSON_OBJECT('role','navigator','dc',5),
      'outcomes', JSON_ARRAY(
        JSON_OBJECT('weight',3,'if','pass','text','Slalom pulito fra i rottami. Ne esci senza un''ammaccatura.','effects',JSON_OBJECT('experience',10)),
        JSON_OBJECT('weight',3,'if','fail','text','Un frammento centra la fiancata. Poteva andare peggio.','effects',JSON_OBJECT('shields',-300)))))
);
