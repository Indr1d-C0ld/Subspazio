-- 0050_stream : un limite alle connessioni in tempo reale per giocatore
--
-- Ogni stream SSE occupa un processo del server fino a live.stream_max_s
-- secondi, e non c'era alcun limite per utente. Una scheda aperta ne usa uno
-- ogni cinque minuti: sei aperture al minuto lasciano ampio margine.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('live.stream_opens_per_min', '6', 'int', '6')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
