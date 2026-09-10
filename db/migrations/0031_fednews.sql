-- 0031_fednews : Slice C3 — bollettino quotidiano "Notiziario della Federazione"
--   / Frontier Broadcast. Il tick, se e' passato fednews.interval_hours dall'ultimo,
--   compone un bollettino da stato reale del gioco (evento di mercato attivo,
--   cronaca di frontiera, nuove colonie, vertice classifica, riga di servizio) e
--   lo pubblica sul canale Radio 'fedcomm', firmato da un conduttore NPC che ruota.
--   Archivio in `fednews`. Motore: src/Game/FedNews.php.

CREATE TABLE IF NOT EXISTS fednews (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  anchor     VARCHAR(80) NOT NULL,
  headlines  JSON NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('fednews.enabled',        '1',  'bool'),
  ('fednews.interval_hours', '24', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue
