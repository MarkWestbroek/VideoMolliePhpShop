-- ============================================================
-- Schema voor video streaming site met Mollie betalingen
-- Uitvoeren via Plesk > Databases > phpMyAdmin
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `is_admin`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `videos` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL,
    `description` TEXT,
    `price`       DECIMAL(10,2)   NOT NULL,
    `filename`    VARCHAR(255)    NOT NULL COMMENT 'Alleen de bestandsnaam, bijv. les1.mp4',
    `thumbnail`   VARCHAR(255)    DEFAULT NULL COMMENT 'Relatief pad vanuit httpdocs/assets/thumbs/',
    `active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
    `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`            INT UNSIGNED    NOT NULL,
    `video_id`           INT UNSIGNED    NOT NULL,
    `mollie_payment_id`  VARCHAR(255)    DEFAULT NULL,
    `status`             ENUM('open','pending','paid','failed','expired','canceled') NOT NULL DEFAULT 'open',
    `amount`             DECIMAL(10,2)   NOT NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at`            DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_video` (`user_id`, `video_id`),
    KEY `idx_mollie_id` (`mollie_payment_id`),
    CONSTRAINT `fk_purchase_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purchase_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
