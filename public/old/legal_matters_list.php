<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }

// Legal-only restriction (keep your existing code here)

// ────────────────────────────────────────────────
// Sorting
// ────────────────────────────────────────────────
$sort_column = $_GET['sort'] ?? 'date_started';
$sort_order  = strtoupper($_GET['order'] ?? 'DESC');

$allowed_sort_columns = ['file_number', 'matter_name', 'status', 'date_started'];

if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'date_started';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

$order_by = "ORDER BY $sort_column $sort_order";
if ($sort_column !== 'date_started') {
    $order_by .= ", date_started DESC";
}

// ────────────────────────────────────────────────
// Filters + Search
// ────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$dept_id = (int)($_GET['dept'] ?? 0);

$where  = [];
$params = [];

if ($search !== '') {
    $like = "%$search%";
    $where[] = "(lm.file_number LIKE ? OR lm.matter_name LIKE ? OR lm.matter_long_description LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '' && $status !== 'all') {
    $where[] = "lm.status = ?";
    $params[] = $status;
}

if ($dept_id > 0) {
    $where[] = "lm.department_id = ?";
    $params[] = $dept_id;
}

// ────────────────────────────────────────────────
// Build query
// ────────────────────────────────────────────────
$sql = "
    SELECT 
        lm.id,
        lm.file_number,
        lm.matter_name,
        lm.matter_type,
        lm.assigned_to,
        d.department_name,
        CONCAT(p.first_name, ' ', p.last_name) AS requested_by_name,
        lm.status,
        DATE_FORMAT(lm.date_started, '%m/%d/%Y') AS date_started_fmt,
        LEFT(lm.matter_long_description, 120) AS short_desc
    FROM legal_matters lm
    LEFT JOIN departments d ON d.department_id = lm.department_id
    LEFT JOIN people p ON p.person_id = lm.requestedby_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " $order_by LIMIT 50";  // temporary limit for testing



$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <div class="d-flex align-items-center mb-4">
        <h1 class="h3 me-auto">Legal Matters</h1>
        <a href="/legal_matter_create.php" class="btn btn-primary">Create New Matter</a>
    </div>

    <!-- Search + Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" 
                               name="search" placeholder="Search file #, name, description..." 
                               value="<?= h($search) ?>" aria-label="Search">
                        <?php if ($search !== ''): ?>
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="window.location.href='?<?= http_build_query(array_diff_key($_GET, ['search' => ''])) ?>'">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3 col-lg-2">
                    <select class="form-select" name="status">
                        <option value="all">All Statuses</option>
                        <!-- Your status options here -->
                    </select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <select class="form-select" name="dept">
                        <option value="0">All Departments</option>
                        <!-- Your department options here -->
                    </select>
                </div>

                <div class="col-md-1 col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>File #</th>
                            <th>Matter Name</th>
                            <th>Type</th>
                            <th>Requested By</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($matters)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No legal matters found.<br>
                                    <small>Debug info: <?= count($matters) ?> rows returned from query.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matters as $m): ?>
                                <tr>
                                    <td><?= h($m['file_number'] ?: '-') ?></td>
                                    <td><?= h($m['matter_name'] ?: '-') ?></td>
                                    <td><?= h($m['matter_type'] ?: '-') ?></td>
                                    <td><?= h($m['requested_by_name'] ?: '-') ?></td>
                                    <td><?= h($m['department_name'] ?: 'Town-wide') ?></td>
                                    <td><?= h($m['status'] ?: '-') ?></td>
                                    <td><?= h($m['date_started_fmt'] ?: '-') ?></td>
                                    <td class="text-end">
                                        <a href="/legal_matter_edit.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>