-- 0033_crest_marks : la marca di flotta ora puo' essere anche una sagoma di
--   nave (valore "nave:<ship_types.ckey>", vedi App\Game\Identity::SHIP_MARKS
--   e partials/ship_sprite.php). Allarga la colonna crest per stare comoda.
ALTER TABLE players MODIFY COLUMN crest VARCHAR(32) NULL;
ALTER TABLE corporations MODIFY COLUMN crest VARCHAR(32) NULL;
