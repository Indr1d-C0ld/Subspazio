-- 0042_iscrizioni_scadute : le iscrizioni mai confermate decadono davvero
--
-- L'e-mail di verifica prometteva che, senza conferma, l'account "sparisce da
-- solo". Non lo cancellava nessuno. La conseguenza concreta: chi sbaglia a
-- scrivere l'indirizzo non puo' farsi rispedire il collegamento (partirebbe
-- verso l'indirizzo sbagliato) e resta con il nome utente occupato per sempre.
--
-- Ora un lavoro del clock (iscrizioni_gc, Auth::gcPending) cancella le
-- iscrizioni `pending` mai verificate dopo questo numero di giorni, contati
-- dall'ULTIMO collegamento spedito: chi ha chiesto un rinvio ha diritto al suo
-- tempo intero.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('auth.pending_ttl_days', '7', 'int', '7')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
