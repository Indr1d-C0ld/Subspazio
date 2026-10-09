-- 0068_comunicato_ripristino : il comunicato dopo il sesto audit (9 ottobre 2026)
--
-- Il comunicato del 0066 diceva persi i messaggi radio e gli avvisi dal 7
-- ottobre. Il bilancio rifatto sul backup delle 10:35 dice altro: nessun
-- avviso perso, e gli 11 messaggi radio sono stati rimessi in onda dal
-- backup (audit «radio.restore»). Le correzioni di bordo raccontano anche
-- quelle del sesto audit, e «bottino» diventa «moduli»: i crediti a bordo di
-- una nave di soccorso si perdono come sempre.
--
-- Si cambia solo se il testo e' ancora quello del 0066: un comunicato
-- riscritto dal pannello resta com'e'.
UPDATE game_config SET cvalue = 'Comunicato della Federazione: il 9 ottobre, durante una manutenzione, la Stagione 3 è stata chiusa per errore e riaperta poco dopo. Navi, crediti e progressi sono intatti, e i messaggi radio dal 7 ottobre sono stati recuperati dall''archivio. Ci scusiamo con tutti i comandanti. | Correzioni di bordo: abbattere una nave di soccorso non vale esperienza né moduli, nemmeno quando è lei ad attaccare (ma l''omicidio di un onesto la Federazione lo conta), un attacco arrivato tardi, anche a un mercantile, viene respinto senza costi, le abilità degli ufficiali si usano una volta per ricarica e un modulo di stiva o d''hangar guasto non blocca più l''officina.'
 WHERE ckey = 'fednews.comunicato' AND cvalue = 'Comunicato della Federazione: il 9 ottobre, durante una manutenzione, la Stagione 3 è stata chiusa per errore e riaperta poco dopo. Navi, crediti e progressi sono intatti, ma i messaggi radio e gli avvisi arrivati dal 7 ottobre sono andati perduti. Ci scusiamo con tutti i comandanti. | Correzioni di bordo: abbattere una nave di soccorso non vale esperienza né bottino nemmeno quando è lei ad attaccare, un attacco respinto all''ultimo istante non consuma più il Nucleo in sovraccarico né spegne l''occultamento, un hangar guasto si smonta di nuovo, e nei temi grafici gli avvisi riprendono il colore del loro tipo.';
