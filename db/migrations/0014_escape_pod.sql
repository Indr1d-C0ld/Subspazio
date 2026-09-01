-- 0014_escape_pod : la capsula di salvataggio non e' piu' un vicolo cieco.
--   - stive minime, per poter commerciare e risalire
--   - "nave di soccorso" della Federazione quando si e' senza crediti
--     (gestita dal Cantiere, vedi App\Game\Shipyard::rescueShip)

UPDATE ship_types SET base_holds = 5, max_holds = 5 WHERE ckey = 'escape_pod';

-- allinea le capsule gia' in volo
UPDATE ships SET holds_total = 5 WHERE type_key = 'escape_pod' AND holds_total < 5;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('hardware.pod_holds',        '5',              'int'),
  ('hardware.rescue_ship_type', 'scout_marauder', 'string')
ON DUPLICATE KEY UPDATE cvalue = cvalue;
