-- 0053_famiglie_moduli : ogni modulo appartiene a una famiglia
--
-- Potenziare un modulo all'Officina sceglieva a caso un modello della fascia
-- superiore nella stessa *categoria*: una Stiva ausiliaria poteva diventare un
-- Braccio recuperatore, uno Scanner di densita' una Guerra elettronica. Ora un
-- modulo sale di rarita' restando nella sua famiglia (stessa funzione, valori
-- piu' alti). Le famiglie dei moduli esistenti sono assegnate qui; il catalogo
-- ampliato completa ogni linea su tutte e cinque le rarita'.

ALTER TABLE item_types ADD COLUMN family VARCHAR(32) NULL AFTER category,
                       ADD INDEX idx_item_types_family (family, rarity);

UPDATE item_types SET family = 'cannoni'       WHERE ckey IN ('w_autocannoni', 'w_railgun', 'w_plasma', 'w_disgregatore', 'w_precursore');
UPDATE item_types SET family = 'scudi'         WHERE ckey IN ('d_piastre', 'd_barriera');
UPDATE item_types SET family = 'rigenerazione' WHERE ckey IN ('d_ondulati', 'd_egida');
UPDATE item_types SET family = 'deflettori'    WHERE ckey IN ('d_deflettore');
UPDATE item_types SET family = 'propulsori'    WHERE ckey IN ('v_bobine', 'v_transwarp', 'v_motore_xeno');
UPDATE item_types SET family = 'sensori'       WHERE ckey IN ('c_scanner_denso', 'c_oloscanner');
UPDATE item_types SET family = 'guerra_elettronica' WHERE ckey IN ('c_gew');
UPDATE item_types SET family = 'analisi'       WHERE ckey IN ('c_preveggenza', 'c_mente');
UPDATE item_types SET family = 'stive'         WHERE ckey IN ('u_stiva', 'u_nanosciame');
UPDATE item_types SET family = 'recupero'      WHERE ckey IN ('u_recuperatore', 'u_drone');
UPDATE item_types SET family = 'occultamento'  WHERE ckey IN ('u_mantello');
