<?php
// ============================================================
// CONFIGURATIE — vul deze waarden in vóór het deployen
// ============================================================

// --- Database (zie Plesk > Verbindingsinformatie) ------------
define('DB_HOST',    'localhost');
define('DB_NAME',    'VULDEZELF_dbnaam');       // bijv. f_00086643_videos
define('DB_USER',    'VULDEZELF_dbgebruiker');  // bijv. f_00086643
define('DB_PASS',    'VULDEZELF_wachtwoord');
define('DB_CHARSET', 'utf8mb4');

// --- Mollie -------------------------------------------------
// Test-sleutel begint met 'test_', live-sleutel met 'live_'
define('MOLLIE_API_KEY', 'test_VULDEZELF');

// --- Site ---------------------------------------------------
// Geen trailing slash
define('BASE_URL', 'https://hbfoto.nl');

// --- Video bestanden (BUITEN de web root!) ------------------
// Via Plesk SSH: maak de map aan met:  mkdir -p ~/private/videos
// Het absolute pad is doorgaans: /var/www/vhosts/hbfoto.nl/private/videos
define('VIDEO_PATH', '/var/www/vhosts/hbfoto.nl/private/videos');

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
