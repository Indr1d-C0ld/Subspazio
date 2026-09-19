-- 0037_coda_posta : la posta non si perde piu' per strada
--
-- Portata da Atlantik, che l'aveva costruita dopo che un suo audit aveva
-- scoperto messaggi persi quando l'SMTP non rispondeva. Qui serve per la
-- stessa ragione, e con piu' urgenza: dalla prossima migrazione la verifica
-- dell'indirizzo diventa l'unica porta d'ingresso al gioco, e un'e-mail
-- perduta e' un giocatore che non entra mai piu'.
--
-- Ogni messaggio entra in coda PRIMA di essere tentato: se il processo muore
-- a meta', il messaggio resta. Se l'invio fallisce si riprova con attesa
-- crescente (1, 5, 15, 60, 180, 360 minuti).

CREATE TABLE IF NOT EXISTS mail_queue (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  destinatario  VARCHAR(190) NOT NULL,
  oggetto       VARCHAR(255) NOT NULL,
  corpo         MEDIUMTEXT NOT NULL,
  genere        VARCHAR(32) NOT NULL DEFAULT 'generico',
  priorita      TINYINT NOT NULL DEFAULT 5,      -- 1 = prima di tutto (verifica), 9 = ultima
  tentativi     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  prossimo_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  inviato_at    DATETIME NULL,
  rinunciato_at DATETIME NULL,
  ultimo_errore VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_coda_da_fare (inviato_at, rinunciato_at, prossimo_at, priorita),
  KEY idx_coda_inviati (inviato_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Il tetto e' volutamente piu' basso di quello di Atlantik (280): i due giochi
-- condividono lo stesso account Brevo, che nel piano gratuito concede 300
-- invii al giorno, e ciascuno conta soltanto i propri. Con 140 a testa la
-- somma resta sotto il limite anche nel giorno peggiore.
INSERT INTO game_config (ckey, cvalue, ctype, default_value) VALUES
  ('mail.max_tentativi', '6',   'int', '6'),
  ('mail.tetto_24h',     '140', 'int', '140'),
  ('mail.per_battito',   '5',   'int', '5'),
  ('mail.keep_days',     '30',  'int', '30')
ON DUPLICATE KEY UPDATE default_value = VALUES(default_value);
