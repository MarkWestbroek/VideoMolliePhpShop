-- ============================================================
-- Migratie: last_activity kolom in users
-- Uitvoeren via Plesk > Databases > phpMyAdmin > SQL-tab
-- ============================================================
ALTER TABLE `users`
    ADD COLUMN `last_activity` DATETIME DEFAULT NULL
        COMMENT 'Bijgewerkt bij elke request (max 1x per minuut)'
        AFTER `is_admin`;
