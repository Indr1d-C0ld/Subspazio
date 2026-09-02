-- 0018_factions : Fase 10 — fazioni & reputazione.
--   Quattro potenze. Reputazione per giocatore -100..+100 con soglie di
--   standing. Le azioni la muovono; il tier sblocca sconti/moduli/passaggio
--   e la pirateria contro la Federazione richiama cacciatori di taglie.

CREATE TABLE IF NOT EXISTS factions (
  ckey  VARCHAR(16) NOT NULL PRIMARY KEY,
  name  VARCHAR(48) NOT NULL,
  blurb VARCHAR(220) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#9db0c8',
  rival VARCHAR(16) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO factions (ckey, name, blurb, color, rival) VALUES
  ('fed',      'Federazione Unita',           'Ordine, scienza e protezione. Controlla la Federazione e le rotte centrali. Non perdona la pirateria contro i suoi.', '#6be2ff', 'hegemony'),
  ('ferrengi', 'Consorzio Ferrengi',          'Cartello commerciale mobile. Prezzi migliori e mercato nero per chi sta nelle sue grazie, e disprezza la Federazione.',  '#ffcf6b', 'fed'),
  ('hegemony', 'Egemonia di Korr',            'Militarista ed espansionista. Non le importa il tuo allineamento, le importano i tuoi bersagli. Armi e navi da guerra.', '#ff7d7d', 'fed'),
  ('frontier', 'Liberi Mondi della Frontiera','Indipendenti, recuperatori, pirati con un codice. Odiano chi bombarda i pianeti e premiano chi lavora il profondo.',     '#b493ff', 'hegemony')
ON DUPLICATE KEY UPDATE name=VALUES(name), blurb=VALUES(blurb), color=VALUES(color), rival=VALUES(rival);

ALTER TABLE regions ADD COLUMN IF NOT EXISTS faction VARCHAR(16) NULL;
UPDATE regions SET faction = 'fed'      WHERE kind IN ('federation','core');
UPDATE regions SET faction = CASE WHEN (id % 2) = 0 THEN 'hegemony' ELSE 'frontier' END WHERE kind = 'frontier';
UPDATE regions SET faction = NULL       WHERE kind = 'deep';

CREATE TABLE IF NOT EXISTS player_reputation (
  player_id  BIGINT UNSIGNED NOT NULL,
  faction    VARCHAR(16) NOT NULL,
  value      SMALLINT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, faction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faction_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  player_id  BIGINT UNSIGNED NOT NULL,
  faction    VARCHAR(16) NOT NULL,
  delta      SMALLINT NOT NULL,
  reason     VARCHAR(48) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fl_player (player_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faction_offers (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  faction  VARCHAR(16) NOT NULL,
  kind     ENUM('module') NOT NULL DEFAULT 'module',
  ref      VARCHAR(40) NOT NULL,
  min_tier ENUM('friendly','allied') NOT NULL DEFAULT 'friendly',
  price    INT UNSIGNED NOT NULL DEFAULT 0,
  label    VARCHAR(64) NOT NULL,
  sort     SMALLINT NOT NULL DEFAULT 100,
  KEY idx_fo_faction (faction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO faction_offers (faction, ref, min_tier, price, label, sort) VALUES
  ('fed',      'd_ondulati',   'friendly', 1800,  'Scudi ondulati (dote Federazione)', 10),
  ('fed',      'c_oloscanner', 'allied',   2600,  'Olo-scanner Federazione',           11),
  ('ferrengi', 'u_recuperatore','friendly',2000,  'Braccio recuperatore Ferrengi',     20),
  ('ferrengi', 'u_drone',      'allied',   9000,  'Drone d\'officina del Consorzio',    21),
  ('hegemony', 'w_railgun',    'friendly', 2400,  'Railgun dell\'Egemonia',            30),
  ('hegemony', 'w_plasma',     'allied',   7000,  'Lancia al plasma dell\'Egemonia',   31),
  ('frontier', 'd_deflettore', 'friendly', 6500,  'Deflettore dei Liberi Mondi',       40),
  ('frontier', 'c_preveggenza','allied',   18000, 'Preveggenza xeno dei Liberi Mondi', 41)
ON DUPLICATE KEY UPDATE price=VALUES(price), label=VALUES(label), sort=VALUES(sort);

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('faction.tier_hostile',   '-60',  'int'),
  ('faction.tier_wary',      '-20',  'int'),
  ('faction.tier_friendly',  '20',   'int'),
  ('faction.tier_allied',    '60',   'int'),
  ('faction.max',            '100',  'int'),
  ('faction.min',            '-100', 'int'),
  ('faction.decay_per_day',  '2',    'int'),
  ('faction.rivalry',        '0.35', 'float'),
  ('faction.trade_gain',     '1',    'int'),
  ('faction.kill_gain',      '6',    'int'),
  ('faction.bust_loss',      '12',   'int'),
  ('faction.bomb_loss',      '25',   'int'),
  ('faction.deep_gain',      '2',    'int'),
  ('faction.mission_gain',   '10',   'int'),
  ('faction.amnesty_cost',   '15000','int'),
  ('faction.amnesty_target', '-30',  'int'),
  ('faction.bh_min_bounty',  '2000', 'int'),
  ('faction.bh_chance_pct',  '25',   'int'),
  ('faction.decay_last_run', '',     'string')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
