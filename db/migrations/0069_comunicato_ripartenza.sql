-- 0069_comunicato_ripartenza : il comunicato della nuova revisione (13 ottobre 2026)
--
-- Il 13 ottobre il gioco riparte da zero (Season::ripartenza, dalla console
-- con revisione:ripartenza --conferma): galassia nuova, Stagione 1, storia
-- azzerata. Il comunicato sull'incidente della Stagione 3 non ha piu' senso:
-- lo sostituisce questo, per una settimana. Niente punti e virgola nei testi:
-- il migratore divide le istruzioni su quelli.
UPDATE game_config SET cvalue = 'Comunicato della Federazione: il 13 ottobre SubSpazio riparte da zero con una nuova revisione. Galassia nuova e Stagione 1, tutti allo StarDock con la nave iniziale e 1.000 crediti: traguardi, statistiche, albo d''oro e registri sono stati azzerati, restano nome e aspetto del comandante. | Novità di bordo: sei temi grafici a scelta dal piè di pagina, taglie pagate da chi le ha sulla testa e confiscate a bordo e in banca, capsule e navi di soccorso che non valgono premi, attacchi arrivati tardi respinti senza costi, abilità degli ufficiali una volta per ricarica, moduli guasti che si riparano anche da soli.'
 WHERE ckey = 'fednews.comunicato';
UPDATE game_config SET cvalue = '2026-10-20 23:59' WHERE ckey = 'fednews.comunicato_fino';
