<?php
declare(strict_types=1);
// Force errors on for debugging (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



require_once __DIR__ . '/../includes/init.php';
require_login();


$pdo = db();

// --- Filters & Search ---
$search = trim($_GET['q'] ?? '');
$active = $_GET['active'] ?? '1';
if (!in_array($active, ['1','0','all'], true)) $active = '1';

$params = [];
$where  = [];

if ($active !== 'all') {
    $where[] = "p.is_active = ?";
    $params[] = (int)$active;
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = "(
        p.first_name LIKE ? 
        OR p.last_name LIKE ? 
        OR p.email LIKE ? 
        OR p.officephone LIKE ? 
        OR p.cellphone LIKE ? 
        OR p.title LIKE ? 
        OR COALESCE(d.department_name, '') LIKE ?
    )";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

// Safe SQL with JOIN
$sql = "
    SELECT 
        p.person_id,
        p.first_name,
        p.last_name,
        p.email,
        p.officephone,
        p.cellphone,
        p.title,
        p.is_active,
        d.department_name
    FROM people p
    LEFT JOIN departments d 
        ON d.department_id = p.department_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.last_name, p.first_name";

// Execute with try/catch
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Show error clearly
    include __DIR__ . '/../app/views/layouts/header.php';
    echo '<div class="container mt-5">';
    echo '<div class="alert alert-danger">';
    echo '<h4>Database Query Failed</h4>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>SQL:</strong><br><pre>' . htmlspecialchars($sql) . '</pre></p>';
    echo '<p><strong>Params:</strong><br><pre>' . print_r($params, true) . '</pre></p>';
    echo '</div></div>';
    include __DIR__ . '/../app/views/layouts/footer.php';
    exit;
}

include __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="d-flex align-items-center mb-4">
        <h1 class="h4 me-auto">People</h1>
        <a class="btn btn-primary btn-sm" href="/person_create.php">New Person</a>
    </div>

    <!-- Search & Filter -->
    <form method="get" class="mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="q" 
                       value="<?= h($search) ?>" placeholder="Search name, email, title, department...">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="active">
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active Only</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive Only</option>
                    <option value="all" <?= $active === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No people found. <?= !empty($search) ? 'Try a different search.' : '' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= h(trim($r['last_name'] . ', ' . $r['first_name'])) ?></strong>
                                </td>
                                <td><?= h($r['title'] ?: '-') ?></td>
                                <td><?= h($r['email'] ?: '-') ?></td>
                                <td><?= h($r['officephone'] ?: $r['cellphone'] ?: '-') ?></td>
                                <td>
                                    <?= $r['department_name'] 
                                        ? h($r['department_name']) 
                                        : '<span class="text-muted">None assigned</span>' ?>
                                </td>
                                <td>
                                    <?php if ((int)$r['is_active'] === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" 
                                       href="/person_edit.php?id=<?= (int)$r['person_id'] ?>">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>