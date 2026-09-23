-- 0049_pedaggio : un tetto al pedaggio dei caccia schierati
--
-- Il pedaggio non aveva limite e veniva prelevato in automatico a chiunque
-- entrasse e potesse pagarlo: un caccia a 5 milioni vicino allo StarDock
-- svuotava i mercanti di passaggio. Ora da 0 a questo valore.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('deploy.toll_max', '5000', 'int', '5000')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);

-- I pedaggi gia' schierati oltre il tetto vengono riportati al tetto.
UPDATE sector_fighters SET toll = 5000 WHERE mode = 'toll' AND toll > 5000;
