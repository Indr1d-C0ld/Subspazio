-- 0024_notify : notifica e-mail all'amministratore per le richieste di
--   iscrizione. La coda e' implicita: users.reg_notified_at marca chi e'
--   gia' stato segnalato. L'invio avviene dal tick (non nel percorso della
--   richiesta di registrazione), via SMTP Brevo (config 'mail'/'notify').

ALTER TABLE users ADD COLUMN IF NOT EXISTS reg_notified_at DATETIME NULL;
