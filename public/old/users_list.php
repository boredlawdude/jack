<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_system_admin();
$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v); }

$q = trim($_GET['q'] ?? '');
$params = [];
$where = [];

if ($q !== '') {
  $where[] = "(u.email LIKE ? OR u.full_name LIKE ?)";
  $like = '%' . $q . '%';
  $params = [$like, $like];
}

$sql = "
  SELECT u.user_id, u.email, u.full_name, u.role, u.is_active, u.created_at
  FROM users u
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY u.user_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Users</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="/user_create.php">New User</a>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-9">
    <input class="form-control" name="q" placeholder="Search email or name..." value="<?= h($q) ?>">
  </div>
  <div class="col-md-3 d-grid">
    <button class="btn btn-outline-primary">Search</button>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Email</th>
          <th>Name</th>
          <th>Old Role</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['email']) ?></td>
            <td><?= h($r['full_name']) ?></td>
            <td><span class="text-muted"><?= h($r['role']) ?></span></td>
            <td>
              <?php if ((int)$r['is_active'] === 1): ?>
                <span class="badge text-bg-success">Active</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Disabled</span>
              <?php endif; ?>
            </td>
            <td><?= h($r['created_at']) ?></td>
            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm" href="/user_edit.php?id=<?= (int)$r['user_id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-muted p-3">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
