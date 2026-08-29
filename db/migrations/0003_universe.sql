-- 0003_universe : settori, warp, regioni, tipi nave, giocatori, navi, fog-of-war

CREATE TABLE IF NOT EXISTS regions (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(48) NOT NULL,
  kind  ENUM('federation','core','frontier','deep') NOT NULL DEFAULT 'frontier',
  color CHAR(7) NOT NULL DEFAULT '#5b6b8c',
  UNIQUE KEY uq_regions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sectors (
  id          INT UNSIGNED NOT NULL PRIMARY KEY,
  name        VARCHAR(64) NOT NULL,
  region_id   INT UNSIGNED NULL,
  is_fedspace TINYINT(1) NOT NULL DEFAULT 0,
  is_stardock TINYINT(1) NOT NULL DEFAULT 0,
  has_port    TINYINT(1) NOT NULL DEFAULT 0,
  beacon      VARCHAR(80) NULL,
  beacon_by   BIGINT UNSIGNED NULL,
  nebula      VARCHAR(48) NULL,
  x           DOUBLE NOT NULL DEFAULT 0,
  y           DOUBLE NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sectors_region (region_id),
  KEY idx_sectors_fedspace (is_fedspace),
  CONSTRAINT fk_sectors_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warps (
  from_sector INT UNSIGNED NOT NULL,
  to_sector   INT UNSIGNED NOT NULL,
  PRIMARY KEY (from_sector, to_sector),
  KEY idx_warps_to (to_sector),
  CONSTRAINT fk_warps_from FOREIGN KEY (from_sector) REFERENCES sectors(id) ON DELETE CASCADE,
  CONSTRAINT fk_warps_to   FOREIGN KEY (to_sector)   REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ship_types (
  ckey           VARCHAR(32) NOT NULL PRIMARY KEY,
  name           VARCHAR(48) NOT NULL,
  base_holds     SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  max_holds      SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  base_fighters  INT UNSIGNED NOT NULL DEFAULT 0,
  max_fighters   INT UNSIGNED NOT NULL DEFAULT 0,
  base_shields   INT UNSIGNED NOT NULL DEFAULT 0,
  max_shields    INT UNSIGNED NOT NULL DEFAULT 0,
  turns_per_warp TINYINT UNSIGNED NOT NULL DEFAULT 1,
  can_transwarp  TINYINT(1) NOT NULL DEFAULT 0,
  hold_price     INT UNSIGNED NOT NULL DEFAULT 0,
  base_cost      INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order     SMALLINT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        BIGINT UNSIGNED NOT NULL,
  handle         VARCHAR(32) NOT NULL,
  sector_id      INT UNSIGNED NOT NULL,
  ship_id        BIGINT UNSIGNED NULL,
  credits        BIGINT NOT NULL DEFAULT 0,
  turns          INT NOT NULL DEFAULT 0,
  turns_reset_on DATE NULL,
  experience     INT NOT NULL DEFAULT 0,
  alignment      INT NOT NULL DEFAULT 0,
  corp_id        BIGINT UNSIGNED NULL,
  deaths         INT NOT NULL DEFAULT 0,
  total_warps    INT NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_move_at   DATETIME NULL,
  UNIQUE KEY uq_players_user (user_id),
  UNIQUE KEY uq_players_handle (handle),
  KEY idx_players_sector (sector_id),
  CONSTRAINT fk_players_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_players_sector FOREIGN KEY (sector_id) REFERENCES sectors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ships (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id      BIGINT UNSIGNED NOT NULL,
  type_key       VARCHAR(32) NOT NULL,
  name           VARCHAR(48) NOT NULL,
  sector_id      INT UNSIGNED NOT NULL,
  holds_total    SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  hold_ore       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  hold_organics  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  hold_equipment SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  hold_colonists SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  fighters       INT UNSIGNED NOT NULL DEFAULT 0,
  shields        INT UNSIGNED NOT NULL DEFAULT 0,
  destroyed      TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ships_player (player_id),
  KEY idx_ships_sector (sector_id),
  CONSTRAINT fk_ships_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_ships_type   FOREIGN KEY (type_key)  REFERENCES ship_types(ckey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_visited_sectors (
  player_id  BIGINT UNSIGNED NOT NULL,
  sector_id  INT UNSIGNED NOT NULL,
  first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  visits     INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (player_id, sector_id),
  KEY idx_pvs_sector (sector_id),
  CONSTRAINT fk_pvs_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_pvs_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS move_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id   BIGINT UNSIGNED NOT NULL,
  from_sector INT UNSIGNED NOT NULL,
  to_sector   INT UNSIGNED NOT NULL,
  turns_spent SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  mode        ENUM('warp','autopilot','transwarp') NOT NULL DEFAULT 'warp',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_movelog_player (player_id, id),
  CONSTRAINT fk_movelog_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
