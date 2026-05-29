<?php
// ============================================================
// CONFIGURATIE — vul deze waarden in vóór het deployen
// ============================================================

// --- Database (zie Plesk > Verbindingsinformatie) ------------
define('DB_HOST',    'localhost');
define('DB_NAME',    'msss_videos');       // bijv. f_00086643_videos
define('DB_USER',    'video_admin');  // bijv. f_00086643
define('DB_PASS',    'V1de0AdminAmber');
define('DB_CHARSET', 'utf8mb4');

// --- Mollie -------------------------------------------------
// Test-sleutel begint met 'test_', live-sleutel met 'live_'
define('MOLLIE_API_KEY', 'test_pSUAUcPBjkVAEyPQzzgDC8nRSvvRej');  // ← de token-waarde uit Mollie

// --- Site ---------------------------------------------------
// Geen trailing slash
define('BASE_URL', 'https://video.msss.nl');

// --- Video bestanden (BUITEN de web root!) ------------------
// Absolute pad naar de private/videos map buiten de web root
define('VIDEO_PATH', '/var/www/vhosts/msss.nl/video.msss.nl/private/videos');

// ============================================================
// Sessie-instellingen (niet aanpassen)
// ============================================================
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
