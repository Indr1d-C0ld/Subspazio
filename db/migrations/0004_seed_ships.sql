-- 0004_seed_ships : catalogo tipi nave (bilanciamento nostro, archetipi ispirati a TW)
--                   + parametri di configurazione della Fase 1

INSERT INTO ship_types
  (ckey, name, base_holds, max_holds, base_fighters, max_fighters, base_shields, max_shields, turns_per_warp, can_transwarp, hold_price, base_cost, sort_order)
VALUES
  ('escape_pod',        'Capsula di salvataggio', 0,  0,     0,      0,     0,     0,    1, 0,     0,        0,   0),
  ('scout_marauder',    'Scout Marauder',         10, 25,    250,    2500,  100,   400,  1, 0,   500,    15000,  10),
  ('merchant_cruiser',  'Merchant Cruiser',       20, 75,    750,    10000, 200,   1500, 1, 0,   400,    41300,  20),
  ('missile_frigate',   'Missile Frigate',        12, 60,    2000,   15000, 300,   2000, 2, 0,   600,    62700,  30),
  ('constellation',     'Constellation',          20, 80,    5000,   20000, 500,   3000, 1, 0,   750,   125000,  40),
  ('merchant_freighter','Merchant Freighter',     30, 140,   1000,   6000,  200,   1000, 2, 0,   350,    58700,  50),
  ('cargo_transport',   'Cargo Transport',        50, 125,   500,    4000,  150,   800,  2, 0,   300,    38700,  60),
  ('colonial_transport','Colonial Transport',     40, 250,   200,    3000,  100,   500,  3, 0,   250,    52200,  70),
  ('corporate_flagship','Corporate Flagship',     20, 85,    10000,  50000, 750,   6000, 2, 1,   800,   525000,  80),
  ('havoc_gunstar',     'Havoc Gunstar',          12, 50,    10000,  25000, 400,   3000, 2, 0,   700,   199000,  90),
  ('imperial_starship', 'Imperial StarShip',      40, 150,   50000,  100000,2000,  16000,3, 1,   900,  1150000, 100),
  ('tholian_sentinel',  'Tholian Sentinel',       5,  50,    2000,   8000,  2000,  4000, 3, 0,   900,   315000, 110),
  ('interdictor',       'Interdictor Cruiser',    10, 40,    100000, 200000,1000,  8000, 4, 1,   950,  2900000, 120)
ON DUPLICATE KEY UPDATE
  name = VALUES(name), base_holds = VALUES(base_holds), max_holds = VALUES(max_holds),
  base_fighters = VALUES(base_fighters), max_fighters = VALUES(max_fighters),
  base_shields = VALUES(base_shields), max_shields = VALUES(max_shields),
  turns_per_warp = VALUES(turns_per_warp), can_transwarp = VALUES(can_transwarp),
  hold_price = VALUES(hold_price), base_cost = VALUES(base_cost), sort_order = VALUES(sort_order);

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('universe.generated_at',   '',    'string'),
  ('universe.region_bands',   '3',   'int'),
  ('map.node_radius',         '6',   'float'),
  ('nav.autopilot_max_hops',  '200', 'int')
ON DUPLICATE KEY UPDATE ctype = VALUES(ctype);
