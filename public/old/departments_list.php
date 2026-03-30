<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();

$q = trim($_GET['q'] ?? '');
$params = [];
$where = [];

if ($q !== '') {
  $where[] = "(d.department_name LIKE ? OR d.department_code LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
}

$sql = "
  SELECT
    d.department_id,
    d.department_code,
    d.department_name,
    d.is_active,

    -- Count ACTIVE people in this department
    COUNT(DISTINCT p.person_id) AS people_count,

    -- Count contracts assigned to this department
    COUNT(DISTINCT ct.contract_id) AS contracts_count

  FROM departments d

  LEFT JOIN people p
    ON p.department_id = d.department_id
    AND p.is_active = 1

  LEFT JOIN contracts ct
    ON ct.department_id = d.department_id
";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
  GROUP BY d.department_id, d.department_code, d.department_name, d.is_active
  ORDER BY d.department_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/header.php';
function h($v): string { return htmlspecialchars((string)$v); }
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Departments</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/people_list.php">People</a>
    <a class="btn btn-outline-secondary btn-sm" href="/contracts_list.php">Contracts</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-8">
    <input class="form-control" name="q" placeholder="Search department name or code..."
           value="<?= h($q) ?>">
  </div>
  <div class="col-md-4 d-grid">
    <button class="btn btn-outline-primary">Search</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Department</th>
          <th>Code</th>
          <th>Status</th>
          <th>Active People</th>
          <th>Contracts</th>
          <th class="text-end">View</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="/department_edit.php?id=<?= (int)$r['department_id'] ?>">
                <?= h($r['department_name']) ?>
              </a>
            </td>

            <td><span class="text-muted"><?= h($r['department_code']) ?></span></td>

            <td>
              <?php if ((int)$r['is_active'] === 1): ?>
                <span class="badge text-bg-success">Active</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Inactive</span>
              <?php endif; ?>
            </td>

            <td><?= (int)$r['people_count'] ?></td>
            <td><a href="/contracts_list.php?department_id=<?= (int)$r['department_id'] ?>">
                <?= (int)$r['contracts_count'] ?>
                </a>
            </td>


            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm"
                 href="/department_people.php?id=<?= (int)$r['department_id'] ?>">
                People →
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-muted p-3">No departments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
