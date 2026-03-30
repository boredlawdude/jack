<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mail_helpers.php';

header('Content-Type: text/html; charset=utf-8');

function request_base_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
  $scheme = $https ? 'https' : 'http';
  $host = trim($_SERVER['HTTP_HOST'] ?? 'localhost');
  return $scheme . '://' . $host;
}

$pdo = pdo();
$email = trim(strtolower($_POST['email'] ?? ''));
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $msg = "If the email exists and has login access, a reset link has been sent.";

  $stmt = $pdo->prepare("SELECT person_id, is_active, can_login FROM people WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($p && (int)$p['is_active'] === 1 && (int)$p['can_login'] === 1) {
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

    // mark prior tokens used
    $pdo->prepare("
      UPDATE password_resets
      SET used_at = NOW()
      WHERE person_id = ?
        AND used_at IS NULL
        AND expires_at > NOW()
    ")->execute([(int)$p['person_id']]);

    $pdo->prepare("
      INSERT INTO password_resets (person_id, token_hash, expires_at, requested_ip, user_agent)
      VALUES (?, ?, ?, ?, ?)
    ")->execute([
      (int)$p['person_id'],
      $token_hash,
      $expires_at,
      $_SERVER['REMOTE_ADDR'] ?? null,
      substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    ]);

    $link = request_base_url() . "/password_reset.php?token=" . urlencode($token);

    error_log("PASSWORD RESET LINK for {$email}: {$link}");

    // send email
    send_reset_email($email, $link);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password – JACK</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width:520px;">
      <div class="card-body">
        <h1 class="h4 mb-1">Forgot your password?</h1>
        <p class="text-muted small mb-3">Enter your email and we'll send you a reset link.</p>

        <?php if ($msg): ?>
          <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
          <label class="form-label">Email address</label>
          <input class="form-control" name="email" type="email" value="<?= h($email) ?>" required autocomplete="username">

          <div class="d-flex align-items-center mt-3">
            <button class="btn btn-primary" type="submit">Send reset link</button>
            <a class="ms-auto small" href="/login.php">&larr; Back to sign in</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
