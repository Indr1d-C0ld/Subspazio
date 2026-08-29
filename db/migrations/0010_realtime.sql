-- 0010_realtime : bus di eventi live (SSE) e alert persistenti

CREATE TABLE IF NOT EXISTS live_events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope      ENUM('global','sector','player','corp') NOT NULL,
  scope_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  kind       VARCHAR(32) NOT NULL,
  title      VARCHAR(120) NULL,
  body       VARCHAR(400) NULL,
  payload    JSON NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_live_scope (scope, scope_id, id),
  KEY idx_live_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  kind       VARCHAR(32) NOT NULL,
  title      VARCHAR(120) NOT NULL,
  body       VARCHAR(400) NOT NULL,
  link       VARCHAR(160) NULL,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_alerts_player (player_id, id),
  KEY idx_alerts_unread (player_id, read_at),
  CONSTRAINT fk_alerts_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('live.retention_min',  '60',   'int'),
  ('live.stream_max_s',   '300',  'int'),
  ('live.tick_ms',        '2000', 'int')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
