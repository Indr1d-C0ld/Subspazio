-- 0044_tracce_delle_prove : via le battaglie di comandanti che non sono mai esistiti
--
-- La suite di prove (introdotta il 15/09/2026) cancellava comandanti, navi e
-- utenti sintetici, ma non le righe che le prove scrivevano in tabelle senza
-- chiave esterna verso players: registro battaglie, giornale di bordo, codex.
-- Al 23/09/2026 il registro battaglie reale contava 220 righe, 205 delle quali
-- di comandanti di prova.
--
-- Da ora l'impalcatura le toglie a fine esecuzione (Finti::spazzaTracce). Qui
-- si ripulisce lo storico, limitandosi a righe nate dal 15/09/2026 in poi e
-- riferite a comandanti inesistenti. L'unico reset di un giocatore registrato
-- nell'audit risale al 29/08/2026, quindi nessuna storia autentica e' toccata.
DELETE c FROM combat_log c
  LEFT JOIN players pa ON pa.id = c.attacker_player_id
  LEFT JOIN players pd ON pd.id = c.defender_player_id
 WHERE c.created_at >= '2026-09-15 00:00:00'
   AND (c.attacker_player_id IS NULL OR pa.id IS NULL)
   AND (c.defender_player_id IS NULL OR pd.id IS NULL);

DELETE l FROM ship_log l LEFT JOIN players p ON p.id = l.player_id
 WHERE l.created_at >= '2026-09-15 00:00:00' AND p.id IS NULL;

DELETE c FROM player_codex c LEFT JOIN players p ON p.id = c.player_id
 WHERE c.unlocked_at >= '2026-09-15 00:00:00' AND p.id IS NULL;
