-- 0034_hot_indexes : indici sulle colonne filtrate dalle query ricorrenti
--   (reperto 06 dell'audit del 2026-09-15).
--
-- Quattro colonne usate in WHERE/ORDER BY senza indice. Oggi non si notano —
-- le tabelle sono minuscole — ma due di queste crescono senza limite e le
-- altre due vengono interrogate a ogni minuto o a ogni apertura del pannello.
--
--   npcs.last_move_at       il tick seleziona gli NPC da muovere, ogni 60 s
--   players.last_seen_at    conteggio "online" e ordinamento del pannello admin
--   trade_log.created_at    statistiche 24h; tabella a crescita illimitata
--   combat_log.created_at   statistiche 24h e notiziario FedNews; idem
--
-- Deliberatamente NON aggiunti, dopo verifica delle query reali:
--   messages.created_at     mai usata in WHERE né ORDER BY (si ordina per id)
--   players.created_at      solo FedNews, una volta ogni 24 ore
--   planets.created_at      idem
--
-- IF NOT EXISTS: le migrazioni devono essere idempotenti (vedi App\Cli\Migrator).

CREATE INDEX IF NOT EXISTS idx_npcs_last_move ON npcs (last_move_at);
CREATE INDEX IF NOT EXISTS idx_players_last_seen ON players (last_seen_at);
CREATE INDEX IF NOT EXISTS idx_trade_log_created ON trade_log (created_at);
CREATE INDEX IF NOT EXISTS idx_combat_log_created ON combat_log (created_at);

-- Manopole della salute del clock (reperti 03 e 05), registrate qui perché
-- compaiano nel pannello di configurazione invece di restare default nascosti
-- dentro il codice.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('tick.keep_days',            '14', 'int', '14'),
  ('tick.alert_after_failures',  '5', 'int',  '5')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
