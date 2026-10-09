-- 0063_traguardi_insieme : correzione del quarto audit (9 ottobre 2026)
--
-- «Tutti e dieci i progetti» e «tutte e tre le collezioni» leggevano contatori
-- di carriera, e la ripartenza totale svuota progetti e collezioni: bastava
-- completare la stessa collezione in tre stagioni. Ora contano quelli posseduti
-- insieme (il massimo raggiunto).
UPDATE achievements SET contatore = 'progetti_insieme' WHERE ckey = 'progetti_oro';
UPDATE achievements SET contatore = 'collezioni_insieme' WHERE ckey = 'collezione_3';
