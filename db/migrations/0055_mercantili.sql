-- 0055_mercantili : scorte, fuga e richiesta di soccorso
--
-- Un mercantile aveva in media 322 caccia, non reagiva e portava circa 58.000
-- cr di contanti: un bottino senza rischio. Ora viaggia con una scorta armata
-- proporzionata alla fascia, puo' tentare la fuga prima dello scontro, e lancia
-- una richiesta di soccorso che porta una pattuglia federale contro
-- l'aggressore, ricercato o no. A bordo pochi contanti e molta piu' merce: da
-- rivendere, quindi altro tempo esposti.

ALTER TABLE npcs ADD COLUMN scorta INT UNSIGNED NOT NULL DEFAULT 0 AFTER fighters,
                 ADD COLUMN scade_at DATETIME NULL AFTER target_player_id;

-- I mercantili nati con le vecchie regole se ne vanno; il clock li rifa' scortati.
DELETE FROM npcs WHERE kind = 'trader';

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('mercanti.scorta_caccia', '300-800,800-2500,2500-7000,7000-20000,20000-60000', 'string', '300-800,800-2500,2500-7000,7000-20000,20000-60000'),
  ('mercanti.scorta_rating', '0.9,1.0,1.2,1.4,1.6', 'string', '0.9,1.0,1.2,1.4,1.6'),
  ('mercanti.contanti',      '0.3',                 'float',  '0.3'),
  ('mercanti.fuga_pct',      '35',                  'int',    '35'),
  ('mercanti.soccorso_pct',  '100,90,75,50,30',     'string', '100,90,75,50,30'),
  ('mercanti.soccorso_min',  '30',                  'int',    '30'),
  ('mercanti.soccorso_forza','0.6',                 'float',  '0.6')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
