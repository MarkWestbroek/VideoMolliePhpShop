<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . $redirect);
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        exit('Toegang geweigerd.');
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        try {
            $stmt = db()->prepare(
                'SELECT id, email, name, is_admin, email_verified_at FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$_SESSION['user_id']]);
        } catch (\PDOException $e) {
            // Kolom email_verified_at bestaat nog niet (vóór migratie) — val terug
            $stmt = db()->prepare(
                'SELECT id, email, name, is_admin, NULL AS email_verified_at FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$_SESSION['user_id']]);
        }
        $row  = $stmt->fetch();
        $user = $row ?: null;

        // last_activity bijwerken, maar maximaal 1x per minuut (via sessie-cache)
        if ($user !== null) {
            $now = time();
            if (empty($_SESSION['last_activity_updated']) || $now - $_SESSION['last_activity_updated'] >= 60) {
                db()->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?')
                    ->execute([$user['id']]);
                $_SESSION['last_activity_updated'] = $now;
            }
        }
    }

    return $user;
}

function hasPurchased(int $userId, int $videoId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM purchases
         WHERE user_id = ? AND video_id = ? AND status = 'paid'
         LIMIT 1"
    );
    $stmt->execute([$userId, $videoId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Registreer een sessie na succesvolle login.
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['is_admin'] = (bool) $user['is_admin'];

    // IP-tracking: registreer huidige IP en bepaal of video-toegang geblokkeerd moet worden
    // Admins worden wel gelogd maar niet aan de limiet onderworpen.
    $isAdmin = (bool) $user['is_admin'];
    $blocked = trackLoginIp((int) $user['id'], !$isAdmin);
    $_SESSION['viewing_blocked'] = $blocked && !$isAdmin;
}

/**
 * Controleer of de huidige gebruiker zijn e-mail heeft geverifieerd.
 */
function isEmailVerified(): bool
{
    $user = currentUser();
    return $user !== null && $user['email_verified_at'] !== null;
}

// ============================================================
// IP-tracking — tegengaan login delen
// ============================================================

/**
 * Haal het client IP-adres op (rekening houdend met proxy's).
 */
function getClientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Apache mod_remoteip (Plesk) zet het echte IP in REMOTE_ADDR,
    // maar voor nginx/proxy kan X-Forwarded-For gebruikt worden.
    // Alleen de meest linkse gebruiken (die van de originele client).
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
    }

    return $ip;
}

/**
 * Zoek ISP- en mobiel-info op voor een IP-adres via ip-api.com (gratis, 45 req/min).
 * Resultaten worden gecached in de login_ips tabel.
 *
 * @return array{isp: string, is_mobile: int}|null
 */
function lookupIpInfo(string $ip): ?array
{
    // Sla localhost en private IP's over
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.') || str_starts_with($ip, '172.')) {
        return null;
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=isp,mobile';
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $json = @file_get_contents($url, false, $ctx);

    if ($json === false) {
        error_log("IP lookup failed for {$ip}");
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['status'] ?? '') === 'fail') {
        return null;
    }

    return [
        'isp'       => $data['isp'] ?? '',
        'is_mobile' => !empty($data['mobile']) ? 1 : 0,
    ];
}

/**
 * Registreer het IP van de huidige login en bepaal of de gebruiker
 * geblokkeerd moet worden voor het bekijken van video's.
 *
 * Werking:
 *  - Verwijdert verlopen IP's (ouder dan IP_TRACK_TTL seconden)
 *  - Als huidige IP al bekend is: update last_seen → geen blokkade
 *  - Als huidige IP nieuw is en er zijn al IP_TRACK_MAX actieve IP's → blokkade
 *  - Anders: voeg IP toe → geen blokkade
 *
 * @param bool $enforceLimit Als false, altijd loggen zonder blokkade (voor admins)
 * @return bool true als video-toegang geblokkeerd moet worden
 */
function trackLoginIp(int $userId, bool $enforceLimit = true): bool
{
    $ip   = getClientIp();
    $ttl  = IP_TRACK_TTL;
    $expireTime = date('Y-m-d H:i:s', time() - $ttl);

    error_log("Login IP-track start: user {$userId}, huidig IP {$ip}, TTL {$ttl}s" . ($enforceLimit ? '' : ' (admin, geen limiet)'));

    // Verwijder verlopen IP's
    db()->prepare('DELETE FROM login_ips WHERE user_id = ? AND last_seen < ?')
        ->execute([$userId, $expireTime]);

    // Check of huidige IP al bestaat
    $stmt = db()->prepare('SELECT id FROM login_ips WHERE user_id = ? AND ip_address = ? LIMIT 1');
    $stmt->execute([$userId, $ip]);

    if ($stmt->fetch()) {
        // IP al bekend → update last_seen, geen blokkade
        db()->prepare('UPDATE login_ips SET last_seen = NOW() WHERE user_id = ? AND ip_address = ?')
            ->execute([$userId, $ip]);
        return false;
    }

    // Nieuw IP — admin: altijd registreren, geen limiet
    if (!$enforceLimit) {
        $ipInfo = lookupIpInfo($ip);
        if ($ipInfo) {
            db()->prepare('INSERT INTO login_ips (user_id, ip_address, isp, is_mobile) VALUES (?, ?, ?, ?)')
                ->execute([$userId, $ip, $ipInfo['isp'], $ipInfo['is_mobile']]);
        } else {
            db()->prepare('INSERT INTO login_ips (user_id, ip_address) VALUES (?, ?)')
                ->execute([$userId, $ip]);
        }
        return false;
    }

    // Gewone gebruiker: tel bestaande IP's, blokkeer bij overschrijding
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_ips WHERE user_id = ?');
    $stmt->execute([$userId]);
    $cnt = (int) $stmt->fetchColumn();

    error_log("Login IP-track: user {$userId}, nieuw IP {$ip}, al {$cnt} bestaande IP's, max " . IP_TRACK_MAX);

    if ($cnt >= IP_TRACK_MAX) {
        // Limiet bereikt: blokkeer video-toegang, voeg IP niet toe
        error_log("Login share blokkade: user {$userId}, nieuw IP {$ip}, al {$cnt} actieve IP's");

        // Stuur notificatie naar admins
        $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $blockedUser = $stmt->fetch();
        if ($blockedUser) {
            $admins = db()->query('SELECT email FROM users WHERE is_admin = 1')->fetchAll();
            $subject = 'Gebruiker geblokkeerd — HB Foto & Video';
            $body    = "Hallo,\r\n\r\n"
                . "Een gebruiker is geblokkeerd van het bekijken van video's vanwege te veel login-IP's.\r\n\r\n"
                . "Gebruiker: {$blockedUser['name']} ({$blockedUser['email']}, ID {$userId})\r\n"
                . "Nieuw IP : {$ip}\r\n"
                . "Bestaande IP's: {$cnt} (max " . IP_TRACK_MAX . ")\r\n\r\n"
                . "Beheer gebruikers: " . BASE_URL . "/admin/users.php?user={$userId}\r\n\r\n"
                . "— HB Foto & Video (automatisch bericht)";
            foreach ($admins as $admin) {
                sendMail($admin['email'], $subject, $body);
            }
        }

        return true;
    }

    // Registreer nieuw IP (voor admins én onder-limiet gebruikers)
    $ipInfo = lookupIpInfo($ip);
    if ($ipInfo) {
        db()->prepare('INSERT INTO login_ips (user_id, ip_address, isp, is_mobile) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $ip, $ipInfo['isp'], $ipInfo['is_mobile']]);
    } else {
        db()->prepare('INSERT INTO login_ips (user_id, ip_address) VALUES (?, ?)')
            ->execute([$userId, $ip]);
    }
    return false;
}

/**
 * Controleer of de huidige gebruiker geblokkeerd is van het bekijken van video's.
 * Geeft een array terug met ['blocked' => bool, 'ipCount' => int, 'maxIps' => int].
 */
function getViewingBlockedStatus(): array
{
    if (!isLoggedIn()) {
        return ['blocked' => false, 'ipCount' => 0, 'maxIps' => IP_TRACK_MAX];
    }

    // Sessie-flag update bij elke call: check ook DB voor het geval admins de blokkade ophieven
    $userId = (int) $_SESSION['user_id'];

    // Admins zijn uitgezonderd van IP-tracking
    if (!empty($_SESSION['is_admin'])) {
        return ['blocked' => false, 'ipCount' => 0, 'maxIps' => IP_TRACK_MAX];
    }

    // Tel actieve IP's
    $ttl = IP_TRACK_TTL;
    $expireTime = date('Y-m-d H:i:s', time() - $ttl);

    // Verwijder verlopen IP's (opruimen)
    db()->prepare('DELETE FROM login_ips WHERE user_id = ? AND last_seen < ?')
        ->execute([$userId, $expireTime]);

    $stmt = db()->prepare('SELECT COUNT(*) FROM login_ips WHERE user_id = ?');
    $stmt->execute([$userId]);
    $cnt = (int) $stmt->fetchColumn();

    return [
        'blocked' => !empty($_SESSION['viewing_blocked']),
        'ipCount' => $cnt,
        'maxIps'  => IP_TRACK_MAX,
    ];
}

/**
 * Verwijder alle IP-records voor een gebruiker (admin-functie).
 */
function resetLoginIps(int $userId): void
{
    db()->prepare('DELETE FROM login_ips WHERE user_id = ?')->execute([$userId]);
}

/**
 * Haal alle geregistreerde IP's van een gebruiker op (admin inzage).
 */
function getUserLoginIps(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT ip_address, first_seen, last_seen, isp, is_mobile FROM login_ips WHERE user_id = ? ORDER BY first_seen DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
