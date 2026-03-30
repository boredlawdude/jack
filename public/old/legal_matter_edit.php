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
$me = current_person(); // assumes updated version that fetches fresh data

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
            <p class="lead">This page is only available to Legal department members.</p>
            <a href="/dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
        </div>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
//  Get matter ID from URL
// ────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    include __DIR__ . '/header.php';
    echo '<div class="container mt-5"><div class="alert alert-warning">No matter ID provided.</div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
//  Load existing matter
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
        DATE_FORMAT(lm.date_started, '%Y-%m-%d') AS date_started,
        lm.matter_long_description,
        d.department_name,
        CONCAT(p.first_name, ' ', p.last_name) AS requested_by_name
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
    echo '<div class="container mt-5"><div class="alert alert-danger">Matter not found.</div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

// ────────────────────────────────────────────────
//  Load dropdown data
// ────────────────────────────────────────────────
$departments = $pdo->query("
    SELECT department_id, department_name 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY department_name
")->fetchAll(PDO::FETCH_ASSOC);

$people = $pdo->query("
    SELECT person_id, CONCAT(first_name, ' ', last_name) AS full_name
    FROM people 
    WHERE is_active = 1 
    ORDER BY last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────
//  Handle form submission
// ────────────────────────────────────────────────
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_number            = trim($_POST['file_number'] ?? '');
    $matter_name            = trim($_POST['matter_name'] ?? '');
    $matter_type            = trim($_POST['matter_type'] ?? '');
    $assigned_to            = trim($_POST['assigned_to'] ?? '');
    $department_id          = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $requestedby_id         = !empty($_POST['requestedby_id']) ? (int)$_POST['requestedby_id'] : null;
    $status                 = $_POST['status'] ?? 'New';
    $date_started           = trim($_POST['date_started'] ?? '');
    $matter_long_description = trim($_POST['matter_long_description'] ?? '');
    $file_path = trim($_POST['file_path'] ?? '');

    // Validation
    if ($file_number === '') $errors[] = "File Number is required.";
    if ($matter_name === '') $errors[] = "Matter Name is required.";
    if ($date_started === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_started)) {
        $errors[] = "Valid Start Date (YYYY-MM-DD) is required.";
    }

    // Check file_number uniqueness (exclude current record)
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT 1 FROM legal_matters WHERE file_number = ? AND id != ?");
        $chk->execute([$file_number, $id]);
        if ($chk->fetchColumn()) {
            $errors[] = "File Number '$file_number' is already used by another matter.";
        }
    }

    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE legal_matters 
            SET 
                file_number             = ?,
                matter_name             = ?,
                matter_type             = ?,
                assigned_to             = ?,
                department_id           = ?,
                requestedby_id          = ?,
                status                  = ?,
                date_started            = ?,
                matter_long_description = ?,
                file_path               = ?
            WHERE id = ?
        ");
        $update->execute([
            $file_number,
            $matter_name,
            $matter_type ?: null,
            $assigned_to ?: null,
            $department_id,
            $requestedby_id,
            $status,
            $date_started,
            $matter_long_description ?: null,
            $file_path,
            $id
        ]);

        $success = true;
        // Refresh data from DB
        $matter = array_merge($matter, [
            'file_number'             => $file_number,
            'matter_name'             => $matter_name,
            'matter_type'             => $matter_type,
            'assigned_to'             => $assigned_to,
            'department_id'           => $department_id,
            'requestedby_id'          => $requestedby_id,
            'status'                  => $status,
            'date_started'            => $date_started,
            'matter_long_description' => $matter_long_description,
            'file_path'               => $file_path  
        ]);
    }
}

include __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4">Edit Legal Matter: <?= h($matter['file_number']) ?></h1>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Matter updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" novalidate>
                <div class="row g-3">

                    <!-- File Number -->
                    <div class="col-md-4">
                        <label class="form-label">File Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="file_number" required 
                               value="<?= h($matter['file_number']) ?>">
                    </div>

                    <!-- Matter Name -->
                    <div class="col-md-8">
                        <label class="form-label">Matter Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="matter_name" required 
                               value="<?= h($matter['matter_name']) ?>">
                    </div>

                    <!-- Matter Type -->
                    <div class="col-md-6">
                        <label class="form-label">Matter Type</label>
                        <select class="form-select" name="matter_type">
                            <option value="">-- Select --</option>
                            <?php foreach (['Contract Review', 'Contract Dispute', 'Litigation', 'Policy / Ordinance', 'Real Estate / Land Use', 'Employment / HR', 'Intergovernmental', 'Other'] as $type): ?>
                                <option value="<?= h($type) ?>" <?= $matter['matter_type'] === $type ? 'selected' : '' ?>>
                                    <?= h($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assigned To -->
                    <div class="col-md-6">
                        <label class="form-label">Assigned To</label>
                        <input type="text" class="form-control" name="assigned_to" 
                               value="<?= h($matter['assigned_to'] ?? '') ?>">
                    </div>

                    <!-- Department -->
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">-- None / Town-wide --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['department_id'] ?>" 
                                        <?= $matter['department_id'] == $d['department_id'] ? 'selected' : '' ?>>
                                    <?= h($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Requested By -->
                    <div class="col-md-6">
                        <label class="form-label">Requested By</label>
                        <select class="form-select" name="requestedby_id">
                            <option value="">-- Select --</option>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= (int)$person['person_id'] ?>" 
                                        <?= $matter['requestedby_id'] == $person['person_id'] ? 'selected' : '' ?>>
                                    <?= h($person['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" required>
                            <?php foreach (['New', 'In Progress', 'Under Review', 'Pending Council/Approval', 'On Hold', 'Completed', 'Closed', 'Cancelled'] as $s): ?>
                                <option value="<?= h($s) ?>" <?= $matter['status'] === $s ? 'selected' : '' ?>>
                                    <?= h($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Started -->
                    <div class="col-md-4">
                        <label class="form-label">Date Started <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_started" required 
                               value="<?= h($matter['date_started']) ?>">
                    </div>
                   <!-- File path picker -->
                      <!-- In your form (create or edit page) -->
                            <div class="mb-3">
                                <label for="filePath" class="form-label">Document File Path</label>
                                <textarea class="form-control" name="file_path" id="filePath" rows="3"
                                        placeholder="Paste the full network path here..."></textarea>
                                <div class="form-text mt-2 text-muted">
                                    <strong>How to get the path:</strong><br>
                                    <strong>Windows:</strong> Right-click file in Explorer → <strong>Copy as path</strong> → paste here<br>
                                    <strong>Mac:</strong> Right-click file → <strong>Copy [filename] as Pathname</strong> (or drag file to Terminal to get path)<br>
                                    <strong>Network drive example:</strong> \\servername\LegalDocs\2025\L-045-contract.pdf
                                </div>
                            </div>
                    
                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label">Matter Description / Background</label>
                        <textarea class="form-control" name="matter_long_description" rows="8"><?= h($matter['matter_long_description'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="col-12 text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5">Save Changes</button>
                        <a href="/legal_matters_list.php" class="btn btn-outline-secondary ms-3">Cancel</a>
                    </div>
                    <!-- Add this right after the Save/Cancel buttons -->
                        <!-- Button - keep or update -->
<!-- Button -->
<div class="mt-4 text-end">
    <button type="button" class="btn btn-outline-secondary btn-lg px-4" 
            onclick="printQL500Label()">
        <i class="bi bi-printer me-2"></i> Print 29×90 Label
    </button>
</div>

<script>
function printQL500Label() {
    const fileNum = <?= json_encode($matter['file_number'] ?? 'N/A') ?>;
    const matterName = <?= json_encode($matter['matter_name'] ?? 'Matter Name') ?>;

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Pop-up blocked. Please allow pop-ups for this site.');
        return;
    }

    printWindow.document.write(`
        <html>
        <head>
            <title>Label - ${fileNum}</title>
            <style>
                @page {
                    size: 90mm 29mm landscape;   /* 90 mm wide × 29 mm high - landscape */
                    margin: 0mm;
                }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    background: white;
                    width: 90mm;
                    height: 29mm;
                    overflow: hidden;
                }
                .label {
                    width: 90mm;
                    height: 29mm;
                    padding: 2mm 4mm;           /* Very tight padding for small label */
                    box-sizing: border-box;
                    border: 1px solid #000;
                    background: white;
                    text-align: left;
                    display: flex;
                    flex-direction: column;
                    justify-content: left;
                    align-items: left;
                    font-size: 9pt;
                    line-height: 1.15;
                }
                .header {
                    font-size: 10pt;
                    font-weight: bold;
                    margin: 0 0 1mm 0;
                }
                .file {
                    font-size: 10pt;
                    font-weight: bold;
                    margin: 1mm 0;
                }
                .name {
                    font-size: 12pt;
                    font-weight: bold;
                    line-height: 1.1;
                    word-break: break-word;
                    margin: 1mm 0;
                    flex-grow: 1;
                    display: flex;
                    align-items: left;
                    justify-content: left;
                }
                .footer {
                    font-size: 7pt;
                    color: #555;
                    margin-top: 1mm;
                }
            </style>
        </head>
        <body onload="window.print(); setTimeout(() => window.close(), 600);">
            <div class="label">
                <div class="file">File: ${fileNum}</div>
                <div class="name">${matterName}</div>
                <div class="footer">Confidential – Internal Use</div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>


<?php include __DIR__ . '/footer.php'; ?>