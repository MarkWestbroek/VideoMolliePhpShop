<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';

$message = '';
$error   = '';

// --- Resend verification email ---
if (isLoggedIn() && isset($_GET['resend'])) {
    $user = currentUser();
    if ($user['email_verified_at'] !== null) {
        $message = 'Je e-mailadres is al geverifieerd!';
    } else {
        // Genereer nieuw token
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        db()->prepare('UPDATE users SET verification_token = ? WHERE id = ?')
            ->execute([$tokenHash, $user['id']]);

        $verifyLink  = BASE_URL . '/verify.php?token=' . urlencode($rawToken);
        $mailSubject = 'Bevestig je e-mailadres — HB Foto & Video';
        $mailBody    = "Hallo {$user['name']},\r\n\r\n"
            . "Klik op de onderstaande link om je e-mailadres te bevestigen:\r\n"
            . "{$verifyLink}\r\n\r\n"
            . "Deze link is 24 uur geldig.\r\n\r\n"
            . "Met vriendelijke groet,\r\nHB Foto & Video";

        $sent = sendMail($user['email'], $mailSubject, $mailBody);
        $message = $sent
            ? 'Er is een nieuwe verificatie-e-mail verstuurd naar <strong>' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</strong>.'
            : 'Er is een fout opgetreden bij het versturen van de e-mail. Probeer het later opnieuw.';
    }
}

// Al ingelogd en geen resend? Stuur door.
if (isLoggedIn() && !isset($_GET['resend'])) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

$rawToken = $_GET['token'] ?? '';

if ($rawToken === '') {
    // Geen token en geen resend — alleen een pagina tonen als er geen bericht is
    if ($message === '' && $error === '') {
        $error = 'Geen verificatietoken opgegeven.';
    }
} else {
    $tokenHash = hash('sha256', $rawToken);

    // Zoek gebruiker met deze token
    $stmt = db()->prepare(
        'SELECT id, email, name, email_verified_at FROM users
         WHERE verification_token = ? LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Ongeldige of verlopen verificatielink.';
    } elseif ($user['email_verified_at'] !== null) {
        $message = 'Je e-mailadres is al geverifieerd! Je kunt <a href="' . BASE_URL . '/login.php">inloggen</a>.';
    } else {
        // Markeer als geverifieerd
        db()->prepare(
            'UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = ?'
        )->execute([$user['id']]);

        $message = 'E-mailadres bevestigd! Je kunt nu <a href="' . BASE_URL . '/login.php">inloggen</a>.';
    }
}

$pageTitle = 'E-mail bevestigen — HB Foto & Video';
require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>E-mailadres bevestigen</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <p class="text-center mt-2" style="font-size:.9rem">
            <a href="<?= BASE_URL ?>/login.php">Terug naar inloggen</a>
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
