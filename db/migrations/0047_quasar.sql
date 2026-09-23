-- 0047_quasar : un tetto al livello del cannone Quasar
--
-- Il livello non aveva limite: il danno (livello x 2.200) cresceva senza fine,
-- e al livello 256 la colonna TINYINT traboccava con un errore.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('planet.quasar_max_level', '10', 'int', '10')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
