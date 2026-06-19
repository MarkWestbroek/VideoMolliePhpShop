<?php
declare(strict_types=1);

/**
 * Mail wrapper - gebruikt PHPMailer via SMTP in plaats van PHP mail()
 */

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Verstuur e-mail via SMTP (PHPMailer)
 * 
 * @param string $to Ontvanger e-mailadres
 * @param string $subject Onderwerp
 * @param string $body Berichtinhoud (platte tekst)
 * @param string $fromName Afzender naam (optioneel, gebruikt SMTP_FROM_NAME)
 * @return bool True bij succes, false bij fout
 */
function sendMail(string $to, string $subject, string $body, string $fromName = '', bool $useFallback = true): bool
{
    $mail = new PHPMailer(true);

    try {
        // Als SMTP niet is ingesteld, val direct terug op PHP mail()
        if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') || SMTP_USERNAME === '' || SMTP_PASSWORD === '') {
            return $useFallback ? fallbackMail($to, $subject, $body, $fromName) : false;
        }

        if (!smtpIsReachable()) {
            error_log('SMTP pre-check: host niet bereikbaar');
            return $useFallback ? fallbackMail($to, $subject, $body, $fromName) : false;
        }

        // SMTP configuratie
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 5;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPKeepAlive = false;
        $mail->SMTPAutoTLS  = true;
        $mail->SMTPDebug    = SMTP::DEBUG_OFF;

        if (SMTP_PORT === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Afzender en ontvanger
        $mail->setFrom(SMTP_FROM_EMAIL, $fromName ?: SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Bericht
        $mail->isHTML(false); // Platte tekst
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Socket timeout
        $mail->SMTPOptions = [
            'socket' => [
                'timeout' => 5,
            ],
        ];

        // Verstuur
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer fout: {$mail->ErrorInfo}");
        error_log("PHPMailer exception: " . $e->getMessage());
        return $useFallback ? fallbackMail($to, $subject, $body, $fromName) : false;
    }
}

function smtpIsReachable(): bool
{
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $timeout = 2;

    if ($port === 465) {
        $host = 'ssl://' . $host;
    }

    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }

    error_log("SMTP preconnect fout: {$errno} - {$errstr}");
    return false;
}

function fallbackMail(string $to, string $subject, string $body, string $fromName = ''): bool
{
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $fromName  = $fromName ?: (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'HB Foto & Video');

    $headers = implode("\r\n", [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $sent = @mail($to, $subject, $body, $headers);
    if (! $sent) {
        error_log("fallbackMail fout: mail() retourneerde false voor ontvanger {$to}");
    }
    return $sent;
}

/**
 * Stuur een aankoopbevestiging naar de klant.
 * Alleen aanroepen bij een definitieve overgang naar 'paid'.
 */
function sendPurchaseConfirmation(int $purchaseId): void
{
    $stmt = db()->prepare(
        'SELECT u.email, u.name, v.title, v.id AS video_id, p.amount, p.status
         FROM purchases p
         JOIN users  u ON u.id = p.user_id
         JOIN videos v ON v.id = p.video_id
         WHERE p.id = ? AND p.status = \'paid\'
         LIMIT 1'
    );
    $stmt->execute([$purchaseId]);
    $p = $stmt->fetch();

    if (!$p) {
        return; // Alleen bevestigen bij daadwerkelijk betaald
    }

    $videoLink = BASE_URL . '/members/watch.php?id=' . (int) $p['video_id'];
    $subject   = 'Aankoopbevestiging — HB Foto & Video';
    $body      = "Beste {$p['name']},\r\n\r\n"
        . "Bedankt voor je aankoop van \"{$p['title']}\"!\r\n\r\n"
        . "Je kunt de video bekijken via deze link:\r\n"
        . "{$videoLink}\r\n\r\n"
        . "Veel kijkplezier!\r\n\r\n"
        . "Met vriendelijke groet,\r\nHB Foto & Video";

    sendMail($p['email'], $subject, $body);

    // Stuur ook een notificatie naar alle admins
    $adminSubject = 'Nieuwe aankoop — HB Foto & Video';
    $adminBody    = "Hallo,\r\n\r\n"
        . "Er is zojuist een aankoop gedaan.\r\n\r\n"
        . "Klant : {$p['name']} ({$p['email']})\r\n"
        . "Video : {$p['title']}\r\n"
        . "Bedrag: € " . number_format((float) $p['amount'], 2, ',', '.') . "\r\n\r\n"
        . "— HB Foto & Video (automatisch bericht)";

    $admins = db()->query('SELECT email FROM users WHERE is_admin = 1')->fetchAll();
    foreach ($admins as $admin) {
        sendMail($admin['email'], $adminSubject, $adminBody);
    }
}
