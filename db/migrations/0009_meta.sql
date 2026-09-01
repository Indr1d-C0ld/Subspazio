-- 0009_meta : classifiche, radio subspaziale, NPC (Ferrengi/pirati/mercanti), eventi globali

CREATE TABLE IF NOT EXISTS messages (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  channel        ENUM('radio','fedcomm','corp','private','hail','system') NOT NULL,
  from_player_id BIGINT UNSIGNED NULL,
  from_name      VARCHAR(48) NULL,
  to_player_id   BIGINT UNSIGNED NULL,
  to_corp_id     BIGINT UNSIGNED NULL,
  sector_id      INT UNSIGNED NULL,
  body           VARCHAR(500) NOT NULL,
  read_at        DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_msg_chan (channel, id),
  KEY idx_msg_to (to_player_id, id),
  KEY idx_msg_corp (to_corp_id, id),
  KEY idx_msg_sector (sector_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS msg_state (
  player_id    BIGINT UNSIGNED NOT NULL,
  channel      VARCHAR(16) NOT NULL,
  last_read_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (player_id, channel),
  CONSTRAINT fk_msgstate_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS npcs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind          ENUM('ferrengi','pirate','trader') NOT NULL,
  name          VARCHAR(48) NOT NULL,
  ship_type     VARCHAR(32) NOT NULL,
  sector_id     INT UNSIGNED NOT NULL,
  home_sector   INT UNSIGNED NULL,
  fighters      BIGINT NOT NULL DEFAULT 0,
  shields       BIGINT NOT NULL DEFAULT 0,
  combat_rating DOUBLE NOT NULL DEFAULT 1.0,
  credits       BIGINT NOT NULL DEFAULT 0,
  cargo_ore     INT NOT NULL DEFAULT 0,
  cargo_org     INT NOT NULL DEFAULT 0,
  cargo_equ     INT NOT NULL DEFAULT 0,
  aggression    TINYINT NOT NULL DEFAULT 0,
  last_move_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_npc_sector (sector_id),
  KEY idx_npc_kind (kind),
  CONSTRAINT fk_npc_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind       VARCHAR(32) NOT NULL,
  title      VARCHAR(120) NOT NULL,
  body       VARCHAR(500) NOT NULL,
  payload    JSON NULL,
  starts_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at    DATETIME NULL,
  reverted   TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_active (ends_at, reverted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE players ADD COLUMN rating BIGINT NOT NULL DEFAULT 0;

ALTER TABLE combat_log MODIFY kind ENUM('ship','port','fighters','mines','toll','planet','quasar','npc') NOT NULL;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('radio.msg_max_per_min',      '6',                'int'),
  ('radio.body_max',             '480',              'int'),
  ('npc.ferrengi_target',        '40',               'int'),
  ('npc.pirate_target',          '25',               'int'),
  ('npc.trader_target',          '30',               'int'),
  ('npc.ferrengi_home_region',   'Abisso di Cygnus', 'string'),
  ('npc.move_interval_min',      '3',                'int'),
  ('npc.spawn_per_tick',         '4',                'int'),
  ('npc.engage_chance_pct',      '65',               'int'),
  ('npc.kill_exp_ferrengi',      '140',              'int'),
  ('npc.kill_exp_pirate',        '70',               'int'),
  ('combat.bounty_mult',         '1',                'float'),
  ('events.interval_min',        '240',              'int'),
  ('events.chance_pct',          '40',               'int'),
  ('events.last_run',            '',                 'string'),
  ('rating.interval_min',        '15',               'int'),
  ('rating.last_run',            '',                 'string')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
