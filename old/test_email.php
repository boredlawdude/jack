<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/mail.php';

send_reset_email(SMTP_USERNAME, "https://example.com/reset?token=TEST");
echo "Sent.";
