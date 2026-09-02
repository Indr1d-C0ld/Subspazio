-- 0019_economy_depth : Fase 11 — profondità economica.
--   Estrazione mineraria (giacimenti + laser), risorse intermedie
--   (Cristalli, Componenti), raffineria e produzione di moduli su ricetta,
--   modalità industria dei pianeti posseduti.

ALTER TABLE players
  ADD COLUMN IF NOT EXISTS crystals   BIGINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS components BIGINT NOT NULL DEFAULT 0;

ALTER TABLE ships   ADD COLUMN IF NOT EXISTS mining_laser TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE planets ADD COLUMN IF NOT EXISTS industry TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE planets ADD COLUMN IF NOT EXISTS last_industry_at DATETIME NULL;

ALTER TABLE sector_features
  MODIFY COLUMN kind ENUM('wreck','cache','anomaly','hazard','asteroid') NOT NULL;

INSERT INTO codex_entries (ckey, title, category, body, sort_order) VALUES
  ('asteroid_generic', 'Giacimenti minerari', 'scansione', 'Campi di asteroidi ricchi di minerale e, nei filoni di metalli rari, di Cristalli. Servono un laser minerario e pazienza: si estraggono a più passaggi, finché il filone non si esaurisce. Nel profondo rendono molto di più.', 35),
  ('production_chain', 'Catena produttiva', 'generale', 'Minerale ed equipaggiamento si raffinano in Componenti. Componenti, Cristalli e Leghe di recupero, seguendo una ricetta, diventano un modulo preciso: la via deterministica per costruire l\'equipaggiamento che ti serve, invece di sperare in un drop.', 100),
  ('planet_industry',  'Industria planetaria', 'generale', 'Un pianeta tuo può passare in modalità industria: converte le sue scorte di minerale in Componenti, che si accumulano per te a ogni tick. Meno crescita di coloni, più produzione.', 110)
ON DUPLICATE KEY UPDATE title=VALUES(title), category=VALUES(category), body=VALUES(body), sort_order=VALUES(sort_order);

CREATE TABLE IF NOT EXISTS recipes (
  ckey            VARCHAR(40) NOT NULL PRIMARY KEY,
  output_item     VARCHAR(40) NOT NULL,
  label           VARCHAR(80) NOT NULL,
  cost_credits    INT UNSIGNED NOT NULL DEFAULT 0,
  cost_components INT UNSIGNED NOT NULL DEFAULT 0,
  cost_crystals   INT UNSIGNED NOT NULL DEFAULT 0,
  cost_salvage    INT UNSIGNED NOT NULL DEFAULT 0,
  cargo_ore       INT UNSIGNED NOT NULL DEFAULT 0,
  cargo_equ       INT UNSIGNED NOT NULL DEFAULT 0,
  cargo_org       INT UNSIGNED NOT NULL DEFAULT 0,
  min_faction     VARCHAR(16) NULL,
  min_tier        ENUM('friendly','allied') NULL,
  sort            SMALLINT NOT NULL DEFAULT 100,
  CONSTRAINT fk_recipe_item FOREIGN KEY (output_item) REFERENCES item_types(ckey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recipes (ckey, output_item, label, cost_credits, cost_components, cost_crystals, cost_salvage, cargo_ore, cargo_equ, min_faction, min_tier, sort) VALUES
  ('r_stiva',        'u_stiva',        'Stiva ausiliaria',       1200,  3,  5,   15,  0,  0,  NULL,       NULL,       10),
  ('r_ondulati',     'd_ondulati',     'Scudi ondulati',         3000,  6,  15,  40,  0,  20, NULL,       NULL,       20),
  ('r_railgun',      'w_railgun',      'Railgun a massa',        3200,  6,  20,  40,  30, 0,  NULL,       NULL,       21),
  ('r_bobine',       'v_bobine',       'Bobine warp potenziate', 5000,  8,  25,  60,  0,  0,  NULL,       NULL,       22),
  ('r_oloscanner',   'c_oloscanner',   'Olo-scanner',            4500,  8,  20,  50,  0,  0,  NULL,       NULL,       23),
  ('r_recuperatore', 'u_recuperatore', 'Braccio recuperatore',   2600,  5,  12,  30,  0,  0,  NULL,       NULL,       24),
  ('r_plasma',       'w_plasma',       'Lancia al plasma',       12000, 14, 60,  180, 60, 0,  'hegemony', 'friendly', 40),
  ('r_deflettore',   'd_deflettore',   'Deflettore adattivo',    11000, 14, 55,  160, 0,  40, NULL,       NULL,       41),
  ('r_gew',          'c_gew',          'Guerra elettronica',     12500, 15, 65,  170, 0,  0,  NULL,       NULL,       42),
  ('r_barriera',     'd_barriera',     'Barriera xeno',          30000, 26, 150, 420, 0,  0,  'frontier', 'allied',   60)
ON DUPLICATE KEY UPDATE output_item=VALUES(output_item), label=VALUES(label),
  cost_credits=VALUES(cost_credits), cost_components=VALUES(cost_components),
  cost_crystals=VALUES(cost_crystals), cost_salvage=VALUES(cost_salvage),
  cargo_ore=VALUES(cargo_ore), cargo_equ=VALUES(cargo_equ), cargo_org=VALUES(cargo_org),
  min_faction=VALUES(min_faction), min_tier=VALUES(min_tier), sort=VALUES(sort);

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('mine.turn_cost',            '5',   'int'),
  ('mine.ore_per_pass_base',    '8',   'int'),
  ('mine.crystal_chance_pct',   '45',  'int'),
  ('mine.crystal_per_hit_min',  '1',   'int'),
  ('mine.crystal_per_hit_max',  '4',   'int'),
  ('mine.deep_mult',            '1.7', 'float'),
  ('mine.asteroid_target_frontier','8','int'),
  ('mine.asteroid_target_deep', '12',  'int'),
  ('hardware.mining_laser_price','8000','int'),
  ('craft.refine_ore_per_component', '4', 'int'),
  ('craft.refine_equ_per_component', '2', 'int'),
  ('craft.refine_turn_cost',    '3',   'int'),
  ('craft.refine_batch_max',    '200', 'int'),
  ('craft.turn_cost',           '6',   'int'),
  ('craft.planet_component_per_day', '48', 'int'),
  ('craft.planet_ore_per_component', '3',  'int'),
  ('craft.industry_last_run',   '',    'string')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
