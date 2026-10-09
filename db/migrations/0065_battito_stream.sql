-- 0065_battito_stream : correzione del quinto audit (9 ottobre 2026)
--
-- Uno stream ucciso senza chiudersi (riavvio, processo terminato) lasciava la
-- sua riga in live_streams fino al gc, e se era piu' recente di uno vivo lo
-- faceva chiudere per errore. Ogni stream ora batte ogni cinque secondi:
-- conta solo chi ha battuto di recente.
ALTER TABLE live_streams ADD COLUMN IF NOT EXISTS visto_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER aperto_at;
