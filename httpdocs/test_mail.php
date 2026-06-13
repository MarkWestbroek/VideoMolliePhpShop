<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';

// Alleen voor admins
if (!isLoggedIn() || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('Geen toegang — alleen voor admins.');
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methode = $_POST['methode'] ?? '';
    $testEmail = trim($_POST['test_email'] ?? '');
    
    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $result = ['success' => false, 'error' => 'Ongeldig e-mailadres'];
    } else {
        $subject = 'Test e-mail — HB Foto & Video';
        $body = "Dit is een test e-mail verzonden op " . date('Y-m-d H:i:s') . "\r\n\r\n"
             . "Methode: " . ($methode === 'php' ? 'PHP mail()' : 'PHPMailer SMTP') . "\r\n"
             . "Van: " . SMTP_FROM_EMAIL . "\r\n"
             . "Aan: " . $testEmail . "\r\n\r\n"
             . "Als je dit leest, werkt de e-mailverzending!";

        if ($methode === 'php') {
            // --- Test via PHP mail() ---
            $headers = implode("\r\n", [
                'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
                'Reply-To: ' . SMTP_FROM_EMAIL,
                'Content-Type: text/plain; charset=UTF-8',
                'X-Mailer: PHP/' . PHP_VERSION,
            ]);
            
            $sent = @mail($testEmail, $subject, $body, $headers);
            
            if ($sent) {
                $result = ['success' => true, 'methode' => 'PHP mail()'];
            } else {
                $result = ['success' => false, 'error' => 'mail() retourneerde false'];
            }
        } else {
            // --- Test via PHPMailer SMTP ---
            $sent = sendMail($testEmail, $subject, $body);
            
            if ($sent) {
                $result = ['success' => true, 'methode' => 'PHPMailer SMTP'];
            } else {
                $result = ['success' => false, 'error' => 'PHPMailer fout — check error log'];
            }
        }
    }
}

// Huidige SMTP config (wachtwoord gemaskeerd)
$smtpInfo = [
    'host' => SMTP_HOST,
    'port' => SMTP_PORT,
    'user' => SMTP_USERNAME,
    'pass' => str_repeat('•', 8),
    'from' => SMTP_FROM_EMAIL,
];

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>E-mail test — HB Foto & Video</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #f5f5f5; padding: 2rem; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { margin-bottom: 1rem; color: #333; }
        .card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .info { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .info code { background: #fff; padding: 2px 6px; border-radius: 3px; font-size: .9em; }
        .success { background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #2e7d32; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #c62828; }
        label { display: block; margin-bottom: .5rem; font-weight: 600; color: #555; }
        input[type="email"] { width: 100%; padding: .75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
        .buttons { display: flex; gap: 1rem; }
        button { flex: 1; padding: .75rem 1.5rem; border: none; border-radius: 4px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all .2s; }
        .btn-php { background: #ff9800; color: white; }
        .btn-php:hover { background: #f57c00; }
        .btn-smtp { background: #2196F3; color: white; }
        .btn-smtp:hover { background: #1976D2; }
        .back { display: inline-block; margin-top: 1rem; color: #2196F3; text-decoration: none; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 E-mail test</h1>
        
        <div class="info">
            <strong>SMTP configuratie:</strong><br>
            Host: <code><?= htmlspecialchars($smtpInfo['host']) ?></code><br>
            Port: <code><?= $smtpInfo['port'] ?></code><br>
            User: <code><?= htmlspecialchars($smtpInfo['user']) ?></code><br>
            From: <code><?= htmlspecialchars($smtpInfo['from']) ?></code>
        </div>

        <?php if ($result): ?>
            <?php if ($result['success']): ?>
                <div class="success">
                    ✅ <strong>Succes!</strong> Test e-mail verstuurd via <?= htmlspecialchars($result['methode']) ?>.<br>
                    Check je inbox (en spam folder).
                </div>
            <?php else: ?>
                <div class="error">
                    ❌ <strong>Mislukt:</strong> <?= htmlspecialchars($result['error']) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <label for="test_email">Test e-mailadres:</label>
                <input type="email" id="test_email" name="test_email" required 
                       placeholder="jouw@email.nl" value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>">
                
                <div class="buttons">
                    <button type="submit" name="methode" value="php" class="btn-php">
                        Test via PHP mail()
                    </button>
                    <button type="submit" name="methode" value="smtp" class="btn-smtp">
                        Test via PHPMailer SMTP
                    </button>
                </div>
            </form>
        </div>

        <a href="<?= BASE_URL ?>/admin/" class="back">← Terug naar admin</a>
    </div>
</body>
</html>
