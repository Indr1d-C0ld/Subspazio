-- 0020_onboarding : primi passi guidati per i nuovi comandanti.
--   onboarding_state: 0 = attivo, 1 = nascosto dall'utente, 2 = completato e premiato.

ALTER TABLE players ADD COLUMN IF NOT EXISTS onboarding_state TINYINT NOT NULL DEFAULT 0;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('onboarding.reward_credits', '5000', 'int')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
