-- 0051_reputazione_commercio : la reputazione dal commercio segue il valore
--
-- Ogni scambio valeva +1 reputazione, qualunque la cifra: cento acquisti da
-- un'unita' portavano una fazione ad «alleata», e uno scambio da 12 crediti
-- toglieva il bando della Federazione. Ora si guadagna faction.trade_gain ogni
-- trade_rep_step crediti scambiati (in media), al massimo trade_gain_max per
-- singolo scambio.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('faction.trade_rep_step', '5000', 'int', '5000'),
  ('faction.trade_gain_max', '3',    'int', '3')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
