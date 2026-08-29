-- 0005_economy : porti, mercato regionale dinamico, log scambi, banca IGB

CREATE TABLE IF NOT EXISTS ports (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sector_id    INT UNSIGNED NOT NULL,
  name         VARCHAR(64) NOT NULL,
  class        TINYINT UNSIGNED NOT NULL,          -- 0 = StarDock/speciale, 1..8 standard
  tech_level   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ore_mode     ENUM('buy','sell') NOT NULL,
  org_mode     ENUM('buy','sell') NOT NULL,
  equ_mode     ENUM('buy','sell') NOT NULL,
  ore_stock    BIGINT NOT NULL DEFAULT 0,
  org_stock    BIGINT NOT NULL DEFAULT 0,
  equ_stock    BIGINT NOT NULL DEFAULT 0,
  ore_capacity BIGINT NOT NULL DEFAULT 1,
  org_capacity BIGINT NOT NULL DEFAULT 1,
  equ_capacity BIGINT NOT NULL DEFAULT 1,
  credits      BIGINT NOT NULL DEFAULT 0,
  credits_max  BIGINT NOT NULL DEFAULT 0,
  last_update  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  destroyed    TINYINT(1) NOT NULL DEFAULT 0,
  built_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ports_sector (sector_id),
  KEY idx_ports_class (class),
  CONSTRAINT fk_ports_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commodity_market (
  region_id   INT UNSIGNED NOT NULL,
  commodity   ENUM('ore','organics','equipment') NOT NULL,
  base_value  DOUBLE NOT NULL,
  anchor      DOUBLE NOT NULL,
  volume_buy  BIGINT NOT NULL DEFAULT 0,
  volume_sell BIGINT NOT NULL DEFAULT 0,
  last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (region_id, commodity),
  CONSTRAINT fk_market_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trade_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id     BIGINT UNSIGNED NOT NULL,
  port_id       INT UNSIGNED NOT NULL,
  sector_id     INT UNSIGNED NOT NULL,
  commodity     ENUM('ore','organics','equipment') NOT NULL,
  action        ENUM('buy','sell') NOT NULL,        -- dal punto di vista del giocatore
  qty           INT UNSIGNED NOT NULL,
  unit_price    DOUBLE NOT NULL,
  total         BIGINT NOT NULL,
  fair_total    BIGINT NOT NULL,
  haggle_rounds TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tradelog_player (player_id, id),
  KEY idx_tradelog_port (port_id, id),
  CONSTRAINT fk_tradelog_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_accounts (
  player_id        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  balance          BIGINT NOT NULL DEFAULT 0,
  last_interest_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bank_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('economy.port_density',        '0.32',      'float'),
  ('economy.anchor.ore',          '18',        'float'),
  ('economy.anchor.organics',     '28',        'float'),
  ('economy.anchor.equipment',    '38',        'float'),
  ('economy.sell_markup',         '1.12',      'float'),
  ('economy.buy_discount',        '0.90',      'float'),
  ('economy.price_elasticity',    '0.9',       'float'),
  ('economy.slippage',            '0.35',      'float'),
  ('economy.regen_hours_full',    '72',        'float'),
  ('economy.drift.rate',          '0.06',      'float'),
  ('economy.drift.impact',        '0.9',       'float'),
  ('economy.drift.band',          '0.45',      'float'),
  ('economy.drift.interval_min',  '30',        'int'),
  ('economy.drift.last_run',      '',          'string'),
  ('economy.haggle.max_rounds',   '4',         'int'),
  ('economy.haggle.accept_band',  '0.03',      'float'),
  ('economy.haggle.walk_band',    '0.16',      'float'),
  ('economy.haggle.open_margin',  '0.15',      'float'),
  ('economy.haggle.concession',   '0.4',       'float'),
  ('economy.haggle.cooldown_s',   '20',        'int'),
  ('economy.generated_at',        '',          'string'),
  ('bank.enabled',                '1',         'bool')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
