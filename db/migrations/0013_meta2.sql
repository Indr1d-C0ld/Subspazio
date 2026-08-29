-- 0013_meta2 : stagioni/ladder, achievement, mercato nero (via config),
--              alleanze fra corp, contratti/taglie fra giocatori

CREATE TABLE IF NOT EXISTS seasons (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  number     INT NOT NULL,
  name       VARCHAR(64) NOT NULL,
  status     ENUM('active','ended') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at   DATETIME NULL,
  UNIQUE KEY uq_seasons_number (number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS season_results (
  season_id  BIGINT UNSIGNED NOT NULL,
  position   INT NOT NULL,
  player_id  BIGINT UNSIGNED NULL,
  handle     VARCHAR(32) NOT NULL,
  rating     BIGINT NOT NULL,
  experience INT NOT NULL,
  kills      INT NOT NULL,
  planets    INT NOT NULL,
  PRIMARY KEY (season_id, position),
  KEY idx_sr_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achievements (
  ckey       VARCHAR(32) NOT NULL PRIMARY KEY,
  name       VARCHAR(48) NOT NULL,
  descr      VARCHAR(160) NOT NULL,
  icon       VARCHAR(8) NOT NULL DEFAULT '*',
  points     INT NOT NULL DEFAULT 10,
  sort_order INT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_achievements (
  player_id BIGINT UNSIGNED NOT NULL,
  ckey      VARCHAR(32) NOT NULL,
  earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (player_id, ckey),
  KEY idx_pa_player (player_id),
  CONSTRAINT fk_pa_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corp_alliances (
  corp_lo     BIGINT UNSIGNED NOT NULL,
  corp_hi     BIGINT UNSIGNED NOT NULL,
  status      ENUM('proposed','active') NOT NULL DEFAULT 'proposed',
  proposed_by BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (corp_lo, corp_hi),
  KEY idx_ca_hi (corp_hi),
  CONSTRAINT fk_ca_lo FOREIGN KEY (corp_lo) REFERENCES corporations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_hi FOREIGN KEY (corp_hi) REFERENCES corporations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind             ENUM('bounty','delivery') NOT NULL,
  issuer_player_id BIGINT UNSIGNED NOT NULL,
  target_player_id BIGINT UNSIGNED NULL,
  commodity        ENUM('ore','organics','equipment') NULL,
  qty              INT UNSIGNED NULL,
  sector_id        INT UNSIGNED NULL,
  reward           BIGINT NOT NULL,
  status           ENUM('open','claimed','cancelled','expired') NOT NULL DEFAULT 'open',
  claimed_by       BIGINT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at       DATETIME NULL,
  KEY idx_contracts_status (status, id),
  KEY idx_contracts_target (target_player_id, status),
  KEY idx_contracts_issuer (issuer_player_id, id),
  CONSTRAINT fk_contracts_issuer FOREIGN KEY (issuer_player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO seasons (number, name) VALUES (1, 'Stagione 1')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO achievements (ckey, name, descr, icon, points, sort_order) VALUES
  ('first_trade',    'Primo affare',        'Concludi il tuo primo scambio a un porto.',              '$',  5,  10),
  ('millionaire',    'Milionario',          'Accumula 1.000.000 di crediti fra contanti e banca.',    '$$', 20, 20),
  ('tycoon',         'Magnate',             'Accumula 10.000.000 di crediti.',                        '$$$',40, 30),
  ('first_kill',     'Battesimo del fuoco', 'Distruggi la nave di un altro comandante.',              '!',  15, 40),
  ('warlord',        'Signore della guerra','Raggiungi 25 uccisioni.',                                '!!', 40, 50),
  ('pod_survivor',   'Naufrago',            'Sopravvivi alla distruzione della tua nave.',            'o',  10, 60),
  ('port_buster',    'Predone dei porti',   'Espugna un porto.',                                      'x',  20, 70),
  ('ferrengi_hunter','Cacciatore Ferrengi', 'Abbatti 10 navi Ferrengi.',                             'F',  30, 80),
  ('explorer_100',   'Cartografo',          'Visita 100 settori distinti.',                           '#',  15, 90),
  ('explorer_500',   'Esploratore',         'Visita 500 settori distinti.',                           '##', 35, 100),
  ('first_planet',   'Colono',              'Crea il tuo primo pianeta con un siluro Genesi.',        'P',  15, 110),
  ('colonizer',      'Colonizzatore',       'Possiedi 5 pianeti contemporaneamente.',                 'PP', 30, 120),
  ('citadel_master', 'Signore della Citadel','Porta una Citadel al livello 6.',                       'C',  40, 130),
  ('quasar_builder', 'Artigliere',          'Costruisci un cannone Quasar.',                          'Q',  25, 140),
  ('corp_founder',   'Fondatore',           'Fonda una corporazione.',                                '&',  10, 150),
  ('black_market',   'Cliente losco',       'Fai un affare al mercato nero.',                         'b',  10, 160),
  ('contract_claim', 'Mercenario',          'Riscuoti un contratto o una taglia.',                    '@',  20, 170),
  ('season_top10',   'Fra i migliori',      'Chiudi una stagione nella top 10.',                      '*',  50, 180)
ON DUPLICATE KEY UPDATE name = VALUES(name), descr = VALUES(descr), icon = VALUES(icon), points = VALUES(points), sort_order = VALUES(sort_order);

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('season.number',            '1',    'int'),
  ('season.wipe_planets',      '1',    'bool'),
  ('season.wipe_corps',        '0',    'bool'),
  ('season.regen_universe',    '0',    'bool'),
  ('season.snapshot_top',      '25',   'int'),
  ('contract.max_open',        '5',    'int'),
  ('contract.min_reward',      '500',  'int'),
  ('contract.expiry_hours',    '72',   'int'),
  ('blackmarket.sell_premium', '1.15', 'float'),
  ('blackmarket.hw_discount',  '0.75', 'float'),
  ('blackmarket.align_per_sale','-3',  'int'),
  ('blackmarket.align_per_buy','-5',   'int'),
  ('blackmarket.bounty_clear_mult','1.5','float')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);

UPDATE game_config SET default_value = cvalue WHERE default_value IS NULL;
