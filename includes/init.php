<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionName = $_ENV['SESSION_NAME'] ?? 'contracts_app_sess';
    session_name($sessionName);

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $domain = $_SERVER['HTTP_HOST'] ?? '';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $domain,
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Session fingerprinting — invalidate sessions from different browsers/IPs
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($secure ? 'https' : 'http'));
    if (isset($_SESSION['_fingerprint'])) {
        if (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
            // Fingerprint mismatch — destroy and restart
            session_unset();
            session_destroy();
            session_start();
        }
    } else {
        $_SESSION['_fingerprint'] = $fingerprint;
    }

    // Absolute session timeout — 8 hours regardless of activity
    $maxLifetime = 8 * 3600;
    if (isset($_SESSION['_created_at'])) {
        if (time() - $_SESSION['_created_at'] > $maxLifetime) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
            $_SESSION['_created_at'] = time();
        }
    } else {
        $_SESSION['_created_at'] = time();
    }
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_once __DIR__ . '/auth.php';