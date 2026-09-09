-- 0026_media : Slice #1b della roadmap post-beta — avatar del comandante e
--   logo di flotta caricati, con coda di approvazione admin (stesso schema
--   mentale della coda iscrizioni: stato pending -> approved | rejected).
--   I file vivono in storage/uploads/ (fuori dal web), serviti da MediaController.

CREATE TABLE IF NOT EXISTS media_assets (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_type   ENUM('player','corp') NOT NULL DEFAULT 'player',
    owner_id     INT UNSIGNED NOT NULL,
    kind         ENUM('avatar','logo') NOT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    mime         VARCHAR(32) NOT NULL,
    ext          VARCHAR(8) NOT NULL,
    bytes        INT UNSIGNED NOT NULL DEFAULT 0,
    width        SMALLINT UNSIGNED NULL,
    height       SMALLINT UNSIGNED NULL,
    sha1         CHAR(40) NOT NULL,
    path         VARCHAR(255) NOT NULL,
    review_note  VARCHAR(255) NULL,
    reviewed_by  INT UNSIGNED NULL,
    reviewed_at  DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY owner_idx (owner_type, owner_id, kind, status),
    KEY status_idx (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
