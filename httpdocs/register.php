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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = trim($_POST['name']      ?? '');
    $email    = trim($_POST['email']     ?? '');
    $password =       $_POST['password'] ?? '';
    $password2 =      $_POST['password2'] ?? '';

    // Validatie
    if ($name === '' || $email === '' || $password === '') {
        $error = 'Vul alle velden in.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Naam is te lang (max. 100 tekens).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
    } elseif ($password !== $password2) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        // Controleer of e-mail al bestaat
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetchColumn()) {
            $error = 'Dit e-mailadres is al in gebruik.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = db()->prepare(
                'INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)'
            );
            $stmt->execute([$email, $hash, $name]);

            $success = 'Account aangemaakt! Je kunt nu <a href="' . BASE_URL . '/login.php">inloggen</a>.';
        }
    }
}

$pageTitle = 'Registreren — HB Foto & Video';
require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Registreren</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php else: ?>

    <form method="post" action="/register.php">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="name">Naam</label>
            <input type="text" id="name" name="name"
                   value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus maxlength="100" autocomplete="name">
        </div>

        <div class="form-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required
                   minlength="8" autocomplete="new-password">
            <p class="form-hint">Minimaal 8 tekens.</p>
        </div>

        <div class="form-group">
            <label for="password2">Wachtwoord herhalen</label>
            <input type="password" id="password2" name="password2" required
                   minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Account aanmaken</button>
    </form>

    <?php endif; ?>

    <p class="text-center mt-2 text-muted" style="font-size:.9rem">
        Al een account? <a href="<?= BASE_URL ?>/login.php">Inloggen</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
