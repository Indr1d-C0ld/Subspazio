-- 0059_reperti_progetti : reperti da collezionare o rivendere, progetti d'Officina
--
-- Reperti: quindici oggetti rari in tre collezioni (Archivio della Prima
-- Federazione, Cimeli del Consorzio Ferrengi, Reliquie dei Precursori). Si
-- trovano abbattendo nemici e spogliando relitti, si rivendono allo StarDock o
-- si tengono per completare una collezione, che paga una volta sola crediti,
-- esperienza e un modulo.
--
-- Progetti: dieci ricette d'Officina per moduli Xeno e Precursore che si
-- sbloccano solo trovandone il progetto (dai comandanti d'elite, e di rado
-- dagli altri nemici dalla Frontiera in fuori).

CREATE TABLE player_reperti (
  player_id BIGINT UNSIGNED NOT NULL,
  ckey      VARCHAR(32)     NOT NULL,
  qty       INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (player_id, ckey),
  CONSTRAINT fk_reperti_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_collezioni (
  player_id    BIGINT UNSIGNED NOT NULL,
  collezione   VARCHAR(32)     NOT NULL,
  completata_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, collezione),
  CONSTRAINT fk_collezioni_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_progetti (
  player_id  BIGINT UNSIGNED NOT NULL,
  recipe_key VARCHAR(32)     NOT NULL,
  acquired_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, recipe_key),
  CONSTRAINT fk_progetti_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE recipes ADD COLUMN progetto TINYINT(1) NOT NULL DEFAULT 0 AFTER min_tier;

-- il modulo in premio per una collezione completata
ALTER TABLE player_items MODIFY source ENUM('npc','pvp','port','planet','wreck','mission','shop','anomaly','encounter','collezione') NOT NULL DEFAULT 'npc';

INSERT INTO recipes (ckey, output_item, label, cost_credits, cost_components, cost_crystals, cost_salvage, cargo_ore, cargo_equ, min_faction, min_tier, progetto, sort) VALUES
  ('r_tela',       'w_tela',       'Tela gravitica xeno',         32000, 26, 150, 420,  60,  0,  NULL, NULL, 1, 70),
  ('r_carapace',   'd_carapace',   'Carapace xeno',               32000, 26, 150, 420,  80,  0,  NULL, NULL, 1, 71),
  ('r_pinne',      'v_pinne',      'Pinne gravitiche xeno',       30000, 24, 140, 400,   0, 40,  NULL, NULL, 1, 72),
  ('r_camaleonte', 'c_camaleonte', 'Firma camaleonte xeno',       34000, 28, 160, 440,   0, 40,  NULL, NULL, 1, 73),
  ('r_nido',       'u_nido',       'Nido riproduttivo xeno',      36000, 30, 170, 460,  60, 60,  NULL, NULL, 1, 74),
  ('r_matrice',    'w_matrice',    'Matrice di forgiatura Precursore', 90000, 60, 400, 1100, 120, 0, NULL, NULL, 1, 80),
  ('r_neutronio',  'd_neutronio',  'Pelle di neutronio',          90000, 60, 400, 1100, 160, 0,  NULL, NULL, 1, 81),
  ('r_fantasma',   'v_fantasma',   'Passo del fantasma',          85000, 56, 380, 1050,   0, 80, NULL, NULL, 1, 82),
  ('r_dominatore', 'c_dominatore', 'Dominatore di segnali',       95000, 64, 420, 1150,   0, 80, NULL, NULL, 1, 83),
  ('r_fabbrica',   'u_fabbrica',   'Fabbrica Precursore',        100000, 70, 450, 1200, 120, 120, NULL, NULL, 1, 84)
ON DUPLICATE KEY UPDATE output_item = VALUES(output_item), label = VALUES(label), progetto = VALUES(progetto);

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('loot.reperto_pct',  '0.12', 'float', '0.12'),
  ('loot.progetto_pct', '0.03', 'float', '0.03')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
