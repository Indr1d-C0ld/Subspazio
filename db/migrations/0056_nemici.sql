-- 0056_nemici : fasce ripide, flotte, comandanti d'elite
--
-- Anche nell'Orlo gli ostili arrivavano al massimo a 11.000 caccia (predoni)
-- e 14.000 (Ferrengi): un'ammiraglia da 50.000 li spazzava via ovunque. Ora le
-- fasce I e II restano quelle di chi comincia, e dalla III la forza sale
-- ripida, fino a 90.000 caccia per un predone e 160.000 per un Ferrengi
-- nell'Orlo. Dalla III gli ostili possono viaggiare in flotte di due-quattro
-- navi che si muovono e combattono insieme; in ogni fascia dalla II c'e' un
-- comandante d'elite, molto piu' forte, con bottino garantito. Crediti ed
-- esperienza salgono in proporzione.

ALTER TABLE npcs ADD COLUMN flotta_id BIGINT UNSIGNED NULL AFTER band,
                 ADD COLUMN elite TINYINT(1) NOT NULL DEFAULT 0 AFTER flotta_id,
                 ADD INDEX idx_npcs_flotta (flotta_id);

UPDATE game_config SET cvalue = '80-250,250-650,1500-5000,6000-25000,25000-90000',
                       default_value = '80-250,250-650,1500-5000,6000-25000,25000-90000' WHERE ckey = 'fasce.predoni_caccia';
UPDATE game_config SET cvalue = '0.7,0.85,1.1,1.4,1.8', default_value = '0.7,0.85,1.1,1.4,1.8' WHERE ckey = 'fasce.predoni_rating';
UPDATE game_config SET cvalue = '1500-3500,2000-4500,8000-20000,15000-50000,50000-160000',
                       default_value = '1500-3500,2000-4500,8000-20000,15000-50000,50000-160000' WHERE ckey = 'fasce.ferrengi_caccia';
UPDATE game_config SET cvalue = '800-3000,2500-8000,8000-25000,20000-70000,60000-200000',
                       default_value = '800-3000,2500-8000,8000-25000,20000-70000,60000-200000' WHERE ckey = 'fasce.crediti_npc';
UPDATE game_config SET cvalue = '0.5,0.75,1.2,2,3', default_value = '0.5,0.75,1.2,2,3' WHERE ckey = 'fasce.xp_mult';

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('fasce.flotta_pct',    '0,0,20,40,60',        'string', '0,0,20,40,60'),
  ('elite.per_fascia',    '0,1,1,1,2',           'string', '0,1,1,1,2'),
  ('elite.molt_caccia',   '1.5',                 'float',  '1.5'),
  ('elite.molt_crediti',  '4',                   'float',  '4'),
  ('elite.molt_xp',       '3',                   'float',  '3'),
  ('elite.rinascita_pct', '2',                   'int',    '2'),
  ('elite.rarita',        'civ,mil,exp,xeno,xeno', 'string', 'civ,mil,exp,xeno,xeno')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);

-- La popolazione ostile nata con le forze di prima se ne va; il clock la rifa'.
DELETE FROM npcs WHERE kind IN ('ferrengi', 'pirate') AND name <> 'Cacciatore di taglie';
