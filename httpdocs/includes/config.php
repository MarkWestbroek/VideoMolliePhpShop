<?php
// ============================================================
// CONFIGURATIE — vul deze waarden in vóór het deployen
// --- NB: instellingen voor video.msss.nl                  ---
// --- met test-Mollie-sleutel, niet voor productie!        ---
// ============================================================

// --- Database (zie Plesk > Verbindingsinformatie) ------------
define('DB_HOST',    'localhost');
define('DB_NAME',    'msss_videos');       // hb: h_00086643_videos
define('DB_USER',    'video_admin');  // hb: h_00086643_video_admin
define('DB_PASS',    'V1de0AdminAmber'); // hb: idem
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

// --- SMTP configuratie voor e-mail --------------------------
/* HANS
define('SMTP_HOST',     'smtp.mijndomein.nl');
define('SMTP_PORT',     587);  // 587 voor TLS, 465 voor SSL
define('SMTP_USERNAME', 'noreply@hbfoto.nl');  // ← het e-mailaccount dat je gebruikt voor SMTP
define('SMTP_PASSWORD', '');  // ← vul hier het wachtwoord in van noreply@hbfoto.nl
define('SMTP_FROM_EMAIL', 'noreply@hbfoto.nl');
define('SMTP_FROM_NAME', 'HB Foto & Video');
*/

define('SMTP_HOST',     'smtp.msss.nl');
define('SMTP_PORT',     465);  // 587 voor TLS, 465 voor SSL
define('SMTP_USERNAME', 'noreply@msss.nl');  // ← het e-mailaccount dat je gebruikt voor SMTP
define('SMTP_PASSWORD', '');  // ← vul hier het wachtwoord in van de SMTP @msss.nl (u$ual als het goed is)
define('SMTP_FROM_EMAIL', 'noreply@msss.nl');
define('SMTP_FROM_NAME', 'MW Foto & Video');

// Lokale override voor wachtwoorden / staging-specifieke instellingen.
// Maak een httpdocs/includes/config.local.php aan en voeg die toe aan .gitignore.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

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
