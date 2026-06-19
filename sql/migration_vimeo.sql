-- ============================================================
-- Migratie: Vimeo ondersteuning
-- ============================================================
ALTER TABLE `videos`
    ADD COLUMN `vimeo_id` VARCHAR(50) DEFAULT NULL AFTER `filename`;
