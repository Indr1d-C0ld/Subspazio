-- 0060_occultamento : l'occultamento ha un prezzo
--
-- Comprato una volta (35.000 cr), l'occultamento si accendeva gratis e durava
-- per sempre, al costo di un turno in piu' a salto: invisibili a NPC, caccia
-- schierati e pattuglie, si commerciava nei porti dell'Orlo col premio del 20%
-- senza alcun rischio. Ora:
--   - ogni interazione col settore (porto, mercato nero, pianeti, relitti,
--     depositi, giacimenti, anomalie, scansione) fa cadere l'occultamento;
--   - ogni aggancio ha una probabilita' di scoprire la nave, che cresce con la
--     fascia e contro pattuglie ed elite; i modelli rari del dispositivo la
--     riducono;
--   - il dispositivo ha una riserva di energia: ogni salto occultato consuma
--     una carica, che si ricarica col tempo; a riserva vuota la nave riappare.

ALTER TABLE ships ADD COLUMN cloak_carica TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER cloaked,
                  ADD COLUMN cloak_carica_at DATETIME NULL AFTER cloak_carica;

-- i modelli rari del dispositivo sfuggono meglio ai sensori
UPDATE item_types SET effects = '{"cloak":1,"evade_pct":10,"cloak_stealth_pct":15}' WHERE ckey = 'u_mantello_xeno';
UPDATE item_types SET effects = '{"cloak":1,"evade_pct":18,"cloak_stealth_pct":30}' WHERE ckey = 'u_velo';

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('cloak.carica_max',        '8',              'int',    '8'),
  ('cloak.ricarica_min',      '10',             'int',    '10'),
  ('cloak.rilevamento_pct',   '5,10,20,30,40',  'string', '5,10,20,30,40'),
  ('cloak.rilevamento_vigili','20',             'int',    '20')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
