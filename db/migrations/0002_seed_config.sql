-- 0002_seed_config : valori di configurazione iniziali della partita

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('game.name',                'SubSpazio',                                  'string'),
  ('game.status',              'setup',                                      'string'),
  ('game.tagline',             'Rotte commerciali nello spazio profondo',    'string'),
  ('registration.open',        'approval',                                   'string'),
  ('universe.sectors',         '1000',                                       'int'),
  ('universe.fedspace_max',    '10',                                         'int'),
  ('universe.stardock_sector', '1',                                          'int'),
  ('universe.warp_density',    '3.2',                                        'float'),
  ('turns.per_day',            '2500',                                       'int'),
  ('turns.reset_hour',         '3',                                          'int'),
  ('turns.timezone',           'Europe/Rome',                                'string'),
  ('player.start_credits',     '1000',                                       'int'),
  ('player.start_ship',        'merchant_cruiser',                           'string'),
  ('player.start_holds',       '20',                                         'int'),
  ('bank.daily_interest_pct',  '0.5',                                        'float')
ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue), ctype = VALUES(ctype);
