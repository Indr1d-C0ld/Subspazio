-- 0015_loot_modules : Fase 7 — loot con fasce di rarità e moduli nave.
--   ship_types guadagna slot per categoria; nuovi cataloghi/tabelle per
--   moduli posseduti (player_items) e installati (ship_modules); i giocatori
--   accumulano "Leghe di recupero" (players.salvage) dallo smontaggio/kill.

-- --- slot sullo scafo -------------------------------------------------------
ALTER TABLE ship_types
  ADD COLUMN slot_weapon   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN slot_defense  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN slot_drive    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN slot_computer TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN slot_utility  TINYINT UNSIGNED NOT NULL DEFAULT 1;

UPDATE ship_types SET slot_weapon=0, slot_defense=0, slot_drive=0, slot_computer=0, slot_utility=0
  WHERE ckey = 'escape_pod';
UPDATE ship_types SET slot_weapon=2, slot_defense=1, slot_drive=1, slot_computer=1, slot_utility=1 WHERE ckey='scout_marauder';
UPDATE ship_types SET slot_weapon=1, slot_defense=2, slot_drive=1, slot_computer=1, slot_utility=2 WHERE ckey='merchant_cruiser';
UPDATE ship_types SET slot_weapon=3, slot_defense=1, slot_drive=1, slot_computer=2, slot_utility=1 WHERE ckey='missile_frigate';
UPDATE ship_types SET slot_weapon=3, slot_defense=3, slot_drive=2, slot_computer=2, slot_utility=2 WHERE ckey='constellation';
UPDATE ship_types SET slot_weapon=1, slot_defense=2, slot_drive=1, slot_computer=1, slot_utility=3 WHERE ckey='merchant_freighter';
UPDATE ship_types SET slot_weapon=1, slot_defense=1, slot_drive=1, slot_computer=1, slot_utility=4 WHERE ckey='cargo_transport';
UPDATE ship_types SET slot_weapon=1, slot_defense=2, slot_drive=1, slot_computer=1, slot_utility=4 WHERE ckey='colonial_transport';
UPDATE ship_types SET slot_weapon=3, slot_defense=3, slot_drive=2, slot_computer=3, slot_utility=3 WHERE ckey='corporate_flagship';
UPDATE ship_types SET slot_weapon=4, slot_defense=2, slot_drive=1, slot_computer=2, slot_utility=1 WHERE ckey='havoc_gunstar';
UPDATE ship_types SET slot_weapon=4, slot_defense=4, slot_drive=3, slot_computer=3, slot_utility=3 WHERE ckey='imperial_starship';
UPDATE ship_types SET slot_weapon=2, slot_defense=4, slot_drive=2, slot_computer=2, slot_utility=1 WHERE ckey='tholian_sentinel';
UPDATE ship_types SET slot_weapon=4, slot_defense=3, slot_drive=3, slot_computer=3, slot_utility=2 WHERE ckey='interdictor';

-- --- materiale del giocatore ---------------------------------------------
ALTER TABLE players ADD COLUMN salvage BIGINT NOT NULL DEFAULT 0;

-- --- catalogo moduli --------------------------------------------------------
CREATE TABLE IF NOT EXISTS item_types (
  ckey         VARCHAR(40)  NOT NULL PRIMARY KEY,
  name         VARCHAR(64)  NOT NULL,
  category     ENUM('weapon','defense','drive','computer','utility') NOT NULL,
  rarity       ENUM('civ','mil','exp','xeno','precursor') NOT NULL DEFAULT 'civ',
  effects      JSON         NOT NULL,
  base_salvage INT UNSIGNED NOT NULL DEFAULT 5,
  descr        VARCHAR(255) NULL,
  sort_order   SMALLINT     NOT NULL DEFAULT 100,
  KEY idx_item_cat_rar (category, rarity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_items (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id   BIGINT UNSIGNED NOT NULL,
  item_key    VARCHAR(40) NOT NULL,
  rolled      JSON NULL,
  source      ENUM('npc','pvp','port','planet','wreck','mission','shop') NOT NULL DEFAULT 'npc',
  acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pi_player (player_id),
  CONSTRAINT fk_pi_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_item   FOREIGN KEY (item_key)  REFERENCES item_types(ckey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ship_modules (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ship_id      BIGINT UNSIGNED NOT NULL,
  slot         ENUM('weapon','defense','drive','computer','utility') NOT NULL,
  item_key     VARCHAR(40) NOT NULL,
  rolled       JSON NULL,
  installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_ship (ship_id),
  CONSTRAINT fk_sm_ship FOREIGN KEY (ship_id)  REFERENCES ships(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_item FOREIGN KEY (item_key) REFERENCES item_types(ckey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- catalogo v1 (23 moduli) ---------------------------------------------
INSERT INTO item_types (ckey, name, category, rarity, effects, base_salvage, descr, sort_order) VALUES
  ('w_autocannoni',  'Autocannoni a rotazione', 'weapon', 'civ',       '{"combat_pct":6}',   8,   'Volume di fuoco economico e affidabile.', 10),
  ('w_railgun',      'Railgun a massa',         'weapon', 'mil',       '{"combat_pct":12}',  22,  'Proiettili cinetici ad alta penetrazione.', 11),
  ('w_plasma',       'Lancia al plasma',        'weapon', 'exp',       '{"combat_pct":20}',  60,  'Bobine sperimentali surriscaldate.', 12),
  ('w_disgregatore', 'Disgregatore xeno',       'weapon', 'xeno',      '{"combat_pct":30}',  160, 'Tecnologia d''arma di origine ignota.', 13),
  ('w_precursore',   'Proiettore Precursore',   'weapon', 'precursor', '{"combat_pct":42}',  420, 'Un''arma che non dovrebbe esistere.', 14),
  ('d_piastre',      'Piastre balistiche',      'defense','civ',       '{"max_shields_pct":12}', 8,   'Corazza supplementare bullonata allo scafo.', 20),
  ('d_ondulati',     'Scudi ondulati',          'defense','mil',       '{"shield_regen":60}', 22,  'Rigenera gli scudi a ogni salto di warp.', 21),
  ('d_deflettore',   'Deflettore adattivo',     'defense','exp',       '{"combat_pct":8,"max_shields_pct":10}', 60, 'Rimodula le frequenze sotto tiro.', 22),
  ('d_barriera',     'Barriera xeno',           'defense','xeno',      '{"max_shields_pct":35,"shield_regen":40}', 160, 'Campo di contenimento denso.', 23),
  ('d_egida',        'Egida Precursore',        'defense','precursor', '{"shield_regen":150,"max_shields_pct":25}', 420, 'Uno scudo che si ripara da solo.', 24),
  ('v_bobine',       'Bobine warp potenziate',  'drive',  'mil',       '{"warp_turn_reduction":1}', 22, 'Meno turni per salto.', 30),
  ('v_transwarp',    'Nucleo transwarp',        'drive',  'exp',       '{"warp_turn_reduction":1,"combat_pct":4}', 60, 'Salti più rapidi, manovra più reattiva.', 31),
  ('v_motore_xeno',  'Motore a curvatura xeno', 'drive',  'xeno',      '{"warp_turn_reduction":1,"combat_pct":10}', 160, 'Propulsione aliena, resa imprevedibile.', 32),
  ('c_scanner_denso','Scanner di densità',      'computer','civ',      '{"scanner":"density"}', 8, 'Rivela mine e forze schierate nel settore.', 40),
  ('c_oloscanner',   'Olo-scanner',             'computer','mil',      '{"scanner":"holo","scan_range":1}', 22, 'Ricostruzione olografica del settore.', 41),
  ('c_gew',          'Guerra elettronica',      'computer','exp',      '{"combat_pct":10}', 60, 'Disturba la mira nemica.', 42),
  ('c_preveggenza',  'Preveggenza xeno',        'computer','xeno',     '{"drop_luck_pct":25}', 160, 'Prevede dove cadranno i rottami migliori.', 43),
  ('c_mente',        'Mente Precursore',        'computer','precursor','{"scan_range":3,"drop_luck_pct":18,"combat_pct":6}', 420, 'Un''intelligenza che non è la tua.', 44),
  ('u_stiva',        'Stiva ausiliaria',        'utility','civ',       '{"cargo_bonus":4}', 8, 'Quattro stive in più.', 50),
  ('u_recuperatore', 'Braccio recuperatore',    'utility','mil',       '{"salvage_bonus_pct":35}', 22, 'Più leghe da ogni relitto.', 51),
  ('u_mantello',     'Generatore di mantello',  'utility','exp',       '{"cloak":1}', 60, 'Ti nasconde agli scanner.', 52),
  ('u_drone',        'Drone d''officina',       'utility','xeno',      '{"salvage_bonus_pct":60,"drop_luck_pct":8}', 160, 'Smonta i relitti mentre voli.', 53),
  ('u_nanosciame',   'Nanosciame Precursore',   'utility','precursor', '{"cargo_bonus":6,"shield_regen":40}', 420, 'Nubi di macchine che riparano e stivano.', 54)
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), rarity=VALUES(rarity),
  effects=VALUES(effects), base_salvage=VALUES(base_salvage), descr=VALUES(descr), sort_order=VALUES(sort_order);

-- --- configurazione loot ------------------------------------------------
INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('loot.drop_chance_npc',        '0.35',  'float'),
  ('loot.drop_chance_pvp',        '0.55',  'float'),
  ('loot.drop_chance_port',       '0.15',  'float'),
  ('loot.drop_chance_planet',     '0.10',  'float'),
  ('loot.double_drop_pct',        '0.08',  'float'),
  ('loot.effect_roll_variance',   '0.15',  'float'),
  ('loot.rarity_weights',         'civ:100,mil:45,exp:16,xeno:5,precursor:1', 'string'),
  ('loot.region_bonus_frontier',  '1.15',  'float'),
  ('loot.region_bonus_deep',      '1.5',   'float'),
  ('loot.event_bounty_luck',      '1.4',   'float'),
  ('loot.pvp_tier_floor',         'mil',   'string'),
  ('loot.pvp_drops_per_day',      '1',     'int'),
  ('loot.min_victim_rating_pvp',  '50',    'int'),
  ('loot.salvage_per_rating',     '6',     'float'),
  ('loot.death_module_refund_pct','0.5',   'float'),
  ('loot.keep_modules_on_refit',  '1',     'int'),
  ('loot.upgrade_cost_credits',   'civ:800,mil:2500,exp:8000,xeno:25000', 'string'),
  ('loot.upgrade_cost_salvage',   'civ:20,mil:60,exp:180,xeno:520', 'string')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
