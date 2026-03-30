<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }

// Legal-only restriction (optional – remove if dashboard is for wider use)
$me = current_person();
$legal_dept_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = 'Legal' LIMIT 1");
$legal_dept_stmt->execute();
$legal_department_id = (int)($legal_dept_stmt->fetchColumn() ?: 0);

if (empty($me) || !isset($me['department_id']) || (int)$me['department_id'] !== $legal_department_id) {
    http_response_code(403);
    include __DIR__ . '/header.php';
 <style>
    .dashboard-header {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: white;
        padding: 2rem 1.5rem;
        border-radius: 0.5rem 0.5rem 0 0;
        margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    }
    .stat-card {
        transition: all 0.25s ease;
        border: none;
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15) !important;
    }
    .stat-icon {
        font-size: 3rem;
        opacity: 0.9;
        margin-bottom: 0.75rem;
    }
    .card-title {
        font-weight: 600;
        letter-spacing: -0.5px;
    }
    .quick-link-card {
        transition: all 0.25s ease;
        border: none;
        border-radius: 0.75rem;
        background: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .quick-link-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
    }
    .quick-icon {
        font-size: 3.5rem;
        margin-bottom: 1rem;
        opacity: 0.85;
    }
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        color: #495057;
    }
    .badge {
        font-weight: 500;
        padding: 0.5em 0.9em;
    }
</style>
   
   ?>
    <div class="container mt-5">
        <div class="alert alert-danger text-center py-5">
            <h2>Access Restricted</h2>
            <p class="lead">Dashboard access is limited to Legal department members.</p>
        </div>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
// Key Stats Queries
// ────────────────────────────────────────────────

// Total Matters
$totalStmt = $pdo->query("SELECT COUNT(*) FROM legal_matters");
$total_matters = (int)$totalStmt->fetchColumn();

// Open / In Progress
$openStmt = $pdo->prepare("SELECT COUNT(*) FROM legal_matters WHERE status IN ('New', 'In Progress', 'Under Review', 'Pending Council/Approval')");
$openStmt->execute();
$open_matters = (int)$openStmt->fetchColumn();

// Completed / Closed
$closedStmt = $pdo->prepare("SELECT COUNT(*) FROM legal_matters WHERE status IN ('Completed', 'Closed')");
$closedStmt->execute();
$closed_matters = (int)$closedStmt->fetchColumn();

// Recent Matters (last 30 days)
$recentStmt = $pdo->prepare("
    SELECT id, file_number, matter_name, status, DATE_FORMAT(date_started, '%m/%d/%Y') AS started_fmt
    FROM legal_matters
    WHERE date_started >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date_started DESC
    LIMIT 5
");
$recentStmt->execute();
$recent_matters = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Pending Council (example – adjust status name if different)
$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM legal_matters WHERE status = 'Pending Council/Approval'");
$pendingStmt->execute();
$pending_council = (int)$pendingStmt->fetchColumn();

include __DIR__ . '/header.php';
?>
<div class="container mt-4">
    <div class="dashboard-header text-center">
        <h1 class="h2 mb-1">Legal Matters Dashboard</h1>
        <p class="lead mb-0 opacity-75">Quick overview & recent activity</p>
    </div>

    <!-- Stats -->
    <div class="row g-4 mt-4">
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card h-100 bg-gradient-primary text-white">
                <div class="card-body text-center">
                    <i class="bi bi-folder-check stat-icon"></i>
                    <h5 class="card-title">Total Matters</h5>
                    <h2 class="display-5 fw-bold"><?= number_format($total_matters) ?></h2>
                </div>
            </div>
        </div>
        <!-- Repeat similar blocks for Open, Completed, Pending with different bg-gradient classes -->
        <!-- e.g. bg-gradient-warning, bg-gradient-success, bg-gradient-info -->
    </div>

    <!-- Recent Activity -->
    <div class="card shadow-sm mt-5">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recent Activity</h5>
        </div>
        <!-- Your existing recent table here -->
    </div>

    <!-- Quick Links -->
    <div class="row g-4 mt-5">
        <div class="col-md-4">
            <div class="card quick-link-card text-center p-5">
                <i class="bi bi-list-ul quick-icon text-primary"></i>
                <h5>All Matters</h5>
                <p class="text-muted small">Full list & advanced search</p>
                <a href="/legal_matters_list.php" class="stretched-link"></a>
            </div>
        </div>
        <!-- Similar blocks for New Matter and Pending Council -->
    </div>
</div>

    <!-- Recent Matters -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recent Activity (Last 30 Days)</h5>
            <a href="/legal_matters_list.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>File #</th>
                            <th>Matter Name</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_matters)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No activity in the last 30 days.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_matters as $m): ?>
                                <tr>
                                    <td><strong><?= h($m['file_number'] ?: '-') ?></strong></td>
                                    <td class="text-truncate" style="max-width: 300px;">
                                        <?= h($m['matter_name'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match($m['status']) {
                                            'New' => 'info',
                                            'In Progress','Under Review' => 'primary',
                                            'Pending Council/Approval' => 'warning',
                                            'On Hold' => 'secondary',
                                            'Completed','Closed' => 'success',
                                            'Cancelled' => 'danger',
                                            default => 'light text-dark'
                                        } ?>">
                                            <?= h($m['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= h($m['started_fmt'] ?: '-') ?></td>
                                    <td class="text-end">
                                        <a href="/legal_matter_edit.php?id=<?= (int)$m['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card quick-link-card shadow-sm h-100 text-center p-4">
                <i class="bi bi-list-ul fs-1 text-primary mb-3"></i>
                <h5>All Matters</h5>
                <p class="text-muted">View full list and search</p>
                <a href="/legal_matters_list.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card quick-link-card shadow-sm h-100 text-center p-4">
                <i class="bi bi-plus-circle fs-1 text-success mb-3"></i>
                <h5>New Matter</h5>
                <p class="text-muted">Create a new legal matter</p>
                <a href="/legal_matter_create.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card quick-link-card shadow-sm h-100 text-center p-4">
                <i class="bi bi-gavel fs-1 text-warning mb-3"></i>
                <h5>Pending Council</h5>
                <p class="text-muted">Review items awaiting approval</p>
                <a href="/legal_matters_list.php?status=Pending Council/Approval" class="stretched-link"></a>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>