-- 0027_limpet : completa le mine Limpet (Tema B della roadmap post-beta).
--   Lo schema esisteva gia' dal 0007 (ship_limpets, sector_mines.type='limpet',
--   ships.mines_limpet) ma nessuno lo leggeva. Qui si aggiungono solo le
--   manopole di bilanciamento; la logica sta in src/Game/Limpet.php.
--
--   limpet.ttl_hours   : ore di aggancio prima del distacco automatico (tick)
--   limpet.max_tracked : quante prede puo' seguire contemporaneamente un giocatore
--   limpet.field_cap   : massimo di mine Limpet in un campo (per proprietario/settore)

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('limpet.ttl_hours',   '12', 'int'),
  ('limpet.max_tracked', '15', 'int'),
  ('limpet.field_cap',   '50', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue
