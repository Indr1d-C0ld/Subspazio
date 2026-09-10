-- 0029_subsystems : Slice B3 — guasti ai sottosistemi. Un colpo incassato in
--   combattimento (o il sovraccarico della griglia di potenza) può mettere
--   OFFLINE un modulo installato: finché è fuori uso non dà i suoi effetti.
--   Si ripara allo StarDock (a pagamento), l'Ingegnere di bordo ne rimette in
--   linea sul tick, e dopo qualche ora un modulo si ripristina da solo.
--   Logica: src/Game/Subsystems.php.

ALTER TABLE ship_modules ADD COLUMN IF NOT EXISTS broken_at DATETIME NULL;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('subsys.break_chance',        '10',  'int'),
  ('subsys.strain_chance',       '4',   'int'),
  ('subsys.repair_cost_each',    '450', 'int'),
  ('subsys.auto_repair_hours',   '18',  'int'),
  ('subsys.engineer_fix_chance', '25',  'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue
