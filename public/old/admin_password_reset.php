<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

// Require superuser/admin
$me = current_person();
$role = (string)($me['role'] ?? '');
if (!in_array($role, ['superuser', 'admin'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim(strtolower((string)($_POST['email'] ?? '')));
  $pw1 = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if (strlen($pw1) < 8) $errors[] = "Password must be at least 8 characters.";
  if ($pw1 !== $pw2) $errors[] = "Passwords do not match.";

  if (!$errors) {
    $hash = password_hash($pw1, PASSWORD_DEFAULT);

    $st = $pdo->prepare("UPDATE people SET password_hash=? WHERE email=? LIMIT 1");
    $st->execute([$hash, $email]);

    if ($st->rowCount() < 1) {
      $errors[] = "No person found with that email.";
    } else {
      $ok = true;
    }
  }
}

include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">Admin Password Reset</h1>

<?php if ($ok): ?>
  <div class="alert alert-success">Password updated.</div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">User email</label>
      <input class="form-control" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">New password</label>
      <input class="form-control" type="password" name="password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Confirm new password</label>
      <input class="form-control" type="password" name="password2" required>
    </div>

    <button class="btn btn-primary">Reset Password</button>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
