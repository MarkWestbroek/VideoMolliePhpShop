-- ============================================================
-- Migratie: e-mailverificatie toevoegen aan bestaande gebruikers
-- ============================================================
-- Uitvoeren via: Plesk > Databases > phpMyAdmin > SQL-tabblad
-- 
-- Bestaande gebruikers worden automatisch als geverifieerd gemarkeerd
-- zodat ze niet opeens geblokkeerd worden.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `email_verified_at` DATETIME      DEFAULT NULL AFTER `last_activity`,
    ADD COLUMN `verification_token` VARCHAR(64)  DEFAULT NULL AFTER `email_verified_at`,
    ADD INDEX `idx_verification_token` (`verification_token`);

-- Bestaande gebruikers direct als geverifieerd markeren
UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL;
