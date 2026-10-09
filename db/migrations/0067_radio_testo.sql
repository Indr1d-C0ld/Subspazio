-- 0067_radio_testo : correzione del sesto audit (9 ottobre 2026)
--
-- Il notiziario va in onda anche sulla Radio (canale fedcomm), e da quando ha
-- i comunicati e gli avvisi di servizio supera i 500 caratteri della colonna:
-- con STRICT_TRANS_TABLES l'invio delle 21:30 sarebbe fallito, lasciando il
-- bollettino in archivio ma muto in radio, e la guardia delle 24 ore non
-- l'avrebbe ritentato. I messaggi dei comandanti restano tagliati dal codice
-- (radio.body_max); la colonna regge ora anche il notiziario intero.
ALTER TABLE messages MODIFY body TEXT NOT NULL;
