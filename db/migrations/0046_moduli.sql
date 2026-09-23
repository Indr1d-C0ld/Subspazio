-- 0046_moduli : i moduli ricordano il guasto, e arrivano da ovunque li si prometta
--
-- 1) Un modulo guasto smontato e rimontato tornava in linea: l'inventario non
--    aveva una colonna per il guasto, e il rimontaggio lo inseriva come nuovo.
--    Tre moduli guasti, preventivo di riparazione 1.350 cr — oppure "Rimuovi" e
--    "Installa", gratis. Lo stesso accadeva cambiando nave. Ora il guasto
--    segue il modulo in inventario e torna con lui.
ALTER TABLE player_items ADD COLUMN IF NOT EXISTS broken_at DATETIME NULL AFTER rolled;

-- 2) Anomalie risolte e incontri promettono un modulo, che Loot::grant inserisce
--    con provenienza 'anomaly' o 'encounter'. L'elenco delle provenienze non le
--    prevedeva: in modalita' stretta l'inserimento falliva, l'errore veniva
--    assorbito, e il giocatore riceveva i crediti ma mai il modulo.
ALTER TABLE player_items
  MODIFY COLUMN source ENUM('npc','pvp','port','planet','wreck','mission','shop','anomaly','encounter') NOT NULL DEFAULT 'npc';
