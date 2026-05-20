<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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
        $stmt = db()->prepare(
            'SELECT id, email, name, is_admin FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $row  = $stmt->fetch();
        $user = $row ?: null;
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
}
