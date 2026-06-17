-- ============================================================
-- Migratie: login IP-tracking toevoegen
-- ============================================================
-- Uitvoeren via: Plesk > Databases > phpMyAdmin > SQL-tabblad
-- ============================================================

CREATE TABLE IF NOT EXISTS `login_ips` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED      NOT NULL,
    `ip_address`  VARCHAR(45)       NOT NULL COMMENT 'IPv4 of IPv6',
    `first_seen`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_ip` (`user_id`, `ip_address`),
    CONSTRAINT `fk_lips_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
