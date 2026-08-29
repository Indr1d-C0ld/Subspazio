-- 0012_admin : supporto al pannello di controllo del gioco

ALTER TABLE game_config ADD COLUMN default_value TEXT NULL;
UPDATE game_config SET default_value = cvalue WHERE default_value IS NULL;

ALTER TABLE users ADD COLUMN session_epoch INT NOT NULL DEFAULT 0;

ALTER TABLE players ADD COLUMN last_seen_at DATETIME NULL;
