-- 0054_legge : notorieta', crimini e pattuglie federali
--
-- Assaltare mercantili non costava niente: in una settimana un comandante ne
-- ha abbattuti 22 e incassato 823.688 cr, con l'allineamento a -295 e la taglia
-- ancora a zero (la taglia scattava solo uccidendo giocatori onesti). Nessuna
-- autorita' pattugliava lo spazio.
--
-- Ora ogni aggressione a civili (mercantili, porti, pianeti altrui, comandanti
-- non ricercati, pattuglie) alza la notorieta', che cala da sola col tempo
-- (dimezza ogni legge.dimezza_ore) e cresce piu' in fretta per i recidivi. A
-- gradini: Sospetto, Ricercato, Pericoloso, Nemico pubblico. Le pattuglie
-- federali girano nelle fasce vicine a Sol e danno la caccia ai ricercati con
-- squadre tarate sulla loro nave; dal gradino Pericoloso lo StarDock chiude i
-- servizi; da Ricercato scatta una taglia pagata a chi abbatte il colpevole.

ALTER TABLE players ADD COLUMN notorieta DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER bounty,
                    ADD COLUMN notorieta_at DATETIME NULL AFTER notorieta;

CREATE TABLE crimini (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  kind       VARCHAR(24)  NOT NULL,
  punti      DECIMAL(8,2) NOT NULL,
  sector_id  BIGINT UNSIGNED NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_crimini_player (player_id, created_at),
  CONSTRAINT fk_crimini_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pattuglie federali. Senza bersaglio fanno la ronda; con un bersaglio
-- inseguono quel ricercato.
ALTER TABLE npcs MODIFY kind ENUM('ferrengi', 'pirate', 'trader', 'patrol') NOT NULL,
                 ADD COLUMN target_player_id BIGINT UNSIGNED NULL AFTER band;

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('legge.soglie',             '25,100,250,500',  'string', '25,100,250,500'),
  ('legge.dimezza_ore',        '24',              'int',    '24'),
  ('legge.crimine_mercantile', '15',              'int',    '15'),
  ('legge.crimine_uccisione',  '30',              'int',    '30'),
  ('legge.crimine_porto',      '40',              'int',    '40'),
  ('legge.crimine_pianeta',    '20',              'int',    '20'),
  ('legge.crimine_bombardamento', '50',           'int',    '50'),
  ('legge.crimine_aggressione','25',              'int',    '25'),
  ('legge.crimine_pattuglia',  '80',              'int',    '80'),
  ('legge.recidiva_ore',       '72',              'int',    '72'),
  ('legge.recidiva_passo',     '0.25',            'float',  '0.25'),
  ('legge.taglia_per_punto',   '300',             'int',    '300'),
  ('legge.ammenda_per_punto',  '400',             'int',    '400'),
  ('legge.squadre',            '0,0,1,2,3',       'string', '0,0,1,2,3'),
  ('legge.forza',              '0,0,0.7,1.0,1.4', 'string', '0,0,0.7,1.0,1.4'),
  ('legge.ronde',              '14',              'int',    '14'),
  ('legge.ronde_fino_a',       '3',               'int',    '3'),
  ('legge.ingaggio_pct',       '70',              'int',    '70')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
