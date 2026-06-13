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
        // SMTP configuratie
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Afzender en ontvanger
        $mail->setFrom(SMTP_FROM_EMAIL, $fromName ?: SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Bericht
        $mail->isHTML(false); // Platte tekst
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Verstuur
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer fout: {$mail->ErrorInfo}");
        return false;
    }
}
