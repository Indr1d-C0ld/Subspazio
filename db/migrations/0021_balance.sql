-- 0021_balance : passata di bilanciamento sui sistemi delle Fasi 7-11.
--   Obiettivo: i moduli si guadagnano, i Cristalli non sono banali, il
--   minerale non inonda l'economia, gli empori di fazione sono una scelta
--   vera rispetto alla produzione, la reputazione va mantenuta.
--   Solo UPDATE su valori fissi -> idempotente, si applica anche in live.

-- --- LOOT: drop più rari, meno "fortuna evento", meno materiale --------
UPDATE game_config SET cvalue = '0.22' WHERE ckey = 'loot.drop_chance_npc';
UPDATE game_config SET cvalue = '0.40' WHERE ckey = 'loot.drop_chance_pvp';
UPDATE game_config SET cvalue = '1.2'  WHERE ckey = 'loot.event_bounty_luck';
UPDATE game_config SET cvalue = '0.05' WHERE ckey = 'loot.double_drop_pct';
UPDATE game_config SET cvalue = '4.5'  WHERE ckey = 'loot.salvage_per_rating';
UPDATE game_config SET cvalue = 'civ:25,mil:75,exp:220,xeno:600' WHERE ckey = 'loot.upgrade_cost_salvage';

-- --- MINING: Cristalli meno frequenti, meno minerale per colpo --------
UPDATE game_config SET cvalue = '30' WHERE ckey = 'mine.crystal_chance_pct';
UPDATE game_config SET cvalue = '3'  WHERE ckey = 'mine.crystal_per_hit_max';
UPDATE game_config SET cvalue = '6'  WHERE ckey = 'mine.ore_per_pass_base';

-- --- RAFFINERIA / INDUSTRIA -------------------------------------------
UPDATE game_config SET cvalue = '3'  WHERE ckey = 'craft.refine_equ_per_component';
UPDATE game_config SET cvalue = '36' WHERE ckey = 'craft.planet_component_per_day';
INSERT INTO game_config (ckey, cvalue, ctype) VALUES ('craft.refine_units_per_turn', '12', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue;

-- --- SCANSIONE: meno moduli dai relitti ------------------------------
UPDATE game_config SET cvalue = '28' WHERE ckey = 'scan.wreck_module_pct';
UPDATE game_config SET cvalue = '48' WHERE ckey = 'scan.wreck_module_deep_pct';

-- --- FAZIONI: rep più lenta da guadagnare, decade più in fretta ------
UPDATE game_config SET cvalue = '5' WHERE ckey = 'faction.kill_gain';
UPDATE game_config SET cvalue = '3' WHERE ckey = 'faction.decay_per_day';

-- --- EMPORI DI FAZIONE: prezzi allineati alla produzione -------------
UPDATE faction_offers SET price = 3200  WHERE ref = 'd_ondulati'   AND faction = 'fed';
UPDATE faction_offers SET price = 4800  WHERE ref = 'c_oloscanner' AND faction = 'fed';
UPDATE faction_offers SET price = 3600  WHERE ref = 'u_recuperatore' AND faction = 'ferrengi';
UPDATE faction_offers SET price = 14000 WHERE ref = 'u_drone'      AND faction = 'ferrengi';
UPDATE faction_offers SET price = 4200  WHERE ref = 'w_railgun'    AND faction = 'hegemony';
UPDATE faction_offers SET price = 13000 WHERE ref = 'w_plasma'     AND faction = 'hegemony';
UPDATE faction_offers SET price = 12000 WHERE ref = 'd_deflettore' AND faction = 'frontier';
UPDATE faction_offers SET price = 28000 WHERE ref = 'c_preveggenza' AND faction = 'frontier';
