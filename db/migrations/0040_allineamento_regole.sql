-- 0040_allineamento_regole : la configurazione torna a dire la verita'
--
-- Tre disallineamenti trovati confrontando ogni chiave di game_config con il
-- codice che la legge.
--
-- 1) registration.open valeva ancora 'approval', vocabolario di quando era
--    l'amministratore ad ammettere gli iscritti. Da 0038 l'account si
--    autovalida: i modi sono 'verify' (aperto, con conferma dell'indirizzo)
--    e 'closed'.
--
-- 2) events.interval_min e events.chance_pct avevano un default_value che
--    NON e' mai stato una scelta: le migrazioni 0012 e 0013 fotografarono
--    `default_value = cvalue` per tutte le chiavi che ne erano prive, e in
--    quel momento gli eventi erano tarati a 90 minuti / 100%. La taratura
--    voluta e' quella di 0009 (240 / 40), la stessa che il codice usa come
--    ripiego. Il pulsante di ripristino nel pannello riportava quindi a una
--    taratura abbandonata: eventi quasi tre volte piu' frequenti e sempre
--    certi.
UPDATE game_config SET cvalue = 'verify'
 WHERE ckey = 'registration.open' AND cvalue NOT IN ('verify', 'closed');

UPDATE game_config SET default_value = '240' WHERE ckey = 'events.interval_min';
UPDATE game_config SET default_value = '40'  WHERE ckey = 'events.chance_pct';

-- 3) chiavi lette dal codice ma assenti dalla tabella: l'amministratore non
--    poteva regolarle dal pannello, pur essendo numeri di bilanciamento.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('economy.haggle.exp_factor', '0.00002', 'float', '0.00002')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);

-- Anche il default del modo di iscrizione portava il vecchio vocabolario:
-- il pulsante di ripristino nel pannello avrebbe riscritto 'approval'.
UPDATE game_config SET default_value = 'verify'
 WHERE ckey = 'registration.open' AND default_value NOT IN ('verify', 'closed');
