-- 0035_limiti_azioni : freno sulle azioni di gioco (reperto 11 dell'audit).
-- Soglia volutamente larga: un umano non ci arriva, chi martella in automatico
-- la sfonda subito. A 0 il freno e' disattivato.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('limits.player_actions_per_min', '120', 'int', '120')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
