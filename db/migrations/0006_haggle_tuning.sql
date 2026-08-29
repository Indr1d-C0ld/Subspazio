-- 0006_haggle_tuning : bande di contrattazione piu' strette
-- (piu' round, il porto tiene un margine minimo sul prezzo equo)

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('economy.haggle.accept_band', '0.012', 'float'),
  ('economy.haggle.walk_band',   '0.14',  'float'),
  ('economy.haggle.concession',  '0.28',  'float'),
  ('economy.haggle.max_rounds',  '5',     'int'),
  ('economy.haggle.min_margin',  '0.05',  'float')
ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue), ctype = VALUES(ctype);
