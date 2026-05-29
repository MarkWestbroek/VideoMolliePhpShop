<?php
declare(strict_types=1);

/**
 * stream.php — Beveiligde video streaming met HTTP Range Request ondersteuning
 *
 * Controles vóór streamen:
 *  1. Gebruiker is ingelogd
 *  2. Video bestaat en is actief
 *  3. Gebruiker heeft de video betaald
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// 1. Authenticatie
if (!isLoggedIn()) {
    http_response_code(403);
    exit;
}

// 2. Video-ID ophalen en valideren
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($videoId <= 0) {
    http_response_code(400);
    exit;
}

// 3. Video opzoeken in de database
$stmt = db()->prepare('SELECT id, filename FROM videos WHERE id = ? AND active = 1 LIMIT 1');
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    exit;
}

// 4. Controleer of de gebruiker toegang heeft (betaald)
if (!hasPurchased((int) $_SESSION['user_id'], $videoId)) {
    http_response_code(403);
    exit;
}

// 5. Bouw bestandspad (basename() voorkomt path traversal)
$filename = basename($video['filename']);
$filePath = rtrim(VIDEO_PATH, '/\\') . DIRECTORY_SEPARATOR . $filename;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit;
}

// 6. Bestand streamen met Range Request ondersteuning
$fileSize = filesize($filePath);
$mimeType = 'video/mp4';

$start = 0;
$end   = $fileSize - 1;

// Output-buffering uitzetten zodat data direct naar client gaat
if (ob_get_level()) {
    ob_end_clean();
}

// Sta het toe dat lange downloads niet afbreken bij verbindingsbreuk
ignore_user_abort(true);
set_time_limit(0);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

// Range-verzoek afhandelen (nodig voor seekbar in videospeler)
if (isset($_SERVER['HTTP_RANGE'])) {
    $rangeHeader = $_SERVER['HTTP_RANGE'];

    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $m)) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    $hasStart = $m[1] !== '';
    $hasEnd   = $m[2] !== '';

    if (!$hasStart) {
        // Suffix range: bytes=-500 → laatste 500 bytes
        $suffixLen = (int) $m[2];
        $start = max(0, $fileSize - $suffixLen);
        $end   = $fileSize - 1;
    } else {
        $start = (int) $m[1];
        $end   = $hasEnd ? (int) $m[2] : $fileSize - 1;
    }

    if ($start < 0 || $start > $end || $end >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    http_response_code(206);
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $fileSize));
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

// Bestand lezen en sturen in blokken van 256 KB
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}

fseek($fp, $start);

$chunkSize = 262144; // 256 KB
$remaining = $length;

while (!feof($fp) && $remaining > 0 && !connection_aborted()) {
    $readSize = min($chunkSize, $remaining);
    $data     = fread($fp, $readSize);

    if ($data === false) {
        break;
    }

    echo $data;
    $remaining -= strlen($data);
    flush();
}

fclose($fp);
exit;
