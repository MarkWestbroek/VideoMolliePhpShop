<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Al ingelogd? Stuur door.
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

$error    = '';
$redirect = '/members/';

// Valideer de redirect-parameter (alleen relatieve paden toestaan)
if (!empty($_GET['redirect'])) {
    $r = $_GET['redirect'];
    if (str_starts_with($r, '/') && !str_starts_with($r, '//')) {
        $redirect = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vul e-mailadres en wachtwoord in.';
    } else {
        $stmt = db()->prepare('SELECT id, email, name, password_hash, is_admin FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            header('Location: ' . BASE_URL . $redirect);
            exit;
        } else {
            // Zelfde foutmelding voor onbekend e-mail én fout wachtwoord (geen user enumeration)
            $error = 'E-mailadres of wachtwoord is onjuist.';
        }
    }
}

$pageTitle = 'Inloggen — HB Foto & Video';
require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Inloggen</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php?redirect=<?= htmlspecialchars(urlencode($redirect), ENT_QUOTES, 'UTF-8') ?>">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Inloggen</button>
    </form>

    <p class="text-center mt-2 text-muted" style="font-size:.9rem">
        Nog geen account? <a href="<?= BASE_URL ?>/register.php">Registreer hier</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
