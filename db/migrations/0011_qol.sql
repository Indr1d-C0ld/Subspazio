-- 0011_qol : preferiti/note sui settori (replay battaglie e cronologia
--            rotte usano combat_log e move_log gia' esistenti)

CREATE TABLE IF NOT EXISTS player_sector_notes (
  player_id  BIGINT UNSIGNED NOT NULL,
  sector_id  INT UNSIGNED NOT NULL,
  label      VARCHAR(32) NULL,
  note       VARCHAR(255) NULL,
  pinned     TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, sector_id),
  KEY idx_psn_pinned (player_id, pinned),
  CONSTRAINT fk_psn_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_psn_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
