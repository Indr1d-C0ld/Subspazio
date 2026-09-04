-- 0023_waiting : meccaniche di attesa (punto 1).
--   a) Rapporto di rientro: al ritorno in plancia dopo un'assenza, un
--      riassunto di cosa e' maturato mentre eri via (giornale, colonie,
--      turni, contratti). players.last_digest_at tiene traccia.
--   b) Lavori dell'Officina a tempo: la produzione di un modulo non e' piu'
--      istantanea, entra in coda e matura sul tick. Nessun timer di volo:
--      il warp resta istantaneo, questo e' un "progetto" che cuoce.

ALTER TABLE players ADD COLUMN IF NOT EXISTS last_digest_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS craft_jobs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  INT UNSIGNED NOT NULL,
  recipe_key VARCHAR(40) NOT NULL,
  item_key   VARCHAR(40) NOT NULL,
  item_name  VARCHAR(80) NOT NULL,
  rarity     VARCHAR(12) NOT NULL DEFAULT 'mil',
  cost       JSON NULL,
  ready_at   DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cj_player (player_id, id),
  KEY idx_cj_ready (ready_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('digest.min_away_min',   '20', 'int'),
  ('craft.max_jobs',        '3',  'int'),
  ('craft.job_minutes',     'civ:4,mil:12,exp:35,xeno:90,precursor:180', 'string')
  ON DUPLICATE KEY UPDATE cvalue = cvalue;
