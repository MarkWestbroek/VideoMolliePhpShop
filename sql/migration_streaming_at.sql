-- Voeg streaming_at kolom toe aan users voor nauwkeurige stream-detectie
-- streaming_at wordt alleen bijgewerkt door stream.php (echte video bytes), niet door gewone paginabezoeken
ALTER TABLE users ADD COLUMN streaming_at DATETIME NULL DEFAULT NULL AFTER last_activity;
