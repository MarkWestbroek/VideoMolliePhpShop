-- ============================================================
-- Migratie: events + toegangscodes (privacy / event-bundels)
-- Uitvoeren via Plesk > Databases > phpMyAdmin > SQL-tab
-- ============================================================
--
-- Doel:
--   * Video's bundelen per event (een event hoort bij een organisator).
--   * Bezoekers van het event krijgen een toegangscode.
--   * Alleen gebruikers die de code hebben ingevoerd zien de
--     bijbehorende event-video's en kunnen ze kopen.
--   * Video's zonder event (event_id = NULL) blijven openbaar zichtbaar
--     voor alle ingelogde gebruikers (achterwaartse compatibiliteit).
-- ============================================================

-- 1. Events (elk event hoort bij een organisator en heeft een unieke code)
CREATE TABLE IF NOT EXISTS `events` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `naam`         VARCHAR(150) NOT NULL,
    `organisator`  VARCHAR(150) NOT NULL,
    `beschrijving` TEXT         DEFAULT NULL,
    `toegangscode` VARCHAR(64)  NOT NULL,
    `active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_code` (`toegangscode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Koppel video's aan een event (NULL = openbaar)
ALTER TABLE `videos`
    ADD COLUMN `event_id` INT UNSIGNED DEFAULT NULL AFTER `staffel_id`,
    ADD CONSTRAINT `fk_video_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL;

-- 3. Welke gebruiker heeft toegang tot welk event (na invoeren code)
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
