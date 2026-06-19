-- ============================================================
-- Migratie: test-video vlag toevoegen
-- ============================================================
ALTER TABLE `videos`
    ADD COLUMN `is_test` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`;
