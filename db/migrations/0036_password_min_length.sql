-- 0036_password_min_length : la lunghezza minima delle password diventa una
--   manopola invece di un numero scritto in cinque punti diversi
--   (registrazione, due comandi CLI, etichetta e minlength del form).
--
-- Valore corrente 9 su richiesta dell'autore; default_value resta 10, che e'
-- il valore consigliato e quello a cui torna il pulsante "ripristina" del
-- pannello. App\Auth\Auth::minPasswordLength() applica comunque un pavimento
-- a 6, cosi' un refuso in configurazione non puo' azzerare il controllo.

INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('security.password_min_length', '9', 'int', '10')
ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue), default_value = VALUES(default_value);
