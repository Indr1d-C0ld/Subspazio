-- 0028_eps : Slice B2 della roadmap post-beta — griglia di potenza (EPS).
--   Il reattore dà un budget fisso di "tacche" (eps.pips_total, default 8) da
--   ripartire su 4 canali: Scudi / Armi / Motori / Sensori. Il nominale è
--   pips_total/4 (= 2). Ogni tacca di scostamento dal nominale sposta la stat
--   del canale di eps.step_pct (12.5%); a ±2 tacche = ±25%. Ri-allocare costa
--   eps.realloc_turn_cost turni. Logica: src/Game/PowerGrid.php + overlay
--   finale in ShipStats::effective().

ALTER TABLE ships ADD COLUMN IF NOT EXISTS eps_shields TINYINT UNSIGNED NOT NULL DEFAULT 2;
ALTER TABLE ships ADD COLUMN IF NOT EXISTS eps_weapons TINYINT UNSIGNED NOT NULL DEFAULT 2;
ALTER TABLE ships ADD COLUMN IF NOT EXISTS eps_engines TINYINT UNSIGNED NOT NULL DEFAULT 2;
ALTER TABLE ships ADD COLUMN IF NOT EXISTS eps_sensors TINYINT UNSIGNED NOT NULL DEFAULT 2;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('eps.pips_total',        '8',    'int'),
  ('eps.step_pct',          '12.5', 'float'),
  ('eps.realloc_turn_cost', '1',    'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue
