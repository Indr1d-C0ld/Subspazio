-- 0066_comunicati : comunicati della Federazione nel notiziario
--
-- Il notiziario si componeva solo dallo stato del gioco: non c'era modo di
-- dire ai comandanti qualcosa che i numeri non raccontano (un disservizio,
-- una regola cambiata). fednews.comunicato e' testo libero, piu' comunicati
-- separati da « | », che apre il bollettino fino a fednews.comunicato_fino
-- (AAAA-MM-GG HH:MM, vuoto = nessuna scadenza). Si cambia dal pannello.
--
-- Il primo racconta la chiusura per errore della Stagione 3 del 9 ottobre e
-- le correzioni del quinto audit che i comandanti possono notare.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('fednews.comunicato', 'Comunicato della Federazione: il 9 ottobre, durante una manutenzione, la Stagione 3 è stata chiusa per errore e riaperta poco dopo. Navi, crediti e progressi sono intatti, ma i messaggi radio e gli avvisi arrivati dal 7 ottobre sono andati perduti. Ci scusiamo con tutti i comandanti. | Correzioni di bordo: abbattere una nave di soccorso non vale esperienza né bottino nemmeno quando è lei ad attaccare, un attacco respinto all''ultimo istante non consuma più il Nucleo in sovraccarico né spegne l''occultamento, un hangar guasto si smonta di nuovo, e nei temi grafici gli avvisi riprendono il colore del loro tipo.', 'string', ''),
  ('fednews.comunicato_fino', '2026-10-16 23:59', 'string', '')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
