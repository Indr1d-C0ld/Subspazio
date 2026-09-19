-- 0041_manopole_scollegate : via le chiavi che il codice non legge piu'
--
-- Otto chiavi di game_config non comparivano da nessuna parte nel codice.
-- Guardandole una per una, nessuna nominava una funzione inesistente: sei
-- erano state SUPERATE da un meccanismo diverso — e in cinque casi su sei
-- piu' preciso — e due erano manopole vere, mai collegate al numero che gia'
-- esisteva scritto a mano.
--
-- Restare in tabella e' solo un invito a girarle: nel pannello sembrano
-- comandare qualcosa e non rispondono. Si tolgono, dicendo per ciascuna chi
-- ha preso il suo posto.
--
--   crew.mission_refresh_hours -> spezzata in crew.mission_cooldown_min e
--                                 crew.mission_expire_hours, entrambe lette
--   season.regen_universe      -> la casella "rigenera anche l'universo" nel
--                                 modulo di chiusura stagione, che arriva a
--                                 Season::close() come parametro
--   craft.industry_last_run    -> la colonna planets.last_industry_at: ogni
--                                 pianeta matura per conto suo invece che a
--                                 un battito globale
--   map.node_radius            -> residuo della vecchia mappa 2D. Nella mappa
--                                 3D attuale il raggio e' derivato dal tipo di
--                                 nodo e scalato dalla prospettiva
--   game.name                  -> app.name, dal file di configurazione
--   game.tagline               -> la stessa frase in manifest.webmanifest
--
-- NON si tocca universe.region_bands: le tre fasce esistono davvero, ma il
-- nome non descrive cio' che il generatore fa (produce una regione profonda
-- ogni tre, non "tre bande"). Collegarla significherebbe prima decidere cosa
-- debba voler dire, ed e' una scelta di disegno, non una correzione.
--
-- ranks.good_threshold invece resta e viene finalmente collegata: vedi
-- Ranks::alignmentBands().
DELETE FROM game_config WHERE ckey IN (
  'crew.mission_refresh_hours',
  'season.regen_universe',
  'craft.industry_last_run',
  'map.node_radius',
  'game.name',
  'game.tagline'
);

-- La soglia dei buoni ora comanda l'etichetta: le si da' un default
-- esplicito, cosi' il pulsante di ripristino del pannello sa dove tornare.
UPDATE game_config SET default_value = '100'
 WHERE ckey = 'ranks.good_threshold' AND (default_value IS NULL OR default_value = '');
UPDATE game_config SET default_value = '-100'
 WHERE ckey = 'ranks.evil_threshold' AND (default_value IS NULL OR default_value = '');
