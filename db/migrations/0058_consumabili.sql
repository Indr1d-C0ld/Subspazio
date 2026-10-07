-- 0058_consumabili : oggetti monouso dal bottino
--
-- Nanoriparatori, container di caccia, cariche EMP, acceleratori di warp, esche
-- olografiche, nuclei in sovraccarico, codici di amnistia contraffatti, sonde
-- di recupero: si trovano abbattendo nemici (piu' spesso e piu' rari lontano da
-- Sol) e si usano una volta, dalla plancia.

CREATE TABLE player_consumabili (
  player_id BIGINT UNSIGNED NOT NULL,
  ckey      VARCHAR(32)     NOT NULL,
  qty       INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (player_id, ckey),
  CONSTRAINT fk_consumabili_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('loot.consumabile_pct', '0.25', 'float', '0.25')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
