-- 0062_audit_ottobre : correzioni del quarto audit (9 ottobre 2026)
--
-- La nave di soccorso regalata dallo StarDock si riconosce: abbatterla non da'
-- esperienza ne' bottino, come la capsula. Un secondo account la ritirava, se
-- la faceva abbattere e ne ritirava un'altra, e il principale incassava
-- esperienza e moduli. Il contrassegno cade al primo acquisto di uno scafo.
ALTER TABLE ships ADD COLUMN IF NOT EXISTS soccorso TINYINT(1) NOT NULL DEFAULT 0 AFTER cloak_carica_at;

-- Stream in tempo reale aperti, uno per pagina. Il tetto contava le aperture
-- al minuto (6): chi cambiava schermata piu' spesso restava senza avvisi
-- sulle pagine successive, e i giocatori attivi prendevano centinaia di
-- rifiuti l'ora. Ora conta gli stream aperti: quando se ne apre uno di
-- troppo, si chiude il piu' vecchio (di solito una pagina gia' lasciata).
CREATE TABLE IF NOT EXISTS live_streams (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  player_id  BIGINT UNSIGNED NOT NULL,
  aperto_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_live_streams_player (player_id, id),
  CONSTRAINT fk_live_streams_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('live.stream_paralleli', '3',  'int', '3'),
  ('eventi.ondata_ore',     '48', 'int', '48')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);

-- Le aperture al minuto restano solo come argine contro i cicli impazziti.
UPDATE game_config SET cvalue = '30' WHERE ckey = 'live.stream_opens_per_min' AND cvalue = '6';
UPDATE game_config SET default_value = '30' WHERE ckey = 'live.stream_opens_per_min';
