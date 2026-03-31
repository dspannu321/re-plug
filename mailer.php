<?php
// RePlug — mailer helper using Brevo SMTP

require_once __DIR__ . '/app/config/env.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function replug_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $host = $_ENV['SMTP_HOST'] ?? '';
    $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
    $user = $_ENV['SMTP_USERNAME'] ?? '';
    $pass = $_ENV['SMTP_PASSWORD'] ?? '';
    $enc  = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
    $from = $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com';
    $name = $_ENV['MAIL_FROM_NAME'] ?? 'RePlug';

    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = $enc;
    $mail->Port       = $port;

    $mail->setFrom($from, $name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}

/**
 * Simple helper to send an email.
 *
 * @return bool true on success, false on failure
 */
function replug_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    try {
        $mail = replug_mailer();
        $mail->clearAllRecipients();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
        return $mail->send();
    } catch (Exception $e) {
        // Optionally log $e->getMessage()
        return false;
    }
}