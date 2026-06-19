<?php
declare(strict_types=1);

/**
 * heartbeat.php — Houdt streaming_at actief terwijl gebruiker een video kijkt.
 * Wordt elke ~30s aangeroepen vanuit watch.php zolang de video speelt.
 * Werkt voor zowel Vimeo (iframe) als lokale video's (stream.php).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$type = ($_GET['type'] ?? '') === 'vimeo' ? 'vimeo' : 'local';

try {
    db()->prepare('UPDATE users SET streaming_at = NOW(), streaming_type = ? WHERE id = ?')->execute([$type, $userId]);
} catch (\PDOException $e) {
    // streaming_at kolom bestaat nog niet — negeer
}

// Geen output nodig
http_response_code(204);
