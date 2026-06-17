<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

requireLogin();

$user    = currentUser();
$ipStatus = getViewingBlockedStatus();
$sent    = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $error = 'Vul een bericht in.';
    } elseif (mb_strlen($message) > 2000) {
        $error = 'Bericht is te lang (max. 2000 tekens).';
    } else {
        // Stuur e-mail naar alle admins
        $stmt = db()->prepare('SELECT email, name FROM users WHERE is_admin = 1');
        $stmt->execute();
        $admins = $stmt->fetchAll();

        $subject = 'Verzoek account vrijgeven — HB Foto & Video';
        $body    = "Hallo,\r\n\r\n"
            . "Gebruiker {$user['name']} ({$user['email']}, ID {$user['id']}) vraagt om zijn account vrij te geven.\r\n\r\n"
            . "Deze gebruiker heeft {$ipStatus['ipCount']} unieke login-IP's (max toegestaan: {$ipStatus['maxIps']}).\r\n\r\n"
            . "Bericht van de gebruiker:\r\n"
            . "{$message}\r\n\r\n"
            . "Beheer gebruikers: " . BASE_URL . "/admin/users.php?user={$user['id']}\r\n\r\n"
            . "— HB Foto & Video (automatisch bericht)";

        foreach ($admins as $admin) {
            sendMail($admin['email'], $subject, $body);
        }

        $sent = true;
    }
}

$pageTitle = 'Contact — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-card" style="max-width:600px;">
    <h1>&#9993; Contact opnemen</h1>

    <?php if ($sent): ?>
        <div class="alert alert-success">
            Je bericht is verzonden. De beheerder neemt zo snel mogelijk contact met je op.
        </div>
        <p class="text-center" style="margin-top:1rem;">
            <a href="<?= BASE_URL ?>/members/">Terug naar video-overzicht</a>
        </p>
    <?php else: ?>
        <?php if ($ipStatus['blocked']): ?>
            <div class="alert alert-warning" style="margin-bottom:1.5rem;">
                Je video-toegang is geblokkeerd vanwege te veel verschillende login-locaties.
                Stuur een bericht naar de beheerder om dit op te lossen.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <p style="margin-bottom:1rem;color:var(--text-muted);">
            Ingelogd als <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>)
        </p>

        <form method="post">
            <div class="form-group">
                <label for="message">Je bericht</label>
                <textarea id="message" name="message" required rows="6" maxlength="2000"
                          placeholder="Leg uit waarom je op meerdere locaties inlogt..."
                          style="width:100%;padding:.75rem;border:1px solid var(--border);border-radius:4px;font-size:1rem;font-family:inherit;"><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="form-hint">Max. 2000 tekens.</p>
            </div>
            <button type="submit" class="btn btn-primary">Verstuur bericht</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
