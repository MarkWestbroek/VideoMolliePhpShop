-- Voeg streaming_type toe om onderscheid te maken tussen lokale stream en Vimeo
ALTER TABLE users ADD COLUMN streaming_type ENUM('local','vimeo') DEFAULT NULL AFTER streaming_at;
