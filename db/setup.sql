-- SubSpazio — provisioning del database.
-- Eseguire UNA VOLTA come utente MariaDB con privilegi di amministrazione:
--
--   sudo mariadb < db/setup.sql
--   -- oppure:  mysql -u root -p < db/setup.sql
--
-- IMPORTANTE: sostituisci CAMBIAMI qui sotto con una password robusta e
-- usa LA STESSA in config (chiave db.pass) — di default il file cercato e'
-- /etc/subspazio/config.php oppure /data/subspazio-config/config.php,
-- FUORI dal DocumentRoot. Vedi config/config.example.php.

CREATE DATABASE IF NOT EXISTS tw_subspazio
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'tw_subspazio'@'localhost'
  IDENTIFIED BY 'CAMBIAMI';
CREATE USER IF NOT EXISTS 'tw_subspazio'@'127.0.0.1'
  IDENTIFIED BY 'CAMBIAMI';

GRANT ALL PRIVILEGES ON tw_subspazio.* TO 'tw_subspazio'@'localhost';
GRANT ALL PRIVILEGES ON tw_subspazio.* TO 'tw_subspazio'@'127.0.0.1';

FLUSH PRIVILEGES;
