-- 0039_media_auto_approve : le immagini del profilo entrano da sole
--
-- Finora avatar e logo restavano 'pending' finche' l'amministratore non li
-- guardava: chi caricava una foto non la vedeva comparire e non sapeva se
-- fosse arrivata. Con l'autovalidazione dell'iscrizione (0038) l'admin era
-- gia' uscito dalla porta d'ingresso; esce anche da qui.
--
-- Non si butta via nulla: la coda di moderazione, lo stato 'pending' e il
-- pulsante di rimozione restano al loro posto. Spegnendo questa chiave si
-- torna esattamente al comportamento di prima.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('media.auto_approve', '1', 'bool', '1')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
