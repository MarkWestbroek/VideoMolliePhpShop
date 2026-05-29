-- ============================================================
-- Migratie: staffel (trapsgewijze) prijzen
-- Uitvoeren via Plesk > Databases > phpMyAdmin > SQL-tab
-- ============================================================

-- 1. Staffel-types (bijv. "Standaard", "Premium")
CREATE TABLE IF NOT EXISTS `staffels` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `naam`        VARCHAR(100) NOT NULL,
    `beschrijving` TEXT DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Prijstrappen per staffel
--    aantal_van/aantal_tot = het hoeveelste aankoop van video's in DEZELFDE staffel
--    Voorbeeld: staffel "Standaard"
--      trap 1: aantal_van=1, aantal_tot=1,  prijs=10.00  (1e video €10)
--      trap 2: aantal_van=2, aantal_tot=3,  prijs=8.75   (2e en 3e video €8.75 stuk)
--      trap 3: aantal_van=4, aantal_tot=999,prijs=7.50   (4e video en verder €7.50)
CREATE TABLE IF NOT EXISTS `staffelprijzen` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `staffel_id`  INT UNSIGNED   NOT NULL,
    `aantal_van`  SMALLINT UNSIGNED NOT NULL COMMENT 'Vanaf het hoeveelste aankoop (1-based)',
    `aantal_tot`  SMALLINT UNSIGNED NOT NULL COMMENT 'Tot en met het hoeveelste aankoop',
    `prijs`       DECIMAL(10,2)  NOT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staffel_van` (`staffel_id`, `aantal_van`),
    CONSTRAINT `fk_sp_staffel` FOREIGN KEY (`staffel_id`) REFERENCES `staffels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Koppel videos aan een staffel (price blijft als fallback voor vaste prijs zonder staffel)
ALTER TABLE `videos`
    ADD COLUMN `staffel_id` INT UNSIGNED DEFAULT NULL AFTER `price`,
    ADD CONSTRAINT `fk_video_staffel` FOREIGN KEY (`staffel_id`) REFERENCES `staffels`(`id`) ON DELETE SET NULL;
