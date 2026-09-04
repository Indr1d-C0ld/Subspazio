-- 0022_shiplog : Giornale di bordo.
--   Registro incidenti persistente per giocatore, con voce coerente
--   all'ambientazione. Alimentato dagli hook che gia' scattano (Combat
--   all'ingresso settore, hazard, NPC, fazioni, pianeti, contratti):
--   e' soprattutto un livello di presentazione durevole sopra combat_log,
--   move_log, faction_log e live_events. La campanella (alerts) resta per
--   il toast in tempo reale; il giornale e' la narrazione che si sfoglia.

CREATE TABLE IF NOT EXISTS ship_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  INT UNSIGNED NOT NULL,
  kind       VARCHAR(24) NOT NULL DEFAULT 'system',
  severity   ENUM('info','warning','alert') NOT NULL DEFAULT 'info',
  title      VARCHAR(140) NOT NULL,
  body       TEXT NOT NULL,
  sector_id  INT UNSIGNED NULL,
  data       JSON NULL,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sl_player (player_id, id),
  KEY idx_sl_unread (player_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('shiplog.keep_per_player', '200', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue;
