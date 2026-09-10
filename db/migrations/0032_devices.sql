-- 0032_devices : dà finalmente una meccanica a due dispositivi gia' acquistabili
--   al Cantiere ma inerti — l'Occultamento (ships.dev_cloak, 35k) e il Transwarp
--   (ships.dev_transwarp, 28k).
--
--   Occultamento: stato ON/OFF (ships.cloaked). Da cloaked sei invisibile agli
--   altri giocatori e agli NPC, li superi senza ingaggio; NON in Fedspace, NON
--   dalle mine, NON dallo StarDock; +cloak.warp_turn_penalty turno per warp;
--   attaccare/dispiegare/attraccare/entrare in Fedspace ti smaschera. Uno
--   scanner olografico vede i cloaked nel proprio settore. Le mine Limpet
--   bucano l'occultamento (tracking invariato).
--   Transwarp: salto diretto a un settore gia' esplorato, ignorando le rotte,
--   a transwarp.turn_cost turni fissi. Ti smaschera. Logica: src/Game/Cloak.php
--   e Navigation::transwarp().

ALTER TABLE ships ADD COLUMN IF NOT EXISTS cloaked TINYINT NOT NULL DEFAULT 0;

INSERT INTO game_config (ckey, cvalue, ctype) VALUES
  ('cloak.warp_turn_penalty',  '1', 'int'),
  ('cloak.fedspace_forbidden', '1', 'bool'),
  ('transwarp.turn_cost',      '5', 'int')
  ON DUPLICATE KEY UPDATE cvalue = cvalue
