<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_system_admin();
$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo "Missing id"; exit; }

$stmt = $pdo->prepare("SELECT * FROM people WHERE person_id = ? LIMIT 1");
$stmt->execute([$id]);
$person = $stmt->fetch();
if (!$person) { http_response_code(404); echo "Person not found"; exit; }

$roles = $pdo->query("SELECT role_id, role_name, role_key FROM roles WHERE is_active=1 ORDER BY role_name")->fetchAll();
$depts = $pdo->query("SELECT department_id, department_name FROM departments WHERE is_active=1 ORDER BY department_name")->fetchAll();

$errors = [];
$ok = false;

// Load current global roles
$cur = $pdo->prepare("
  SELECT r.role_id
  FROM person_roles ur JOIN roles r ON r.role_id = ur.role_id
  WHERE ur.person_id = ?
");
$cur->execute([$id]);
$current_role_ids = array_map('intval', array_column($cur->fetchAll(), 'role_id'));

// Load current dept roles
$cur2 = $pdo->prepare("
  SELECT department_id, role_id
  FROM person_department_roles
  WHERE person_id = ?
");
$cur2->execute([$id]);
$current_udr = $cur2->fetchAll(); // rows

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim(strtolower($_POST['email'] ?? ''));
  $full_name = trim($_POST['full_name'] ?? '');
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  $role_ids = $_POST['role_ids'] ?? [];
  if (!is_array($role_ids)) $role_ids = [];

  // Dept roles: arrays keyed by dept_id => [role_id,...]
  $dept_contract_admin = $_POST['dept_contract_admin'] ?? [];
  if (!is_array($dept_contract_admin)) $dept_contract_admin = [];
  $dept_admin = $_POST['dept_admin'] ?? [];
  if (!is_array($dept_admin)) $dept_admin = [];

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if ($full_name === '') $errors[] = "Full name is required.";

  // Unique email excluding self
  if (!$errors) {
    $chk = $pdo->prepare("SELECT person_id FROM people WHERE email = ? AND person_id <> ? LIMIT 1");
    $chk->execute([$email, $id]);
    if ($chk->fetch()) $errors[] = "Email already exists.";
  }

  if (!$errors) {
    $pdo->beginTransaction();

    // Update user
    $up = $pdo->prepare("UPDATE people SET email=?, full_name=?, is_active=? WHERE person_id=?");
    $up->execute([$email, $full_name, $is_active, $id]);

    // Replace global roles
    $pdo->prepare("DELETE FROM person_roles WHERE person_id=?")->execute([$id]);
    $ins = $pdo->prepare("INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)");
    foreach ($role_ids as $rid) {
      if (ctype_digit((string)$rid)) $ins->execute([$id, (int)$rid]);
    }

    // Replace dept roles
    $pdo->prepare("DELETE FROM person_department_roles WHERE person_id=?")->execute([$id]);
    $ins2 = $pdo->prepare("INSERT INTO person_department_roles (person_id, department_id, role_id) VALUES (?, ?, ?)");

    // Find role_ids for dept keys
    $roleMap = [];
    foreach ($roles as $r) $roleMap[$r['role_key']] = (int)$r['role_id'];
    $RID_DEPT_CONTRACT_ADMIN = $roleMap['DEPT_CONTRACT_ADMIN'] ?? 0;
    $RID_DEPT_ADMIN = $roleMap['DEPT_ADMIN'] ?? 0;

    foreach ($dept_contract_admin as $deptIdStr => $on) {
      if ($RID_DEPT_CONTRACT_ADMIN && ctype_digit((string)$deptIdStr) && $on === '1') {
        $ins2->execute([$id, (int)$deptIdStr, $RID_DEPT_CONTRACT_ADMIN]);
      }
    }
    foreach ($dept_admin as $deptIdStr => $on) {
      if ($RID_DEPT_ADMIN && ctype_digit((string)$deptIdStr) && $on === '1') {
        $ins2->execute([$id, (int)$deptIdStr, $RID_DEPT_ADMIN]);
      }
    }

    $pdo->commit();
    $ok = true;

    // Reload
    $stmt = $pdo->prepare("SELECT * FROM people WHERE person_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    $cur->execute([$id]);
    $current_role_ids = array_map('intval', array_column($cur->fetchAll(), 'role_id'));

    $cur2->execute([$id]);
    $current_udr = $cur2->fetchAll();
  }
}

function has_udr(array $current_udr, int $dept_id, int $role_id): bool {
  foreach ($current_udr as $r) {
    if ((int)$r['department_id'] === $dept_id && (int)$r['role_id'] === $role_id) return true;
  }
  return false;
}

// build role_key => role_id map
$roleMap = [];
foreach ($roles as $r) $roleMap[$r['role_key']] = (int)$r['role_id'];
$RID_DEPT_CONTRACT_ADMIN = $roleMap['DEPT_CONTRACT_ADMIN'] ?? 0;
$RID_DEPT_ADMIN = $roleMap['DEPT_ADMIN'] ?? 0;

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Edit User</h1>
  <a class="btn btn-outline-secondary btn-sm" href="/people_list.php">Back to People</a>
</div>

<?php if ($ok): ?><div class="alert alert-success py-2">Saved.</div><?php endif; ?>
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
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" value="<?= h($person['email']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Full Name</label>
          <input name="full_name" class="form-control" value="<?= h($person['full_name']) ?>">
        </div>

        <div class="col-md-6">
          <div class="form-check mt-4 pt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
              <?= ((int)$person['is_active'] === 1) ? 'checked' : '' ?>>
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
                         <?= in_array((int)$r['role_id'], $current_role_ids, true) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= h($r['role_name']) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Department Roles</label>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Department</th>
                  <th>Dept Contract Admin</th>
                  <th>Dept Administrator</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($depts as $d): ?>
                  <?php $did = (int)$d['department_id']; ?>
                  <tr>
                    <td><?= h($d['department_name']) ?></td>
                    <td>
                      <?php if ($RID_DEPT_CONTRACT_ADMIN): ?>
                        <input type="checkbox" name="dept_contract_admin[<?= $did ?>]" value="1"
                          <?= has_udr($current_udr, $did, $RID_DEPT_CONTRACT_ADMIN) ? 'checked' : '' ?>>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($RID_DEPT_ADMIN): ?>
                        <input type="checkbox" name="dept_admin[<?= $did ?>]" value="1"
                          <?= has_udr($current_udr, $did, $RID_DEPT_ADMIN) ? 'checked' : '' ?>>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="form-text">
            Dept roles apply only to selected departments.
          </div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Save User</button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
