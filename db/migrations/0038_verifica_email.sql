-- 0038_verifica_email : l'iscrizione si autovalida, l'admin esce dalla porta
--
-- Prima ogni account nasceva 'pending' e restava li' finche' l'amministratore
-- non lo attivava a mano. Ora chi si iscrive riceve un collegamento e si
-- attiva da solo confermando l'indirizzo — stesso schema di Atlantik e
-- CthulhuMUD.
--
-- In tabella finisce solo l'HASH del gettone: chi legge il database non puo'
-- usarlo per entrare al posto di qualcun altro. Ogni gettone e' monouso e
-- scade; la stessa tabella regge anche il recupero della password.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS verify_sent_at    DATETIME NULL AFTER email_verified_at,
  ADD COLUMN IF NOT EXISTS verify_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER verify_sent_at;

CREATE TABLE IF NOT EXISTS user_tokens (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  kind       ENUM('verify_email','reset_password') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_ip VARBINARY(16) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_token_user (user_id, kind),
  KEY idx_token_expires (expires_at),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gli account gia' attivi non devono ritrovarsi fuori: chi c'era, c'era.
UPDATE users SET email_verified_at = COALESCE(approved_at, created_at)
 WHERE status = 'active' AND email_verified_at IS NULL;

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('auth.verify_ttl_hours',   '48', 'int', '48'),
  ('auth.resend_wait_min',    '10', 'int', '10'),
  ('auth.resend_max',          '5', 'int',  '5'),
  ('auth.reset_ttl_hours',     '2', 'int',  '2')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
