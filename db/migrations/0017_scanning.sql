-- 0017_scanning : Fase 9 — scansione & frontiera.
--   La scansione rivela le "feature" nascoste di un settore (relitti,
--   depositi, anomalie, pericoli). Le regioni profonde ne hanno di piu' e
--   migliori, ma colpiscono all'ingresso con hazard ambientali. Codex =
--   diario delle scoperte.

CREATE TABLE IF NOT EXISTS sector_features (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sector_id  INT UNSIGNED NOT NULL,
  kind       ENUM('wreck','cache','anomaly','hazard') NOT NULL,
  subtype    VARCHAR(24) NOT NULL DEFAULT '',
  richness   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  data       JSON NULL,
  spawned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  depleted   TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_sf_sector (sector_id, depleted),
  KEY idx_sf_kind (kind, depleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_feature_state (
  player_id     BIGINT UNSIGNED NOT NULL,
  feature_id    BIGINT UNSIGNED NOT NULL,
  discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  progress      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  resolved      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (player_id, feature_id),
  KEY idx_pfs_feat (feature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS codex_entries (
  ckey       VARCHAR(40) NOT NULL PRIMARY KEY,
  title      VARCHAR(80) NOT NULL,
  category   VARCHAR(24) NOT NULL DEFAULT 'generale',
  body       VARCHAR(700) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_codex (
  player_id   BIGINT UNSIGNED NOT NULL,
  entry_key   VARCHAR(40) NOT NULL,
  unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, entry_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO codex_entries (ckey, title, category, body, sort_order) VALUES
  ('scan_basics',   'Scansione attiva',        'scansione', 'Una scansione mirata costa turni ma rivela cio\' che un semplice colpo d\'occhio non vede: scafi alla deriva, depositi, anomalie e pericoli ambientali. Uno scanner migliore, o uno Scienziato in plancia, estende la portata ai settori vicini.', 10),
  ('wreck_generic', 'Relitti',                 'scansione', 'Scafi morti che galleggiano lungo le rotte meno battute. Spogliarli rende Leghe di recupero e, con fortuna, moduli di fascia alta. A volte fra le lamiere c\'e\' ancora qualcuno vivo.', 20),
  ('cache_generic', 'Depositi',                'scansione', 'Container agganciati a un relitto, casse di contrabbando, scorte dimenticate. Vanno svuotati in fretta prima che li trovi qualcun altro.', 30),
  ('anomaly_generic','Anomalie',               'scansione', 'Letture che non tornano. Servono piu\' passaggi di analisi — e uno Scienziato aiuta — per capirci qualcosa. Chi le risolve porta a casa dati preziosi.', 40),
  ('hazard_radiation','Fasce di radiazioni',   'frontiera', 'Cinture di particelle cariche. Attraversarle a scudi bassi e\' un errore che si paga. Se sai che ci sono, imposti la rotta per limitare i danni.', 50),
  ('hazard_gravity', 'Pozzi gravitazionali',   'frontiera', 'Distorsioni che risucchiano potenza dai motori: il salto costa di piu\'. Non passano mai: restano li\', a marcare i confini della mappa.', 60),
  ('hazard_ion',     'Tempeste ioniche',       'frontiera', 'Fronti di plasma che spazzano interi settori. Fondono i caccia esposti, poi si dissolvono da sole nel giro di qualche ora.', 70),
  ('deep_space',     'Oltre la Frontiera',     'frontiera', 'Piu\' ti allontani dallo StarDock, piu\' lo spazio diventa ostile e generoso insieme. Le regioni profonde pullulano di relitti e anomalie, e nascondono la tecnologia dei Precursori. Pochi ci vanno. Meno tornano.', 80),
  ('precursor_tech', 'Eco dei Precursori',     'frontiera', 'Frammenti di una tecnologia che non abbiamo costruito noi e che non sappiamo del tutto replicare. Si trova solo nel profondo, e solo da chi ha la pazienza di cercarla.', 90)
ON DUPLICATE KEY UPDATE title=VALUES(title), category=VALUES(category), body=VALUES(body), sort_order=VALUES(sort_order);

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('scan.turn_cost',              '8',    'int'),
  ('scan.probe_turn_cost',        '2',    'int'),
  ('scan.salvage_turn_cost',      '6',    'int'),
  ('scan.harvest_turn_cost',      '4',    'int'),
  ('scan.study_turn_cost',        '5',    'int'),
  ('scan.wreck_target_frontier',  '6',    'int'),
  ('scan.wreck_target_deep',      '11',   'int'),
  ('scan.cache_target_frontier',  '5',    'int'),
  ('scan.cache_target_deep',      '7',    'int'),
  ('scan.anomaly_target_frontier','3',    'int'),
  ('scan.anomaly_target_deep',    '6',    'int'),
  ('scan.hazard_target_frontier', '3',    'int'),
  ('scan.hazard_target_deep',     '12',   'int'),
  ('scan.feature_ttl_hours',      '48',   'int'),
  ('scan.salvage_base',           '45',   'int'),
  ('scan.cache_credits_base',     '600',  'int'),
  ('scan.deep_mult',              '1.8',  'float'),
  ('scan.wreck_module_pct',       '35',   'int'),
  ('scan.wreck_module_deep_pct',  '60',   'int'),
  ('scan.wreck_officer_pct',      '12',   'int'),
  ('scan.anomaly_progress_base',  '30',   'int'),
  ('scan.anomaly_science_bonus',  '2.0',  'float'),
  ('scan.hazard_radiation_drain', '0.35', 'float'),
  ('scan.hazard_gravity_turns',   '1',    'int'),
  ('scan.hazard_ion_fighter_frac','0.15', 'float'),
  ('scan.hazard_known_mitigation','0.5',  'float')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
