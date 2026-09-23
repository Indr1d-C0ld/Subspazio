-- 0043_registro_battaglie : chi muore entrando in un settore non ha "vinto"
--
-- Negli scontri all'ingresso (NPC aggressori, mine, Quasar, caccia schierati)
-- chi entra e' registrato come difensore. Quando moriva, l'esito veniva scritto
-- 'att_destroyed' — attaccante distrutto — e il registro battaglie, che legge
-- l'esito dal punto di vista del giocatore, gli mostrava una «vittoria».
-- Speculare per l'NPC aggressore abbattuto: 'def_win' invece di 'att_destroyed'.
--
-- Il codice ora scrive l'esito giusto; qui si raddrizza lo storico. I lettori
-- di questi codici (traguardi, bottino PvP, notiziario) guardano solo le righe
-- in cui il giocatore e' l'attaccante, quindi nessun conteggio cambia.
UPDATE combat_log SET outcome = 'def_destroyed'
 WHERE kind IN ('mines', 'quasar', 'fighters') AND outcome = 'att_destroyed';

UPDATE combat_log
   SET outcome = CASE outcome WHEN 'att_destroyed' THEN 'def_destroyed'
                              WHEN 'def_win'       THEN 'att_destroyed' END
 WHERE kind = 'npc' AND attacker_player_id IS NULL
   AND outcome IN ('att_destroyed', 'def_win');
