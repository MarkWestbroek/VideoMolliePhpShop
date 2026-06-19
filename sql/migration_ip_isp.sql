-- ============================================================
-- Migratie: ISP / mobiel-info bij login IP's
-- ============================================================
ALTER TABLE `login_ips`
    ADD COLUMN `isp`        VARCHAR(100) DEFAULT NULL AFTER `last_seen`,
    ADD COLUMN `is_mobile`  TINYINT(1)   DEFAULT NULL AFTER `isp`;
