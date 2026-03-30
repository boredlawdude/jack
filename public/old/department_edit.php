<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';


require_login();

$pdo = pdo();

function h($v): string { return htmlspecialchars((string)$v); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "Missing or invalid id.";
  exit;
}

// Load department
$stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ? LIMIT 1");
$stmt->execute([$id]);
$dept = $stmt->fetch();
if (!$dept) {
  http_response_code(404);
  echo "Department not found.";
  exit;
}

// People dropdown options (active only)
$people = $pdo->query("
  SELECT
    person_id,
    CONCAT(last_name, ', ', first_name) AS display_name
  FROM people
  WHERE is_active = 1
  ORDER BY last_name, first_name
")->fetchAll();

$errors = [];
$ok = false;

// Optional delete (usually you may prefer to set is_active=0 instead)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!is_admin()) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }

  $del = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
  $del->execute([$id]);
  header("Location: /departments_list.php");
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  $department_code = trim($_POST['department_code'] ?? '');
  $department_name = trim($_POST['department_name'] ?? '');
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $notes = trim($_POST['notes'] ?? '');

  $department_head_id = trim($_POST['department_head_id'] ?? '');
  $town_manager_id = trim($_POST['town_manager_id'] ?? '');
  $contract_admin_id = trim($_POST['contract_admin_id'] ?? '');


  if ($department_code === '') $errors[] = "Department code is required.";
  if ($department_name === '') $errors[] = "Department name is required.";

  // Normalize IDs
  if ($department_head_id !== '' && (!ctype_digit($department_head_id) || (int)$department_head_id <= 0)) {
    $errors[] = "Department head must be blank or a valid person.";
  }
  if ($town_manager_id !== '' && (!ctype_digit($town_manager_id) || (int)$town_manager_id <= 0)) {
    $errors[] = "Town manager must be blank or a valid person.";
  } 
    if ($contract_admin_id !== '' && (!ctype_digit($contract_admin_id) || (int)$contract_admin_id <= 0)) {
  $errors[] = "Contract Administrator must be blank or a valid person.";

  }

  // Ensure code unique (excluding self)
  if (!$errors) {
    $chk = $pdo->prepare("
      SELECT department_id
      FROM departments
      WHERE department_code = ?
        AND department_id <> ?
      LIMIT 1
    ");
    $chk->execute([$department_code, $id]);
    if ($chk->fetch()) $errors[] = "That department code already exists.";
  }

  if (!$errors) {
    $up = $pdo->prepare("
      UPDATE departments SET
        department_code = ?,
        department_name = ?,
        is_active = ?,
        notes = ?,
        department_head_id = ?,
        town_manager_id = ?
        contract_admin_id = ?
      WHERE department_id = ?
    ");

    $up->execute([
      $department_code,
      $department_name,
      $is_active,
      ($notes === '' ? null : $notes),
      ($department_head_id === '' ? null : (int)$department_head_id),
      ($town_manager_id === '' ? null : (int)$town_manager_id),
      ($contract_admin_id === '' ? null : (int)$contract_admin_id),
      $id
    ]);

    $ok = true;

    // Reload
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $dept = $stmt->fetch();
  }
}

include __DIR__ . '/header.php';

$current_head  = (string)($dept['department_head_id'] ?? '');
$current_mgr   = (string)($dept['town_manager_id'] ?? '');
$current_admin = (string)($dept['contract_admin_id'] ?? '');

?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Edit Department</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/departments_list.php">Back to Departments</a>
    <a class="btn btn-outline-secondary btn-sm" href="/department_people.php?id=<?= (int)$id ?>">People →</a>
  </div>
</div>

<?php if ($ok): ?>
  <div class="alert alert-success py-2">Saved.</div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="save">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Department Code *</label>
          <input name="department_code" class="form-control" required value="<?= h($dept['department_code']) ?>">
        </div>

        <div class="col-md-8">
          <label class="form-label">Department Name *</label>
          <input name="department_name" class="form-control" required value="<?= h($dept['department_name']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Department Head</label>
          <select name="department_head_id" class="form-select">
            <option value="">(none)</option>
            <?php foreach ($people as $p): ?>
              <?php $pid = (string)$p['person_id']; ?>
              <option value="<?= (int)$p['person_id'] ?>" <?= $current_head === $pid ? 'selected' : '' ?>>
                <?= h($p['display_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Contract Administrator</label>
            <select name="contract_admin_id" class="form-select">
                <option value="">(none)</option>
                <?php foreach ($people as $p): ?>
                <?php $pid = (string)$p['person_id']; ?>
                <option value="<?= (int)$p['person_id'] ?>"
                    <?= $current_admin === $pid ? 'selected' : '' ?>>
                    <?= h($p['display_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        <div class="form-text">
        Primary staff responsible for contract administration for this department.
        </div>
</div>

        <div class="col-md-6">
          <label class="form-label">Town Manager</label>
          <select name="town_manager_id" class="form-select">
            <option value="">(none)</option>
            <?php foreach ($people as $p): ?>
              <?php $pid = (string)$p['person_id']; ?>
              <option value="<?= (int)$p['person_id'] ?>" <?= $current_mgr === $pid ? 'selected' : '' ?>>
                <?= h($p['display_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">You can set this per-department or leave blank if you prefer it global.</div>
        </div>

        <div class="col-md-6">
          <div class="form-check mt-4 pt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   <?= ((int)$dept['is_active'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="4"><?= h($dept['notes'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>


<?php if (is_system_admin()): ?>
  <form method="post" onsubmit="return confirm('Delete this department? This cannot be undone.');">
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger">Delete Department</button>
  </form>
<?php endif; ?>



<p class="text-muted small mt-3">
  Created: <?= h($dept['created_at']) ?> • Updated: <?= h($dept['updated_at']) ?>
</p>

<?php include __DIR__ . '/footer.php'; ?>
