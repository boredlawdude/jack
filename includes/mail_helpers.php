<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_reset_email(string $toEmail, string $resetLink): void
{
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    $mail->Debugoutput = function ($str, $level) { error_log("SMTPDBG[$level] $str"); };

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = (($_ENV['SMTP_SECURE'] ?? 'tls') === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];
        $mail->Timeout    = 15;

        $mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME'] ?? '');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Password reset link';

        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <p>You requested a password reset.</p>
            <p><a href=\"{$safeLink}\">Click here to reset your password</a></p>
            <p>If you did not request this, you can ignore this email.</p>
            <p>This link expires in 60 minutes.</p>
        ";

        $mail->AltBody =
            "You requested a password reset.\n\n" .
            "Reset link: {$resetLink}\n\n" .
            "If you did not request this, ignore this email.\n" .
            "This link expires in 60 minutes.\n";

        $mail->send();
    } catch (Exception $e) {
        error_log("Password reset email failed: " . $mail->ErrorInfo);
        throw new RuntimeException("Email could not be sent.");
    }
}
