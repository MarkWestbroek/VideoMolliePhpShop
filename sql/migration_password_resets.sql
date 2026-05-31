-- ============================================================
-- Migratie: wachtwoord-reset tokens
-- Uitvoeren via Plesk > Databases > phpMyAdmin > SQL-tab
-- (Alleen nodig als je al een bestaande installatie hebt.
--  install_full.sql bevat deze tabel al.)
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)  NOT NULL COMMENT 'SHA-256 hash van het reset-token',
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pr_token` (`token`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
