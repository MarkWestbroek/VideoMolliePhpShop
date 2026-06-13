<?php
declare(strict_types=1);

/**
 * Mail wrapper - gebruikt PHPMailer via SMTP in plaats van PHP mail()
 */

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

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
function sendMail(string $to, string $subject, string $body, string $fromName = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        // Als SMTP niet is ingesteld, val direct terug op PHP mail()
        if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') || SMTP_USERNAME === '' || SMTP_PASSWORD === '') {
            return fallbackMail($to, $subject, $body, $fromName);
        }

        if (!smtpIsReachable()) {
            error_log('SMTP pre-check: host niet bereikbaar, fallback naar mail()');
            return fallbackMail($to, $subject, $body, $fromName);
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
        return fallbackMail($to, $subject, $body, $fromName);
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
