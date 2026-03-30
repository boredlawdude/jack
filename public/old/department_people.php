<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

function h($v): string { return htmlspecialchars((string)$v); }

$dept_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($dept_id <= 0) {
  http_response_code(400);
  echo "Missing or invalid id.";
  exit;
}

// Load department
$stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ? LIMIT 1");
$stmt->execute([$dept_id]);
$dept = $stmt->fetch();
if (!$dept) {
  http_response_code(404);
  echo "Department not found.";
  exit;
}

// Filters
$active = $_GET['active'] ?? '1';
if (!in_array($active, ['1','0','all'], true)) $active = '1';
$q = trim($_GET['q'] ?? '');

$where = ["p.department_id = ?"];
$params = [$dept_id];

if ($active !== 'all') {
  $where[] = "p.is_active = ?";
  $params[] = (int)$active;
}

if ($q !== '') {
  $where[] = "(
    p.first_name LIKE ?
    OR p.last_name LIKE ?
    OR p.email LIKE ?
    OR p.officephone LIKE ?
    OR p.cellphone LIKE ?
    OR p.`title` LIKE ?
    OR c.name LIKE ?
  )";
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
}

$sql = "
  SELECT
    p.person_id,
    p.first_name,
    p.last_name,
    p.email,
    p.officephone,
    p.cellphone,
    p.`title`,
    p.is_active,
    c.name AS company_name
  FROM people p
  LEFT JOIN companies c ON c.company_id = p.company_id
  WHERE " . implode(" AND ", $where) . "
  ORDER BY p.last_name, p.first_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$people = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto"><?= h($dept['department_name']) ?> — People</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/departments_list.php">Departments</a>
    <a class="btn btn-primary btn-sm" href="/index.php?page=people_create">New Person</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <input type="hidden" name="id" value="<?= (int)$dept_id ?>">

  <div class="col-md-6">
    <input class="form-control" name="q" placeholder="Search people in this department..."
           value="<?= h($q) ?>">
  </div>

  <div class="col-md-3">
    <select class="form-select" name="active">
      <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active only</option>
      <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Disabled only</option>
      <option value="all" <?= $active === 'all' ? 'selected' : '' ?>>All</option>
    </select>
  </div>

  <div class="col-md-3 d-grid">
    <button class="btn btn-outline-primary">Filter</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Company</th>
          <th>Email</th>
          <th>Phones</th>
          <th>Title</th>
          <th>Status</th>
          <th class="text-end">Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($people as $p): ?>
          <tr>
            <td>
              <a href="/people_edit.php?id=<?= (int)$p['person_id'] ?>">
                <?= h($p['last_name']) ?>, <?= h($p['first_name']) ?>
              </a>
            </td>

            <td><?= h($p['company_name'] ?? '') ?></td>
            <td><?= h($p['email'] ?? '') ?></td>

            <td>
              <?= h($p['officephone'] ?? '') ?>
              <?php if (!empty($p['cellphone'])): ?><br>
                <small class="text-muted"><?= h($p['cellphone']) ?></small>
              <?php endif; ?>
            </td>

            <td><?= h($p['title'] ?? '') ?></td>

            <td>
              <?php if ((int)$p['is_active'] === 1): ?>
                <span class="badge text-bg-success">Active</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Disabled</span>
              <?php endif; ?>
            </td>

            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm"
                 href="/people_edit.php?id=<?= (int)$p['person_id'] ?>">
                Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$people): ?>
          <tr><td colspan="7" class="text-muted p-3">No people found for this department.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
