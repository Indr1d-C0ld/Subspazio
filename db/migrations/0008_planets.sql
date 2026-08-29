-- 0008_planets : pianeti (Genesi), coloni e produzione, Citadel, Quasar,
--                corporazioni (versione base per il possesso condiviso)

CREATE TABLE IF NOT EXISTS planet_types (
  ckey        CHAR(1) NOT NULL PRIMARY KEY,
  name        VARCHAR(32) NOT NULL,
  descr       VARCHAR(120) NOT NULL,
  max_col     INT UNSIGNED NOT NULL,
  breed_rate  DOUBLE NOT NULL,          -- frazione/ora di crescita coloni
  prod_ore    DOUBLE NOT NULL,          -- unita' per colono per ora
  prod_org    DOUBLE NOT NULL,
  prod_equ    DOUBLE NOT NULL,
  citadel_ok  TINYINT(1) NOT NULL DEFAULT 1,
  spawn_weight SMALLINT NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO planet_types (ckey,name,descr,max_col,breed_rate,prod_ore,prod_org,prod_equ,citadel_ok,spawn_weight) VALUES
  ('M','Terrestre','Clima temperato, biosfera ricca: produzione bilanciata.',100000,0.040,0.9,0.9,0.5,1,22),
  ('K','Desertico','Distese aride e ricche di minerali.',80000,0.025,1.6,0.3,0.6,1,18),
  ('O','Oceanico','Mari sconfinati: organico in abbondanza.',90000,0.035,0.3,1.7,0.4,1,18),
  ('L','Montuoso','Catene rocciose: minerali e materiali da lavorazione.',70000,0.020,1.4,0.2,1.2,1,15),
  ('C','Glaciale','Ghiacci perenni: colonizzazione difficile.',40000,0.015,0.5,0.5,0.4,1,12),
  ('H','Vulcanico','Superficie ostile ma ricchissima di metalli.',30000,0.012,1.8,0.1,0.9,1,10),
  ('U','Gassoso','Gigante gassoso: pochissimo utile.',8000,0.005,0.05,0.05,0.05,0,5)
ON DUPLICATE KEY UPDATE name=VALUES(name), descr=VALUES(descr), max_col=VALUES(max_col),
  breed_rate=VALUES(breed_rate), prod_ore=VALUES(prod_ore), prod_org=VALUES(prod_org),
  prod_equ=VALUES(prod_equ), citadel_ok=VALUES(citadel_ok), spawn_weight=VALUES(spawn_weight);

CREATE TABLE IF NOT EXISTS planets (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sector_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(48) NOT NULL,
  type_key        CHAR(1) NOT NULL,
  owner_player_id BIGINT UNSIGNED NULL,
  corp_id         BIGINT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  col_ore         BIGINT NOT NULL DEFAULT 0,
  col_org         BIGINT NOT NULL DEFAULT 0,
  col_equ         BIGINT NOT NULL DEFAULT 0,
  col_idle        BIGINT NOT NULL DEFAULT 0,
  stock_ore       BIGINT NOT NULL DEFAULT 0,
  stock_org       BIGINT NOT NULL DEFAULT 0,
  stock_equ       BIGINT NOT NULL DEFAULT 0,
  credits         BIGINT NOT NULL DEFAULT 0,
  citadel_level   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  citadel_upgrade_to TINYINT UNSIGNED NULL,
  citadel_ready_at   DATETIME NULL,
  quasar_level    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fighters        BIGINT NOT NULL DEFAULT 0,
  shields         BIGINT NOT NULL DEFAULT 0,
  last_prod_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  destroyed       TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_planets_sector (sector_id),
  KEY idx_planets_owner (owner_player_id),
  KEY idx_planets_corp (corp_id),
  KEY idx_planets_due (citadel_ready_at),
  CONSTRAINT fk_planets_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE,
  CONSTRAINT fk_planets_type FOREIGN KEY (type_key) REFERENCES planet_types(ckey),
  CONSTRAINT fk_planets_owner FOREIGN KEY (owner_player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corporations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(48) NOT NULL,
  tag           VARCHAR(6) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  ceo_player_id BIGINT UNSIGNED NOT NULL,
  treasury      BIGINT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_corp_name (name),
  UNIQUE KEY uq_corp_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corp_members (
  player_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  corp_id   BIGINT UNSIGNED NOT NULL,
  role      ENUM('ceo','member') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cm_corp (corp_id),
  CONSTRAINT fk_cm_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_corp   FOREIGN KEY (corp_id)   REFERENCES corporations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE combat_log MODIFY kind ENUM('ship','port','fighters','mines','toll','planet','quasar') NOT NULL;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('hardware.genesis_price',       '31000', 'int'),
  ('hardware.genesis_capacity',    '10',    'int'),
  ('planet.max_per_sector',        '5',     'int'),
  ('planet.stock_cap_mult',        '20',    'int'),
  ('planet.colonist_pickup_per_day','5000', 'int'),
  ('planet.citadel_cost_mult',     '1.0',   'float'),
  ('planet.garrison_per_citadel',  '3000',  'int'),
  ('planet.militia_col_frac',      '0.01',  'float'),
  ('planet.quasar_damage',         '2200',  'int'),
  ('planet.quasar_cost_credits',   '250000','int'),
  ('planet.quasar_cost_equ',       '4000',  'int'),
  ('planet.bombard_frac',          '0.2',   'float'),
  ('planet.bust_alignment',        '-60',   'int'),
  ('planet.bombard_alignment',     '-150',  'int'),
  ('corp.create_cost',             '50000', 'int'),
  ('corp.max_members',             '8',     'int')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
