<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

$error   = '';
$success = '';

// Token uit de URL ophalen en de bijbehorende reset-rij valideren
$rawToken = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($rawToken === '') {
    header('Location: ' . BASE_URL . '/forgot_password.php');
    exit;
}

$tokenHash = hash('sha256', $rawToken);

$stmt = db()->prepare(
    'SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email
     FROM password_resets pr
     JOIN users u ON u.id = pr.user_id
     WHERE pr.token = ? LIMIT 1'
);
$stmt->execute([$tokenHash]);
$reset = $stmt->fetch();

$tokenValid = $reset
    && $reset['used'] === 0
    && strtotime($reset['expires_at']) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!$tokenValid) {
        $error = 'Deze reset-link is ongeldig of verlopen. Vraag een nieuwe aan.';
    } else {
        $password  =  $_POST['password']  ?? '';
        $password2 =  $_POST['password2'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
        } elseif ($password !== $password2) {
            $error = 'Wachtwoorden komen niet overeen.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$hash, $reset['user_id']]);

            // Token als gebruikt markeren
            db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
                ->execute([$reset['id']]);

            $success = true;
        }
    }
}

$pageTitle = 'Nieuw wachtwoord instellen — HB Foto & Video';
require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Nieuw wachtwoord instellen</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            Je wachtwoord is bijgewerkt. Je kunt nu inloggen met je nieuwe wachtwoord.
        </div>
        <p class="text-center mt-2" style="font-size:.9rem">
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Inloggen</a>
        </p>

    <?php elseif (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <div class="alert alert-error">
            Deze reset-link is ongeldig of verlopen.
        </div>
        <p class="text-center mt-2" style="font-size:.9rem">
            <a href="<?= BASE_URL ?>/forgot_password.php">Vraag een nieuwe link aan</a>
        </p>

    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/reset_password.php">
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="password">Nieuw wachtwoord</label>
                <input type="password" id="password" name="password" required
                       minlength="8" autofocus autocomplete="new-password">
                <p class="form-hint">Minimaal 8 tekens.</p>
            </div>

            <div class="form-group">
                <label for="password2">Wachtwoord herhalen</label>
                <input type="password" id="password2" name="password2" required
                       minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full">Wachtwoord opslaan</button>
        </form>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
