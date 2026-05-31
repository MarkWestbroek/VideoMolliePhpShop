-- ============================================================
-- Volledig installatiescript — HB Foto & Video
-- Bevat alle tabellen in de juiste volgorde (incl. staffels en events).
-- Uitvoeren via: Plesk > Databases > phpMyAdmin > SQL-tabblad
--
-- Veilig om op een lege database uit te voeren.
-- Bestaande tabellen worden NIET overschreven (IF NOT EXISTS).
-- ============================================================

-- ------------------------------------------------------------
-- 1. Gebruikers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255)  NOT NULL,
    `password_hash` VARCHAR(255)  NOT NULL,
    `name`          VARCHAR(100)  NOT NULL,
    `is_admin`      TINYINT(1)    NOT NULL DEFAULT 0,
    `last_activity` DATETIME      DEFAULT NULL COMMENT 'Bijgewerkt bij elke request (max 1x per minuut)',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Staffels (trapsgewijze prijzen)
--    Een staffel bevat één of meer prijstrappen.
--    Voorbeeld: 1e video €10, 2e–3e €8,75, 4e en verder €7,50.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staffels` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `naam`         VARCHAR(100)  NOT NULL,
    `beschrijving` TEXT          DEFAULT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staffelprijzen` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `staffel_id`  INT UNSIGNED      NOT NULL,
    `aantal_van`  SMALLINT UNSIGNED NOT NULL COMMENT 'Vanaf het hoeveelste aankoop (1-based)',
    `aantal_tot`  SMALLINT UNSIGNED NOT NULL COMMENT 'Tot en met het hoeveelste aankoop (999 = onbeperkt)',
    `prijs`       DECIMAL(10,2)     NOT NULL,
    `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staffel_van` (`staffel_id`, `aantal_van`),
    CONSTRAINT `fk_sp_staffel` FOREIGN KEY (`staffel_id`) REFERENCES `staffels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Events (besloten toegang via toegangscode)
--    Bezoekers van een event krijgen een code waarmee ze de
--    bijbehorende video's kunnen zien en kopen.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `naam`         VARCHAR(150)  NOT NULL,
    `organisator`  VARCHAR(150)  NOT NULL,
    `beschrijving` TEXT          DEFAULT NULL,
    `toegangscode` VARCHAR(64)   NOT NULL,
    `active`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_code` (`toegangscode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Video's
--    staffel_id  → optioneel, bepaalt trapsgewijze prijs
--    event_id    → optioneel, maakt video besloten (alleen
--                  zichtbaar na invoeren event-toegangscode)
--    price       → vaste prijs, tevens terugval bij staffel
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `videos` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)  NOT NULL,
    `description` TEXT          DEFAULT NULL,
    `price`       DECIMAL(10,2) NOT NULL,
    `staffel_id`  INT UNSIGNED  DEFAULT NULL,
    `event_id`    INT UNSIGNED  DEFAULT NULL,
    `filename`    VARCHAR(255)  NOT NULL COMMENT 'Alleen bestandsnaam, bijv. les1.mp4',
    `thumbnail`   VARCHAR(255)  DEFAULT NULL COMMENT 'Relatief pad vanuit httpdocs/assets/thumbs/',
    `active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_video_staffel` FOREIGN KEY (`staffel_id`) REFERENCES `staffels`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_video_event`   FOREIGN KEY (`event_id`)   REFERENCES `events`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Aankopen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED  NOT NULL,
    `video_id`          INT UNSIGNED  NOT NULL,
    `mollie_payment_id` VARCHAR(255)  DEFAULT NULL,
    `status`            ENUM('open','pending','paid','failed','expired','canceled') NOT NULL DEFAULT 'open',
    `amount`            DECIMAL(10,2) NOT NULL,
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at`           DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_video` (`user_id`, `video_id`),
    KEY `idx_mollie_id` (`mollie_payment_id`),
    CONSTRAINT `fk_purchase_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_purchase_video` FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Event-toegang (welke gebruiker heeft welke code ingevoerd)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_access` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `event_id`    INT UNSIGNED NOT NULL,
    `unlocked_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_event` (`user_id`, `event_id`),
    CONSTRAINT `fk_ea_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ea_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Wachtwoord-reset tokens
-- ------------------------------------------------------------
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
