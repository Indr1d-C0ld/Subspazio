-- 0045_cacciatori : un tetto ai cacciatori di taglie
--
-- Il clock ne creava uno nuovo, ogni minuto, con il 25% di probabilita' per
-- ogni ricercato, senza guardare quanti ce ne fossero gia'. Ora al massimo
-- uno per ricercato e questo numero in tutto l'universo.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('faction.bh_max', '5', 'int', '5')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
