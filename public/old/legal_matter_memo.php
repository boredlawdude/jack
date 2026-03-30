<?php
declare(strict_types=1);

// Force errors during dev (remove/comment in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ────────────────────────────────────────────────
//  Security: Legal department only
// ────────────────────────────────────────────────
$me = current_person();

$legal_dept_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = 'Legal' LIMIT 1");
$legal_dept_stmt->execute();
$legal_department_id = (int)($legal_dept_stmt->fetchColumn() ?: 0);

if (empty($me) || !isset($me['department_id']) || (int)$me['department_id'] !== $legal_department_id) {
    http_response_code(403);
    include __DIR__ . '/header.php';
    ?>
    <div class="container mt-5">
        <div class="alert alert-danger text-center py-5">
            <h2>Access Restricted</h2>
            <p class="lead">This memo view is only available to Legal department members.</p>
        </div>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
//  Get matter ID
// ────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    include __DIR__ . '/header.php';
    echo '<div class="container mt-5"><div class="alert alert-warning">No matter ID provided.</div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
//  Load matter data
// ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        lm.id,
        lm.file_number,
        lm.matter_name,
        lm.matter_type,
        lm.assigned_to,
        lm.department_id,
        lm.requestedby_id,
        lm.status,
        DATE_FORMAT(lm.date_started, '%M %d, %Y') AS date_started_fmt,
        lm.matter_long_description,
        d.department_name,
        CONCAT(p.first_name, ' ', p.last_name) AS requested_by_name,
        p.email AS requested_by_email
    FROM legal_matters lm
    LEFT JOIN departments d ON d.department_id = lm.department_id
    LEFT JOIN people p ON p.person_id = lm.requestedby_id
    WHERE lm.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$matter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$matter) {
    include __DIR__ . '/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Legal matter not found.</div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

include __DIR__ . '/header.php';
?>

<div class="container mt-4 mb-5" id="memo-content">
    <div class="text-end mb-4">
        <button onclick="window.print()" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-printer me-2"></i> Print Memo
        </button>
    </div>

    <div class="memo-paper border shadow bg-white p-5 mx-auto" style="max-width: 8.5in; min-height: 11in;">
        <!-- Header -->
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-1">TOWN OF HOLLY SPRINGS</h2>
            <h4 class="text-muted mb-0">Town Attorney's Office  – Legal Matter Memorandum</h4>
            <small class="text-muted">Confidential – Attorney Work Product / Privileged</small>
        </div>

        <!-- Matter Info Grid -->
        <div class="row mb-4">
            <div class="col-6">
                <strong>File Number:</strong><br>
                <?= h($matter['file_number']) ?>
            </div>
            <div class="col-6 text-end">
                <strong>Date Prepared:</strong><br>
                <?= date('F j, Y') ?>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <strong>Matter Name / Description:</strong><br>
                <div class="border p-3 bg-light rounded">
                    <?= h($matter['matter_name']) ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <strong>Matter Type:</strong><br>
                <?= h($matter['matter_type'] ?: 'Not specified') ?>
            </div>
            <div class="col-md-4">
                <strong>Status:</strong><br>
                <span class="badge bg-<?= match($matter['status']) {
                    'New' => 'info',
                    'In Progress', 'Under Review' => 'primary',
                    'Pending Council/Approval' => 'warning',
                    'On Hold' => 'secondary',
                    'Completed', 'Closed' => 'success',
                    'Cancelled' => 'danger',
                    default => 'light text-dark'
                } ?>">
                    <?= h($matter['status']) ?>
                </span>
            </div>
            <div class="col-md-4">
                <strong>Date Started:</strong><br>
                <?= h($matter['date_started_fmt']) ?>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <strong>Assigned To:</strong><br>
                <?= h($matter['assigned_to'] ?: 'Not assigned') ?>
            </div>
            <div class="col-md-6">
                <strong>Department:</strong><br>
                <?= h($matter['department_name'] ?: 'Town-wide / Not specified') ?>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <strong>Requested By:</strong><br>
                <?= h($matter['requested_by_name'] ?: 'Unknown') ?>
                <?php if (!empty($matter['requested_by_email'])): ?>
                    <br><small class="text-muted"><?= h($matter['requested_by_email']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Long Description -->
        <div class="mb-5">
            <h5 class="fw-bold mb-3">Detailed Background / Scope / Notes</h5>
            <div class="border p-4 bg-light rounded" style="white-space: pre-wrap; min-height: 200px;">
                <?= nl2br(h($matter['matter_long_description'] ?: 'No detailed description entered.')) ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-5 pt-4 border-top">
            <small class="text-muted">
                Generated <?= date('F j, Y \a\t g:i A') ?> • Confidential – For Internal Use Only
            </small>
        </div>
    </div>
</div>

<!-- Print-specific CSS -->
<style>
    @media print {
        .no-print { display: none !important; }
        .memo-paper {
            box-shadow: none !important;
            border: none !important;
            margin: 0 !important;
            padding: 0.5in !important;
            width: 100% !important;
            min-height: auto !important;
        }
        body { background: white !important; }
        @page { margin: 0.5in; }
    }
</style>

<?php include __DIR__ . '/footer.php'; ?>