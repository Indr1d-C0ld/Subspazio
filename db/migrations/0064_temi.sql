-- 0064_temi : temi grafici a scelta del comandante
--
-- La scelta segue l'account su ogni dispositivo. NULL = tema di base
-- («console»). Per chi non ha fatto l'accesso vale un cookie.
ALTER TABLE users ADD COLUMN IF NOT EXISTS tema VARCHAR(20) NULL AFTER session_epoch;
