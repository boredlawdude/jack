<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();




// Filters
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$department_id = $_GET['department_id'] ?? 'all';
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

$allowed_statuses = ['all','draft','in_review','signed','expired','terminated'];
if (!in_array($status, $allowed_statuses, true)) $status = 'all';

if ($department_id !== 'all') {
  $department_id = trim((string)$department_id);
  if ($department_id !== '' && (!ctype_digit($department_id) || (int)$department_id <= 0)) {
    $department_id = 'all';
  }
}

// Load departments dropdown
$departments = $pdo->query("
  SELECT department_id, department_name
  FROM departments
  WHERE is_active = 1
  ORDER BY department_name
")->fetchAll();

// Build query
$where = [];
$params = [];

if ($status !== 'all') {
  $where[] = "ct.status = ?";
  $params[] = $status;
}

if ($department_id !== 'all') {
  $where[] = "ct.department_id = ?";
  $params[] = (int)$department_id;
}


if ($company_id) {
  $where[] = "(ct.owner_company_id = ? OR ct.counterparty_company_id = ?)";
  $params[] = $company_id;
  $params[] = $company_id;
}

if ($q !== '') {
  $where[] = "(
    ct.contract_number LIKE ?
    OR ct.name LIKE ?
    OR ct.description LIKE ?
    OR owner.name LIKE ?
    OR counterparty.name LIKE ?
    OR d.department_name LIKE ?
  )";
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like,$like,$like,$like,$like,$like]);
}

$sql = "
  SELECT
    ct.contract_id,
    ct.contract_number,
    ct.name,
    ct.status,
    ct.start_date,
    ct.end_date,
    ct.total_contract_value,
    ct.currency,
    d.department_name,
    owner.name AS owner_company,
    counterparty.name AS counterparty_company
  FROM contracts ct
  LEFT JOIN departments d ON d.department_id = ct.department_id
  JOIN companies owner ON owner.company_id = ct.owner_company_id
  JOIN companies counterparty ON counterparty.company_id = ct.counterparty_company_id
";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ct.updated_at DESC, ct.contract_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require APP_ROOT . '/app/views/layouts/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Contracts</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="/contract_create_full.php">New Contract</a>
    <a class="btn btn-outline-secondary btn-sm" href="/departments_list.php">Departments</a>
    <a class="btn btn-outline-secondary btn-sm" href="/people_list.php">People</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-5">
    <input class="form-control" name="q"
           placeholder="Search contract #, name, company, department..."
           value="<?= h($q) ?>">
  </div>

  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
      <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>draft</option>
      <option value="in_review" <?= $status === 'in_review' ? 'selected' : '' ?>>in_review</option>
      <option value="signed" <?= $status === 'signed' ? 'selected' : '' ?>>signed</option>
      <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>expired</option>
      <option value="terminated" <?= $status === 'terminated' ? 'selected' : '' ?>>terminated</option>
    </select>
  </div>

  <div class="col-md-3">
    <select class="form-select" name="department_id">
      <option value="all" <?= $department_id === 'all' ? 'selected' : '' ?>>(All departments)</option>
      <?php foreach ($departments as $d): ?>
        <?php $did = (string)$d['department_id']; ?>
        <option value="<?= (int)$d['department_id'] ?>"
          <?= ($department_id !== 'all' && (string)$department_id === $did) ? 'selected' : '' ?>>
          <?= h($d['department_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-1 d-grid">
    <button class="btn btn-outline-primary">Go</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Contract #</th>
          <th>Name</th>
          <th>Department</th>
          <th>Status</th>
          
          <th>Counterparty</th>
          <th>End Date</th>
          <th class="text-end">Value</th>
          <th class="text-end">Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="/contract_edit.php?id=<?= (int)$r['contract_id'] ?>">
                <?= h($r['contract_number']) ?>
              </a>
            </td>

            <td><?= h($r['name']) ?></td>

            <td><?= h($r['department_name'] ?? '') ?></td>

            <td>
              <?php
                $st = (string)$r['status'];
                $badge = 'text-bg-secondary';
                if ($st === 'draft') $badge = 'text-bg-secondary';
                if ($st === 'in_review') $badge = 'text-bg-info';
                if ($st === 'signed') $badge = 'text-bg-success';
                if ($st === 'expired') $badge = 'text-bg-warning';
                if ($st === 'terminated') $badge = 'text-bg-dark';
              ?>
              <span class="badge <?= $badge ?>"><?= h($st) ?></span>
            </td>

            
            <td><?= h($r['counterparty_company']) ?></td>

            <td><?= h($r['end_date'] ?? '') ?></td>

            <td class="text-end">
              <?php if (!empty($r['total_contract_value'])): ?>
                <?= h($r['currency'] ?? 'USD') ?> <?= number_format((float)$r['total_contract_value'], 2) ?>
              <?php endif; ?>
            </td>

            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm"
                 href="/contract_edit.php?id=<?= (int)$r['contract_id'] ?>">
                Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-muted p-3">No contracts found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>
