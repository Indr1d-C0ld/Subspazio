-- 0001_core_auth : utenti, configurazione, rate limit, audit, clock
-- (la tabella schema_migrations e' gestita dal Migratore, non qui)

CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(32)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(48)  NULL,
  status        ENUM('pending','active','suspended','banned') NOT NULL DEFAULT 'pending',
  role          ENUM('player','moderator','admin') NOT NULL DEFAULT 'player',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at   DATETIME NULL,
  approved_by   BIGINT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARBINARY(16) NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_config (
  ckey       VARCHAR(64) NOT NULL PRIMARY KEY,
  cvalue     TEXT NOT NULL,
  ctype      ENUM('string','int','float','bool','json') NOT NULL DEFAULT 'string',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  rkey     VARCHAR(190) NOT NULL PRIMARY KEY,
  hits     INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  KEY idx_rate_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action        VARCHAR(64) NOT NULL,
  target_type   VARCHAR(32) NULL,
  target_id     BIGINT UNSIGNED NULL,
  meta          JSON NULL,
  ip            VARBINARY(16) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor (actor_user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tick_runs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  started_at  DATETIME(3) NOT NULL,
  finished_at DATETIME(3) NULL,
  ok          TINYINT(1) NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  tasks       JSON NULL,
  note        VARCHAR(255) NULL,
  KEY idx_tick_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
