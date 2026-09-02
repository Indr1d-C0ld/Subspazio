-- 0016_crew : Fase 8 — equipaggio (ufficiali con ruoli, abilità, XP) e
--   missioni away a skill-check con esiti ramificati.

-- --- posti equipaggio sullo scafo ---------------------------------------
ALTER TABLE ship_types ADD COLUMN crew_slots TINYINT UNSIGNED NOT NULL DEFAULT 2;
UPDATE ship_types SET crew_slots = CASE ckey
  WHEN 'escape_pod'          THEN 0
  WHEN 'scout_marauder'      THEN 2
  WHEN 'merchant_cruiser'    THEN 3
  WHEN 'missile_frigate'     THEN 3
  WHEN 'constellation'       THEN 4
  WHEN 'merchant_freighter'  THEN 4
  WHEN 'cargo_transport'     THEN 3
  WHEN 'colonial_transport'  THEN 4
  WHEN 'corporate_flagship'  THEN 6
  WHEN 'havoc_gunstar'       THEN 3
  WHEN 'imperial_starship'   THEN 8
  WHEN 'tholian_sentinel'    THEN 4
  WHEN 'interdictor'         THEN 5
  ELSE 2 END;

-- --- archetipi (per la generazione procedurale) -----------------------
CREATE TABLE IF NOT EXISTS officer_archetypes (
  ckey    VARCHAR(40) NOT NULL PRIMARY KEY,
  role    ENUM('tactical','navigator','engineer','scientist','medic','diplomat') NOT NULL,
  title   VARCHAR(48) NOT NULL,
  weights JSON NOT NULL,     -- pesi 0..1 per combat/piloting/engineering/science/medicine/diplomacy
  blurb   VARCHAR(160) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO officer_archetypes (ckey, role, title, weights, blurb) VALUES
  ('tac_veterano', 'tactical',  'Veterano di guerra',   '{"combat":1.0,"piloting":0.5,"engineering":0.3,"science":0.2,"medicine":0.2,"diplomacy":0.2}', 'Ha visto troppe battaglie per contarle.'),
  ('tac_cecchino', 'tactical',  'Artigliere scelto',    '{"combat":1.0,"piloting":0.35,"engineering":0.2,"science":0.35,"medicine":0.15,"diplomacy":0.15}', 'Non spreca un colpo.'),
  ('nav_pilota',   'navigator', 'Pilota da ricognizione','{"piloting":1.0,"combat":0.4,"science":0.4,"engineering":0.3,"medicine":0.2,"diplomacy":0.25}', 'Conosce rotte che non esistono sulle carte.'),
  ('nav_corsaro',  'navigator', 'Vecchio corsaro',      '{"piloting":1.0,"combat":0.5,"diplomacy":0.4,"engineering":0.25,"science":0.2,"medicine":0.15}', 'Ha fatto il contrabbandiere per trent''anni.'),
  ('eng_capo',     'engineer',  'Capo macchinista',     '{"engineering":1.0,"science":0.5,"piloting":0.3,"combat":0.3,"medicine":0.2,"diplomacy":0.2}', 'Tiene insieme la nave con nastro e bestemmie.'),
  ('eng_prodigio', 'engineer',  'Prodigio dei reattori','{"engineering":1.0,"science":0.6,"combat":0.2,"piloting":0.25,"medicine":0.25,"diplomacy":0.2}', 'Parla con i reattori. A volte rispondono.'),
  ('sci_xeno',     'scientist', 'Xeno-analista',        '{"science":1.0,"engineering":0.45,"diplomacy":0.4,"medicine":0.35,"piloting":0.25,"combat":0.15}', 'Colleziona anomalie come altri collezionano francobolli.'),
  ('sci_sensori',  'scientist', 'Ufficiale ai sensori', '{"science":1.0,"piloting":0.4,"engineering":0.4,"combat":0.25,"medicine":0.25,"diplomacy":0.3}', 'Vede cose che gli altri scanner non vedono.'),
  ('med_campo',    'medic',     'Medico di bordo',      '{"medicine":1.0,"science":0.55,"diplomacy":0.4,"engineering":0.25,"piloting":0.2,"combat":0.2}', 'Ha rimesso in piedi interi equipaggi.'),
  ('med_trauma',   'medic',     'Chirurgo di trauma',   '{"medicine":1.0,"science":0.5,"combat":0.3,"diplomacy":0.35,"piloting":0.2,"engineering":0.25}', 'Lavora meglio sotto il fuoco.'),
  ('dip_console',  'diplomat',  'Ex console',           '{"diplomacy":1.0,"science":0.45,"medicine":0.4,"piloting":0.3,"engineering":0.2,"combat":0.15}', 'Ha firmato trattati che reggono ancora.'),
  ('dip_mercante', 'diplomat',  'Mercante navigato',    '{"diplomacy":1.0,"piloting":0.4,"combat":0.3,"science":0.3,"engineering":0.25,"medicine":0.25}', 'Ogni conflitto è solo un prezzo da trattare.')
ON DUPLICATE KEY UPDATE role=VALUES(role), title=VALUES(title), weights=VALUES(weights), blurb=VALUES(blurb);

-- --- ufficiali del giocatore -----------------------------------------
CREATE TABLE IF NOT EXISTS officers (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id    BIGINT UNSIGNED NOT NULL,
  name         VARCHAR(48) NOT NULL,
  role         ENUM('tactical','navigator','engineer','scientist','medic','diplomat') NOT NULL,
  archetype    VARCHAR(40) NULL,
  level        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  xp           INT UNSIGNED NOT NULL DEFAULT 0,
  status       ENUM('active','injured','dead') NOT NULL DEFAULT 'active',
  skills       JSON NOT NULL,
  ability_tier TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ready_at     DATETIME NULL,
  assigned     TINYINT(1) NOT NULL DEFAULT 1,
  loyalty_done TINYINT(1) NOT NULL DEFAULT 0,
  origin       ENUM('hire','wreck','mission','gift') NOT NULL DEFAULT 'hire',
  hired_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_off_player (player_id, assigned),
  CONSTRAINT fk_off_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- pool di reclutamento (per giocatore) ---------------------------
CREATE TABLE IF NOT EXISTS recruit_candidates (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  name       VARCHAR(48) NOT NULL,
  role       ENUM('tactical','navigator','engineer','scientist','medic','diplomat') NOT NULL,
  archetype  VARCHAR(40) NULL,
  level      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  skills     JSON NOT NULL,
  cost       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rc_player (player_id),
  CONSTRAINT fk_rc_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- missioni away --------------------------------------------------
CREATE TABLE IF NOT EXISTS away_missions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  kind       VARCHAR(24) NOT NULL,
  title      VARCHAR(90) NOT NULL,
  descr      VARCHAR(255) NULL,
  difficulty SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  skills     JSON NOT NULL,
  turn_cost  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  sector_id  INT UNSIGNED NULL,
  rewards    JSON NOT NULL,
  status     ENUM('open','done') NOT NULL DEFAULT 'open',
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_am_player (player_id, status),
  CONSTRAINT fk_am_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS away_mission_log (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id    BIGINT UNSIGNED NOT NULL,
  mission_kind VARCHAR(24) NOT NULL,
  title        VARCHAR(90) NOT NULL,
  officers     JSON NOT NULL,
  outcome      ENUM('triumph','success','partial','failure','disaster') NOT NULL,
  margin       INT NOT NULL DEFAULT 0,
  reward_text  VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aml_player (player_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- effetti transitori delle abilità -----------------------------
CREATE TABLE IF NOT EXISTS crew_pending (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  effect     VARCHAR(32) NOT NULL,
  magnitude  DOUBLE NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cp_player (player_id, effect)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- configurazione ----------------------------------------------
INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('crew.recruit_pool_size',    '4',    'int'),
  ('crew.recruit_refresh_hours','6',    'int'),
  ('crew.hire_cost_base',       '1500', 'int'),
  ('crew.hire_cost_per_level',  '1400', 'int'),
  ('crew.xp_per_kill',          '12',   'int'),
  ('crew.xp_curve_base',        '100',  'int'),
  ('crew.max_level',            '8',    'int'),
  ('crew.skill_per_level',      '1.8',  'float'),
  ('crew.passive_diminish',     '0.55', 'float'),
  ('crew.ability_cooldown_min', '90',   'int'),
  ('crew.ability_turn_cost',    '15',   'int'),
  ('crew.mission_pool_size',    '4',    'int'),
  ('crew.mission_refresh_hours','4',    'int'),
  ('crew.mission_expire_hours', '14',   'int'),
  ('crew.mission_cooldown_min', '120',  'int'),
  ('crew.mission_turn_min',     '20',   'int'),
  ('crew.mission_turn_max',     '55',   'int'),
  ('crew.injury_heal_cost',     '2500', 'int'),
  ('crew.injury_heal_hours',    '6',    'int'),
  ('crew.permadeath',           '0',    'int'),
  ('crew.loyalty_level',        '4',    'int')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
