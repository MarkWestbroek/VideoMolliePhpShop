-- Voeg Vimeo-downloadlink + beschikbaarheidsdatum toe aan videos
ALTER TABLE `videos`
    ADD COLUMN `vimeo_download_url` VARCHAR(255) DEFAULT NULL AFTER `vimeo_id`,
    ADD COLUMN `download_after`     DATE         DEFAULT NULL AFTER `vimeo_download_url`;
