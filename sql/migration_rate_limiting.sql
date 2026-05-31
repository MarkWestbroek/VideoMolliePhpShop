-- ============================================================
-- Migratie: login_attempts tabel (rate limiting)
-- Uitvoeren via Plesk > Databases > phpMyAdmin > SQL-tab
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(45)  NOT NULL COMMENT 'IPv4 of IPv6 adres',
    `action`     VARCHAR(30)  NOT NULL DEFAULT 'login',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_action_time` (`ip`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
