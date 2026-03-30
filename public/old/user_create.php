<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_system_admin();
$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v); }

$errors = [];
$ok = false;

$email = '';
$full_name = '';
$password = '';
$is_active = 1;
$role_ids = [];

$roles = $pdo->query("
  SELECT role_id, role_name, role_key
  FROM roles
  WHERE is_active = 1
  ORDER BY role_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim(strtolower($_POST['email'] ?? ''));
  $full_name = trim($_POST['full_name'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $role_ids = $_POST['role_ids'] ?? [];
  if (!is_array($role_ids)) $role_ids = [];

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if ($full_name === '') $errors[] = "Full name is required.";
  if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";

  // Ensure unique email
  if (!$errors) {
    $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) $errors[] = "Email already exists.";
  }

  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Keep legacy users.role as 'user' by default (does not control new roles)
    $stmt = $pdo->prepare("
      INSERT INTO users (email, password_hash, full_name, role, is_active)
      VALUES (?, ?, ?, 'user', ?)
    ");
    $stmt->execute([$email, $hash, $full_name, $is_active]);
    $new_user_id = (int)$pdo->lastInsertId();

    // Assign selected global roles
    $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    foreach ($role_ids as $rid) {
      if (ctype_digit((string)$rid)) $ins->execute([$new_user_id, (int)$rid]);
    }

    $ok = true;
    $email = $full_name = $password = '';
    $is_active = 1;
    $role_ids = [];
  }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">New User</h1>
  <a class="btn btn-outline-secondary btn-sm" href="/users_list.php">Back to Users</a>
</div>

<?php if ($ok): ?><div class="alert alert-success py-2">User created.</div><?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Email *</label>
          <input name="email" type="email" class="form-control" required value="<?= h($email) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Full Name *</label>
          <input name="full_name" class="form-control" required value="<?= h($full_name) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <input name="password" type="password" class="form-control" required>
          <div class="form-text">At least 8 characters.</div>
        </div>

        <div class="col-md-6">
          <div class="form-check mt-4 pt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $is_active ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Global Roles (User Types)</label>
          <div class="row">
            <?php foreach ($roles as $r): ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="role_ids[]"
                         value="<?= (int)$r['role_id'] ?>"
                         <?= in_array((string)$r['role_id'], array_map('strval', $role_ids), true) ? 'checked' : '' ?>>
                  <label class="form-check-label">
                    <?= h($r['role_name']) ?> <span class="text-muted">(<?= h($r['role_key']) ?>)</span>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text">Dept roles (like Dept Contract Admin) are assigned on the user edit page.</div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Create User</button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
