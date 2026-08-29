-- 0007_combat : equipaggiamento nave, difese porto, mine/fighter dispiegati,
--               log combattimento, statistiche giocatore, parametri hardware/combat

ALTER TABLE ships
  ADD COLUMN mines_armid  INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN mines_limpet INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN probes       INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN genesis      INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN escape_pod   TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN dev_scanner  ENUM('none','density','holo') NOT NULL DEFAULT 'none',
  ADD COLUMN dev_transwarp TINYINT(1)  NOT NULL DEFAULT 0,
  ADD COLUMN dev_cloak    TINYINT(1)   NOT NULL DEFAULT 0;

ALTER TABLE ship_types
  ADD COLUMN combat_rating DOUBLE NOT NULL DEFAULT 1.0;

UPDATE ship_types SET combat_rating = CASE ckey
  WHEN 'escape_pod'         THEN 0.10
  WHEN 'scout_marauder'     THEN 0.80
  WHEN 'merchant_cruiser'   THEN 1.00
  WHEN 'missile_frigate'    THEN 1.50
  WHEN 'constellation'      THEN 1.70
  WHEN 'merchant_freighter' THEN 0.90
  WHEN 'cargo_transport'    THEN 0.70
  WHEN 'colonial_transport' THEN 0.50
  WHEN 'corporate_flagship' THEN 2.00
  WHEN 'havoc_gunstar'      THEN 2.40
  WHEN 'imperial_starship'  THEN 2.60
  WHEN 'tholian_sentinel'   THEN 1.60
  WHEN 'interdictor'        THEN 2.20
  ELSE 1.00 END;

ALTER TABLE players
  ADD COLUMN bounty          BIGINT   NOT NULL DEFAULT 0,
  ADD COLUMN kills           INT      NOT NULL DEFAULT 0,
  ADD COLUMN port_busts      INT      NOT NULL DEFAULT 0,
  ADD COLUMN protected_until DATETIME NULL,
  ADD COLUMN last_death_at   DATETIME NULL;

ALTER TABLE ports
  ADD COLUMN fighters     INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN fighters_max INT UNSIGNED NOT NULL DEFAULT 0;

UPDATE ports SET
  fighters_max = CASE WHEN class = 0 THEN 50000 ELSE GREATEST(120, ROUND(150 * POW(1.55, tech_level - 1))) END,
  fighters     = CASE WHEN class = 0 THEN 50000 ELSE GREATEST(120, ROUND(150 * POW(1.55, tech_level - 1))) END;

CREATE TABLE IF NOT EXISTS sector_fighters (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sector_id       INT UNSIGNED NOT NULL,
  owner_player_id BIGINT UNSIGNED NOT NULL,
  corp_id         BIGINT UNSIGNED NULL,
  qty             INT UNSIGNED NOT NULL,
  mode            ENUM('offensive','defensive','toll') NOT NULL DEFAULT 'defensive',
  toll            INT UNSIGNED NOT NULL DEFAULT 0,
  deployed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sf (sector_id, owner_player_id),
  KEY idx_sf_sector (sector_id),
  CONSTRAINT fk_sf_player FOREIGN KEY (owner_player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_sf_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sector_mines (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sector_id       INT UNSIGNED NOT NULL,
  owner_player_id BIGINT UNSIGNED NOT NULL,
  type            ENUM('armid','limpet') NOT NULL,
  qty             INT UNSIGNED NOT NULL,
  deployed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sm (sector_id, owner_player_id, type),
  KEY idx_sm_sector (sector_id),
  CONSTRAINT fk_sm_player FOREIGN KEY (owner_player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ship_limpets (
  ship_id         BIGINT UNSIGNED NOT NULL,
  owner_player_id BIGINT UNSIGNED NOT NULL,
  attached_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ship_id, owner_player_id),
  KEY idx_sl_owner (owner_player_id),
  CONSTRAINT fk_sl_ship  FOREIGN KEY (ship_id)         REFERENCES ships(id)   ON DELETE CASCADE,
  CONSTRAINT fk_sl_owner FOREIGN KEY (owner_player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS combat_log (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind               ENUM('ship','port','fighters','mines','toll') NOT NULL,
  sector_id          INT UNSIGNED NOT NULL,
  attacker_player_id BIGINT UNSIGNED NULL,
  defender_player_id BIGINT UNSIGNED NULL,
  defender_port_id   INT UNSIGNED NULL,
  rounds             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  att_fighters_lost  INT UNSIGNED NOT NULL DEFAULT 0,
  def_fighters_lost  INT UNSIGNED NOT NULL DEFAULT 0,
  outcome            VARCHAR(24) NOT NULL,
  loot_credits       BIGINT NOT NULL DEFAULT 0,
  detail             JSON NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cl_sector (sector_id, id),
  KEY idx_cl_att (attacker_player_id, id),
  KEY idx_cl_def (defender_player_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('hardware.fighter_price',        '12',    'float'),
  ('hardware.shield_price',         '8',     'float'),
  ('hardware.hold_price_mult',      '1.0',   'float'),
  ('hardware.probe_price',          '200',   'int'),
  ('hardware.armid_price',          '95',    'int'),
  ('hardware.limpet_price',         '55',    'int'),
  ('hardware.escape_pod_price',     '3000',  'int'),
  ('hardware.scanner_density_price','3000',  'int'),
  ('hardware.scanner_holo_price',   '12000', 'int'),
  ('hardware.transwarp_price',      '28000', 'int'),
  ('hardware.cloak_price',          '35000', 'int'),
  ('hardware.mine_capacity',        '50',    'int'),
  ('hardware.probe_capacity',       '20',    'int'),
  ('combat.attack_turn_cost',       '2',     'int'),
  ('combat.round_kill_ratio',       '0.65',  'float'),
  ('combat.variance',               '0.35',  'float'),
  ('combat.max_rounds',             '12',    'int'),
  ('combat.loot_pct',               '0.5',   'float'),
  ('combat.armid_damage',           '1.0',   'float'),
  ('combat.exp_per_kill',           '50',    'int'),
  ('combat.exp_per_fighter',        '0.02',  'float'),
  ('combat.kill_good_alignment',    '-25',   'int'),
  ('combat.kill_evil_alignment',    '15',    'int'),
  ('combat.port_bust_alignment',    '-120',  'int'),
  ('combat.bounty_pct',             '0.1',   'float'),
  ('newbie.protect_hours',          '48',    'int'),
  ('ranks.evil_threshold',          '-100',  'int'),
  ('ranks.good_threshold',          '100',   'int')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
