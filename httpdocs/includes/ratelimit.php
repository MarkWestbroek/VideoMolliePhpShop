<?php
declare(strict_types=1);

/**
 * Rate limiting via de database.
 *
 * Elke mislukte poging wordt gelogd met IP-adres en actie-type.
 * Bij te veel pogingen binnen het tijdvenster wordt verdere toegang geweigerd.
 *
 * Limieten (instelbaar via de constanten hieronder):
 *   login          : 5 pogingen per 10 minuten
 *   forgot_password: 3 pogingen per 10 minuten
 *   event_code     : 10 pogingen per 10 minuten
 */

// Maximaal aantal pogingen per actie per tijdvenster
const RL_LIMITS = [
    'login'           => ['max' => 5,  'window' => 600],
    'forgot_password' => ['max' => 3,  'window' => 600],
    'event_code'      => ['max' => 10, 'window' => 600],
];

/**
 * Geeft het IP-adres van de verbinding terug.
 * We vertrouwen uitsluitend REMOTE_ADDR — niet X-Forwarded-For,
 * want die kan door aanvallers gespooft worden om de limiet te omzeilen.
 */
function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Controleert of het IP geblokkeerd is voor de gegeven actie.
 * Ruimt meteen verlopen rijen op (houdt de tabel klein).
 */
function isRateLimited(string $action): bool
{
    $cfg    = RL_LIMITS[$action] ?? ['max' => 5, 'window' => 600];
    $ip     = clientIp();
    $since  = date('Y-m-d H:i:s', time() - $cfg['window']);

    // Opruimen van verlopen rijen voor dit IP+actie (goedkoop, geïndexeerd)
    db()->prepare(
        'DELETE FROM login_attempts WHERE ip = ? AND action = ? AND created_at < ?'
    )->execute([$ip, $action, $since]);

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND action = ? AND created_at >= ?'
    );
    $stmt->execute([$ip, $action, $since]);
    return (int) $stmt->fetchColumn() >= $cfg['max'];
}

/**
 * Registreert een mislukte poging.
 */
function recordAttempt(string $action): void
{
    db()->prepare(
        'INSERT INTO login_attempts (ip, action) VALUES (?, ?)'
    )->execute([clientIp(), $action]);
}

/**
 * Wist alle pogingen voor dit IP+actie na een succesvolle actie.
 */
function clearAttempts(string $action): void
{
    db()->prepare(
        'DELETE FROM login_attempts WHERE ip = ? AND action = ?'
    )->execute([clientIp(), $action]);
}

/**
 * Geeft het aantal seconden terug totdat het IP weer mag proberen.
 * Retourneert 0 als het IP niet geblokkeerd is.
 */
function rateLimitWaitSeconds(string $action): int
{
    $cfg   = RL_LIMITS[$action] ?? ['max' => 5, 'window' => 600];
    $ip    = clientIp();
    $since = date('Y-m-d H:i:s', time() - $cfg['window']);

    // Zoek de oudste poging in het huidige venster die de limiet veroorzaakt
    $stmt = db()->prepare(
        'SELECT created_at FROM login_attempts
         WHERE ip = ? AND action = ? AND created_at >= ?
         ORDER BY created_at ASC
         LIMIT 1 OFFSET ' . ($cfg['max'] - 1)
    );
    $stmt->execute([$ip, $action, $since]);
    $oldest = $stmt->fetchColumn();
    if (!$oldest) return 0;

    $unlocksAt = strtotime($oldest) + $cfg['window'];
    return max(0, $unlocksAt - time());
}
