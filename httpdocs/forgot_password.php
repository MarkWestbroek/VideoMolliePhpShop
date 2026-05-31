<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/ratelimit.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } elseif (isRateLimited('forgot_password')) {
        $wacht = rateLimitWaitSeconds('forgot_password');
        $error = 'Te veel aanvragen. Probeer het over ' . ceil($wacht / 60) . ' minuten opnieuw.';
    } else {
        recordAttempt('forgot_password');
        // Zoek gebruiker — zelfde melding tonen ongeacht of het e-mail bestaat (geen user enumeration)
        $stmt = db()->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verwijder eventuele oude (verlopen) tokens van deze gebruiker
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')
                ->execute([$user['id']]);

            // Genereer een veilig token (64 hex-tekens) en sla de hash ervan op
            $rawToken   = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $rawToken);
            $expiresAt  = date('Y-m-d H:i:s', time() + 3600); // 1 uur geldig

            db()->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
            )->execute([$user['id'], $tokenHash, $expiresAt]);

            // Stuur e-mail
            $resetLink = BASE_URL . '/reset_password.php?token=' . urlencode($rawToken);
            $naam      = $user['name'];
            $subject   = 'Wachtwoord opnieuw instellen — HB Foto & Video';
            $body      = "Hallo {$naam},\r\n\r\n"
                . "Je hebt een aanvraag gedaan om je wachtwoord opnieuw in te stellen.\r\n\r\n"
                . "Klik op de onderstaande link (geldig voor 1 uur):\r\n"
                . "{$resetLink}\r\n\r\n"
                . "Als je dit niet hebt aangevraagd, kun je deze e-mail negeren.\r\n\r\n"
                . "Met vriendelijke groet,\r\nHB Foto & Video";

            $domain  = parse_url(BASE_URL, PHP_URL_HOST) ?? 'hbfoto.nl';
            $headers = implode("\r\n", [
                'From: HB Foto & Video <noreply@' . $domain . '>',
                'Reply-To: noreply@' . $domain,
                'Content-Type: text/plain; charset=UTF-8',
                'X-Mailer: PHP/' . PHP_VERSION,
            ]);

            mail($email, $subject, $body, $headers);
        }

        // Altijd dezelfde melding tonen
        $sent = true;
    }
    }

$pageTitle = 'Wachtwoord vergeten — HB Foto & Video';
require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Wachtwoord vergeten</h1>

    <?php if ($sent): ?>
        <div class="alert alert-success">
            Als dit e-mailadres bij ons bekend is, ontvang je binnen enkele minuten een e-mail
            met een link om je wachtwoord opnieuw in te stellen.
        </div>
        <p class="text-center mt-2" style="font-size:.9rem">
            <a href="<?= BASE_URL ?>/login.php">Terug naar inloggen</a>
        </p>
    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <p class="text-muted" style="margin-bottom:1.25rem;font-size:.95rem;">
            Vul je e-mailadres in. Als het bij ons bekend is, sturen we je een link
            om een nieuw wachtwoord in te stellen.
        </p>

        <form method="post" action="<?= BASE_URL ?>/forgot_password.php">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" required autofocus
                       autocomplete="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Reset-link versturen</button>
        </form>

        <p class="text-center mt-2 text-muted" style="font-size:.9rem">
            <a href="<?= BASE_URL ?>/login.php">Terug naar inloggen</a>
        </p>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
