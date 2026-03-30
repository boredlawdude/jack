<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_reset_email(string $toEmail, string $resetLink): void {
  $mail = new PHPMailer(true);
$mail->SMTPDebug = 2; // 0 off, 2 = client+server messages
$mail->Debugoutput = function($str, $level) { error_log("SMTPDBG[$level] $str"); };

  try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
   $mail->SMTPSecure = (SMTP_SECURE === 'ssl')
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port       = SMTP_PORT;

    // Recommended: timeouts
    $mail->Timeout = 15;

    // From / To
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($toEmail);

    // Content
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
      "You requested a password reset.\n\n".
      "Reset link: {$resetLink}\n\n".
      "If you did not request this, ignore this email.\n".
      "This link expires in 60 minutes.\n";

    $mail->send();
  } catch (Exception $e) {
    // Don't leak SMTP credentials—log a generic error
    error_log("Password reset email failed: " . $mail->ErrorInfo);
    throw new RuntimeException("Email could not be sent.");
  }
}
