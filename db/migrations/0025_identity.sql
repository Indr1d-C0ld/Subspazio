-- 0025_identity : Slice #1 della roadmap post-beta — identità del comandante.
--   Colore d'accento, stemma di flotta (chiave da un set curato), motto,
--   registro della nave. Il "titolo" non si salva: si deriva a runtime da
--   grado + tier di fazione (src/Game/Identity.php).

ALTER TABLE players ADD COLUMN IF NOT EXISTS color VARCHAR(7)  NULL;
ALTER TABLE players ADD COLUMN IF NOT EXISTS crest VARCHAR(24) NULL;
ALTER TABLE players ADD COLUMN IF NOT EXISTS motto VARCHAR(80) NULL;

ALTER TABLE ships   ADD COLUMN IF NOT EXISTS registry VARCHAR(16) NULL;

ALTER TABLE corporations ADD COLUMN IF NOT EXISTS color VARCHAR(7)  NULL;
ALTER TABLE corporations ADD COLUMN IF NOT EXISTS crest VARCHAR(24) NULL;
