<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$is_super = person_has_role_key('SUPERUSER');

// Departments dropdown
$departments = $pdo->query("
  SELECT department_id, department_name
  FROM departments
  ORDER BY department_name
")->fetchAll(PDO::FETCH_ASSOC);

// Roles for checkboxes (superuser only)
$roles = $pdo->query("
  SELECT role_id, role_key, role_name
  FROM roles
  WHERE is_active = 1
  ORDER BY COALESCE(role_name, role_key), role_key
")->fetchAll(PDO::FETCH_ASSOC);

// Form defaults
$first_name = '';
$last_name = '';
$full_name = '';
$email = '';
$officephone = '';
$cellphone = '';
$title = '';
$department_id = '';
$can_login = 0;
$is_active = 1;
$password = '';
$password2 = '';
$role_ids = []; // selected role ids (superuser only)

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $full_name  = trim($_POST['full_name'] ?? '');
  $email      = trim(strtolower($_POST['email'] ?? ''));
  $officephone = trim($_POST['officephone'] ?? '');
  $cellphone   = trim($_POST['cellphone'] ?? '');
  $title       = trim($_POST['title'] ?? '');
  $department_id = trim($_POST['department_id'] ?? '');

  $can_login = isset($_POST['can_login']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  $password  = (string)($_POST['password'] ?? '');
  $password2 = (string)($_POST['password2'] ?? '');   
  $is_town_employee = isset($_POST['is_town_employee']) ? 1 : 0;

  $department_id = trim((string)($_POST['department_id'] ?? ''));
  $department_id_db = null;

  if ($is_town_employee === 1 && $department_id !== '' && ctype_digit($department_id) && (int)$department_id > 0) {
  $department_id_db = (int)$department_id;
}

  // Roles (superuser only)
  if ($is_super) {
    $role_ids = $_POST['role_ids'] ?? [];
    if (!is_array($role_ids)) $role_ids = [];
    $role_ids = array_values(array_filter($role_ids, fn($x) => ctype_digit((string)$x)));
    $role_ids = array_map('intval', $role_ids);

    // intersect with real roles
    $valid_role_ids = array_map(fn($r) => (int)$r['role_id'], $roles);
    $role_ids = array_values(array_intersect($role_ids, $valid_role_ids));
  } else {
    $role_ids = [];
  }

  // Name validation: Full Name OR First+Last
  if ($full_name === '' && ($first_name === '' || $last_name === '')) {
    $errors[] = "Enter Full Name, or First + Last name.";
  }

  // Department validation
  if ($department_id !== '' && (!ctype_digit($department_id) || (int)$department_id <= 0)) {
    $errors[] = "Department must be blank or a valid department.";
  }

  // Email/password validation
  if ($can_login === 1) {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = "A valid email is required for login-enabled people.";
    }
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if ($password !== $password2) $errors[] = "Passwords do not match.";
  } else {
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = "Email is not valid.";
    }
  }

  // Duplicate email (only if provided)
  if (!$errors && $email !== '') {
    $dup = $pdo->prepare("SELECT 1 FROM people WHERE email = ? LIMIT 1");
    $dup->execute([$email]);
    if ($dup->fetchColumn()) {
      $errors[] = "That email is already in use.";
    }
  }

  if (!$errors) {
    $hash = null;
    if ($can_login === 1) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
    }

    $pdo->beginTransaction();

    $ins = $pdo->prepare("
      INSERT INTO people
        (first_name, last_name, full_name, email, officephone, cellphone, title, department_id,
         can_login, is_active, password_hash)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
      ($first_name === '' ? null : $first_name),
      ($last_name  === '' ? null : $last_name),
      ($full_name  === '' ? null : $full_name),
      ($email      === '' ? null : $email),
      ($officephone === '' ? null : $officephone),
      ($cellphone  === '' ? null : $cellphone),
      ($title      === '' ? null : $title),
      ($department_id === '' ? null : (int)$department_id),
      $can_login,
      $is_active,
      $hash
    ]);

    $new_person_id = (int)$pdo->lastInsertId();

    // Assign roles (superuser only)
    if ($is_super && !empty($role_ids)) {
      $rins = $pdo->prepare("INSERT IGNORE INTO person_roles (person_id, role_id) VALUES (?, ?)");
      foreach ($role_ids as $rid) {
        $rins->execute([$new_person_id, (int)$rid]);
      }
    }

    $pdo->commit();

    header("Location: /people_list.php");
    exit;
  }
}

include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">Create Person</h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <div class="row g-3">

      <div class="col-md-4">
        <label class="form-label">First Name</label>
        <input class="form-control" name="first_name" value="<?= h($first_name) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Last Name</label>
        <input class="form-control" name="last_name" value="<?= h($last_name) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="full_name" value="<?= h($full_name) ?>">
        <div class="form-text">Use Full Name OR First/Last.</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" value="<?= h($email) ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Office Phone</label>
        <input class="form-control" name="officephone" value="<?= h($officephone) ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Cell Phone</label>
        <input class="form-control" name="cellphone" value="<?= h($cellphone) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Title</label>
        <input class="form-control" name="title" value="<?= h($title) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Department</label>
        <select class="form-select" name="department_id">
          <option value="">(none)</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['department_id'] ?>"
              <?= ((string)$department_id === (string)$d['department_id']) ? 'selected' : '' ?>>
              <?= h($d['department_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="can_login" name="can_login" <?= $can_login ? 'checked' : '' ?>>
          <label class="form-check-label" for="can_login">Can Login</label>
        </div>

        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $is_active ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active">Active</label>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Password (required if Can Login)</label>
        <input class="form-control" type="password" name="password" autocomplete="new-password">
      </div>

      <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input class="form-control" type="password" name="password2" autocomplete="new-password">
      </div>

  <?php
$isTown = (int)($person['is_town_employee'] ?? 0); // for create page use $form[...] default
$deptId = (string)($person['department_id'] ?? ''); // for create page use $form[...]
?>

<div class="col-md-4">
  <div class="form-check mt-4">
    <input class="form-check-input" type="checkbox" id="is_town_employee" name="is_town_employee" value="1"
      <?= $isTown === 1 ? 'checked' : '' ?>>
    <label class="form-check-label" for="is_town_employee">
      Town employee
    </label>
  </div>
  <div class="form-text">If checked, Department can be selected.</div>
</div>

<div class="col-md-8" id="dept_wrap">
  <label class="form-label">Department</label>
  <select class="form-select" name="department_id" id="department_id">
    <option value="">(none)</option>
    <?php foreach ($departments as $d): ?>
      <option value="<?= (int)$d['department_id'] ?>"
        <?= ($deptId !== '' && (int)$deptId === (int)$d['department_id']) ? 'selected' : '' ?>>
        <?= h($d['department_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<script>
(function () {
  const cb = document.getElementById('is_town_employee');
  const wrap = document.getElementById('dept_wrap');
  const sel = document.getElementById('department_id');

  function sync() {
    const on = cb.checked;
    wrap.style.display = on ? '' : 'none';
    sel.disabled = !on;
    if (!on) sel.value = '';
  }

  cb.addEventListener('change', sync);
  sync();
})();
</script>
      <?php if ($is_super): ?>
        <div class="col-12">
          <div class="card mt-2">
            <div class="card-header fw-semibold">Roles (Superuser only)</div>
            <div class="card-body">
              <div class="row">
                <?php foreach ($roles as $r): $rid = (int)$r['role_id']; ?>
                  <div class="col-md-4">
                    <label class="form-check">
                      <input class="form-check-input" type="checkbox" name="role_ids[]" value="<?= $rid ?>"
                        <?= in_array($rid, $role_ids, true) ? 'checked' : '' ?>>
                      <span class="form-check-label">
                        <?= h($r['role_name'] ?? $r['role_key']) ?>
                        <span class="text-muted small">(<?= h($r['role_key']) ?>)</span>
                      </span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="text-muted small mt-2">Only Superusers can assign roles.</div>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit">Create</button>
    <a class="btn btn-outline-secondary" href="/people_list.php">Cancel</a>
  </div>



</form>

<?php include __DIR__ . '/footer.php'; ?>
