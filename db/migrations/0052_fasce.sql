-- 0052_fasce : fasce di rischio concentriche attorno a Sol
--
-- Chi usciva dalla Federazione incontrava subito Ferrengi da 2.500-9.000
-- caccia e predoni da 600-3.000, contro i 750 della nave iniziale: nei trenta
-- giorni prima di questa migrazione, 22 ingaggi NPC contro giocatori, 22 navi
-- distrutte. Gli NPC nascevano e vagavano ovunque fuori dalla Federazione,
-- anche a un salto da Sol.
--
-- Ora lo spazio e' diviso in cinque fasce concentriche attorno allo StarDock
-- (Sol), per distanza sulla mappa galattica, piu' lo spazio federale (fascia
-- 0). Piu' ci si allontana, piu' le minacce sono forti e frequenti e piu'
-- rendono commercio, bottino, anomalie e missioni. Le soglie sono frazioni del
-- raggio della galassia, cosi' valgono anche dopo un Big Bang di altra misura.

ALTER TABLE sectors ADD COLUMN band TINYINT UNSIGNED NULL AFTER is_fedspace,
                    ADD INDEX idx_sectors_band (band);

-- La fascia di nascita di un NPC ne fissa la forza e il raggio d'azione. NULL
-- per chi non ha fascia: i cacciatori di taglie, che inseguono il ricercato.
ALTER TABLE npcs ADD COLUMN band TINYINT UNSIGNED NULL AFTER home_sector;

-- Dopo una razzia gli NPC delle fasce vicine lasciano stare il comandante per
-- un po': senza tregua una nave spogliata veniva razziata di nuovo al minuto
-- dopo, fino a restare senza un credito.
ALTER TABLE players ADD COLUMN tregua_npc_until DATETIME NULL AFTER protected_until;

-- Prima assegnazione, con le soglie di default (la stessa formula di
-- Fasce::ricalcola, che rifa' il conto quando le soglie cambiano).
UPDATE sectors s
  JOIN (SELECT x AS cx, y AS cy FROM sectors WHERE is_stardock = 1 ORDER BY id LIMIT 1) c
  JOIN (SELECT MAX(SQRT(POW(s2.x - c2.x, 2) + POW(s2.y - c2.y, 2))) AS rmax
          FROM sectors s2
          JOIN (SELECT x, y FROM sectors WHERE is_stardock = 1 ORDER BY id LIMIT 1) c2) m
   SET s.band = CASE
       WHEN s.is_fedspace = 1 THEN 0
       WHEN SQRT(POW(s.x - c.cx, 2) + POW(s.y - c.cy, 2)) < 0.36 * m.rmax THEN 1
       WHEN SQRT(POW(s.x - c.cx, 2) + POW(s.y - c.cy, 2)) < 0.53 * m.rmax THEN 2
       WHEN SQRT(POW(s.x - c.cx, 2) + POW(s.y - c.cy, 2)) < 0.69 * m.rmax THEN 3
       WHEN SQRT(POW(s.x - c.cx, 2) + POW(s.y - c.cy, 2)) < 0.84 * m.rmax THEN 4
       ELSE 5 END;

-- La popolazione ostile nata con le vecchie regole (Ferrengi a un salto da
-- Sol, predoni da tremila caccia accanto alla Federazione) se ne va; il clock
-- la ricostituisce in pochi minuti, ogni nave nella sua fascia. Restano i
-- cacciatori di taglie e i mercantili, che prendono la fascia in cui si trovano.
DELETE FROM npcs WHERE kind IN ('ferrengi', 'pirate') AND name <> 'Cacciatore di taglie';
UPDATE npcs n JOIN sectors s ON s.id = n.sector_id SET n.band = s.band WHERE n.kind = 'trader';

-- I pericoli ambientali permanenti finiti nella Cintura di Sol si esauriscono:
-- d'ora in poi nascono solo dalla fascia II (fasce.pericoli_da).
UPDATE sector_features sf JOIN sectors s ON s.id = sf.sector_id
   SET sf.depleted = 1
 WHERE sf.kind = 'hazard' AND sf.depleted = 0 AND s.band < 2;

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('fasce.soglie',            '0.36,0.53,0.69,0.84', 'string', '0.36,0.53,0.69,0.84'),
  ('fasce.soglie_applicate',  '0.36,0.53,0.69,0.84', 'string', ''),
  ('fasce.predoni',           '6,8,9,9,8',           'string', '6,8,9,9,8'),
  ('fasce.predoni_caccia',    '80-250,250-650,700-1800,1800-4500,4500-11000', 'string', '80-250,250-650,700-1800,1800-4500,4500-11000'),
  ('fasce.predoni_rating',    '0.7,0.85,1.0,1.2,1.4', 'string', '0.7,0.85,1.0,1.2,1.4'),
  ('fasce.ferrengi_da',       '4',                   'int',    '4'),
  ('fasce.ferrengi_caccia',   '1500-3500,2000-4500,2500-6000,3500-8000,6000-14000', 'string', '1500-3500,2000-4500,2500-6000,3500-8000,6000-14000'),
  ('fasce.crediti_npc',       '800-3000,2500-8000,6000-20000,15000-50000,40000-120000', 'string', '800-3000,2500-8000,6000-20000,15000-50000,40000-120000'),
  ('fasce.ingaggio_pct',      '15,30,45,60,75',      'string', '15,30,45,60,75'),
  ('fasce.xp_mult',           '0.5,0.75,1,1.5,2',    'string', '0.5,0.75,1,1.5,2'),
  ('fasce.commercio_pct',     '0,5,10,15,20',        'string', '0,5,10,15,20'),
  ('fasce.bottino_mult',      '0.7,0.85,1,1.3,1.7',  'string', '0.7,0.85,1,1.3,1.7'),
  ('fasce.rarita',            'civ:100,mil:25,exp:4,xeno:1,precursor:0|civ:100,mil:40,exp:10,xeno:2,precursor:0|civ:80,mil:50,exp:20,xeno:6,precursor:1|civ:50,mil:50,exp:30,xeno:12,precursor:3|civ:25,mil:45,exp:40,xeno:20,precursor:6', 'string', 'civ:100,mil:25,exp:4,xeno:1,precursor:0|civ:100,mil:40,exp:10,xeno:2,precursor:0|civ:80,mil:50,exp:20,xeno:6,precursor:1|civ:50,mil:50,exp:30,xeno:12,precursor:3|civ:25,mil:45,exp:40,xeno:20,precursor:6'),
  ('fasce.ricchezza',         '1-2,1-3,2-3,2-4,3-5', 'string', '1-2,1-3,2-3,2-4,3-5'),
  ('fasce.missioni_diff',     '8,10,12,15,18',       'string', '8,10,12,15,18'),
  ('fasce.pericoli_da',       '2',                   'int',    '2'),
  ('fasce.razzia_fino_a',     '2',                   'int',    '2'),
  ('fasce.razzia_crediti_pct','10',                  'int',    '10'),
  ('fasce.tregua_min',        '30',                  'int',    '30'),
  ('fasce.avviso_salto',      '2',                   'int',    '2')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);

-- Sostituite dalle fasce: la regione non dice piu' quanto si e' lontani.
DELETE FROM game_config WHERE ckey IN ('loot.region_bonus_deep', 'loot.region_bonus_frontier',
                                     'npc.pirate_target', 'npc.engage_chance_pct');
