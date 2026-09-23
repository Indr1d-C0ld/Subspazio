-- 0048_feriti : i feriti guariscono all'ora che la scheda mostra
--
-- Nessuno rimetteva in servizio gli ufficiali feriti: la scheda mostrava
-- «ferito · fino alle …», ma restavano feriti per sempre salvo pagare
-- l'infermeria o usare il Triage. Ora un lavoro del clock (crew_heal) li
-- guarisce a ready_at. I sopravvissuti recuperati dai relitti nascevano feriti
-- senza alcuna ora: qui ne ricevono una, come ogni altro ferito.
UPDATE officers SET ready_at = DATE_ADD(NOW(), INTERVAL 6 HOUR)
 WHERE status = 'injured' AND ready_at IS NULL;
