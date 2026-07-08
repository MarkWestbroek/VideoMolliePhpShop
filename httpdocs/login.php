<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/ratelimit.php';

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
    if (strpos($r, '/') === 0 && strpos($r, '//') !== 0) {
        $redirect = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF manueel checken zodat we netjes de fout in het formulier kunnen tonen
    $csrfToken     = $_POST['csrf_token'] ?? '';
    $sessionToken  = $_SESSION['csrf_token'] ?? null;
    if ($sessionToken === null || !hash_equals($sessionToken, $csrfToken)) {
        // Token verlopen of sessie leeg (sessie GC op shared hosting):
        // forceer nieuw token, maar toon géén fout als de sessie gewoon leeg was
        // (gebruiker ziet hetzelfde formulier, kan direct opnieuw versturen)
        unset($_SESSION['csrf_token']);
        if ($sessionToken === null) {
            // Sessie was leeg — stille refresh, geen foutmelding
            $error = '';
        } else {
            $error = 'Je sessie is verlopen. Probeer opnieuw in te loggen.';
        }
    } else {
        unset($_SESSION['csrf_token']); // Token verbruikt

        // Rate limiting controleren vóór we iets doen
        if (isRateLimited('login')) {
            $wacht = rateLimitWaitSeconds('login');
            $error = 'Te veel mislukte pogingen. Probeer het over '
                . ceil($wacht / 60) . ' minuten opnieuw.';
        } else {
            $email    = trim($_POST['email']    ?? '');
            $password =       $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $error = 'Vul e-mailadres en wachtwoord in.';
            } else {
                $stmt = db()->prepare('SELECT id, email, name, password_hash, is_admin FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    clearAttempts('login');
                    loginUser($user);
                    header('Location: ' . BASE_URL . $redirect);
                    exit;
                } else {
                    recordAttempt('login');
                    // Zelfde foutmelding voor onbekend e-mail én fout wachtwoord (geen user enumeration)
                    $error = 'E-mailadres of wachtwoord is onjuist.';
                    if (isRateLimited('login')) {
                        $wacht = rateLimitWaitSeconds('login');
                        $error = 'Te veel mislukte pogingen. Probeer het over '
                            . ceil($wacht / 60) . ' minuten opnieuw.';
                    }
                }
            }
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
        <a href="<?= BASE_URL ?>/forgot_password.php">Wachtwoord vergeten?</a>
    </p>
    <p class="text-center mt-1 text-muted" style="font-size:.9rem">
        Nog geen account? <a href="<?= BASE_URL ?>/register.php">Registreer hier</a>
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
