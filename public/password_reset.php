<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$pdo = pdo();

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$ok = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(400);
  exit("Invalid token.");
}

$token_hash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT password_reset_id, person_id, expires_at, used_at
  FROM password_resets
  WHERE token_hash = ?
  LIMIT 1
");
$stmt->execute([$token_hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(400);
  exit("Invalid or expired reset link.");
}

if ($row['used_at'] !== null) {
  http_response_code(400);
  exit("This reset link has already been used.");
}

if (new DateTime() > new DateTime($row['expires_at'])) {
  http_response_code(400);
  exit("This reset link has expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pw1 = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');

  if (strlen($pw1) < 8) $errors[] = "Password must be at least 8 characters.";
  if ($pw1 !== $pw2) $errors[] = "Passwords do not match.";

  if (!$errors) {
    $hash = password_hash($pw1, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $up = $pdo->prepare("UPDATE people SET password_hash = ? WHERE person_id = ?");
    $up->execute([$hash, (int)$row['person_id']]);

    $use = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE password_reset_id = ?");
    $use->execute([(int)$row['password_reset_id']]);

    $pdo->commit();

    $ok = true;
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set New Password – JACK</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width:520px;">
      <div class="card-body">
        <h1 class="h4 mb-1">Set new password</h1>
        <p class="text-muted small mb-3">Choose a new password for your account.</p>

        <?php if ($ok): ?>
          <div class="alert alert-success">
            Password updated. <a href="/login.php">Sign in</a>.
          </div>
        <?php else: ?>

          <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0">
              <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul></div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <label class="form-label">New password</label>
            <input class="form-control" type="password" name="password" required minlength="8">

            <label class="form-label mt-3">Confirm new password</label>
            <input class="form-control" type="password" name="password2" required minlength="8">

            <div class="d-flex align-items-center mt-3">
              <button class="btn btn-primary" type="submit">Update password</button>
              <a class="ms-auto small" href="/login.php">&larr; Back to sign in</a>
            </div>
          </form>

        <?php endif; ?>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
