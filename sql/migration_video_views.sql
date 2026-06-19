-- ============================================================
-- Migratie: video weergaven-tracking (video_views)
-- ============================================================
-- Uitvoeren via: Plesk > Databases > phpMyAdmin > SQL-tabblad
-- ============================================================

CREATE TABLE IF NOT EXISTS `video_views` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `video_id`   INT UNSIGNED NOT NULL,
    `watched_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_time` (`user_id`, `watched_at`),
    KEY `idx_video_time` (`video_id`, `watched_at`),
    CONSTRAINT `fk_view_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_view_video` FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
