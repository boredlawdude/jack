<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$email = trim(strtolower($_POST['email'] ?? ''));
$next  = (string)($_GET['next'] ?? $_POST['next'] ?? '/index.php?page=contracts');
$errors = [];

function safe_next_local(string $next, string $fallback = '/index.php?page=contracts'): string {
  $next = trim($next);
  if ($next === '') return $fallback;
  if (strpos($next, '/') === 0 && strpos($next, '//') !== 0) return $next;
  return $fallback;
}

if (current_person()) {
  header("Location: /contracts_list.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pw = (string)($_POST['password'] ?? '');

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Enter a valid email address.";
  } elseif ($pw === '') {
    $errors[] = "Enter your password.";
  } else {
    $login_result = login_person($email, $pw);
    if ($login_result) {
      session_write_close();
      header("Location: " . safe_next_local($next, '/index.php?page=contracts'));
      exit;
    }
    $errors[] = "Invalid email or password.";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width:520px;">
      <div class="card-body">
        <h1 class="h4 mb-3">Sign in to JACK </h1>
        <h2 class="h4 mb-3">(John's Awesome Contract Knowledgebase)</h2>
        <?php if ($errors): ?>
          <div class="alert alert-danger"><ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul></div>
        <?php endif; ?>

        <form method="post" action="/login.php" autocomplete="on">
          <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($email) ?>" autocomplete="username">

          <label class="form-label mt-3">Password</label>
          <input class="form-control" type="password" name="password" required autocomplete="current-password">

          <div class="d-flex align-items-center mt-3">
            <button class="btn btn-primary" type="submit">Sign in</button>
            <a class="ms-auto small" href="/password_reset_request.php">Forgot your password?</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
