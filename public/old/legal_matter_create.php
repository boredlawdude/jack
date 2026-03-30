<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Only people who can create legal matters (adjust permission as needed)
if (!is_system_admin() && !person_has_role_key('LEGAL_ADMIN')) {
    http_response_code(403);
    exit('Forbidden');
}

// Load dropdown data
$departments = $pdo->query("
    SELECT department_id, department_name 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY department_name
")->fetchAll(PDO::FETCH_ASSOC);

$people = $pdo->query("
    SELECT person_id, CONCAT(first_name, ' ', last_name) AS full_name, department_name
    FROM people p
    LEFT JOIN departments d ON d.department_id = p.department_id
    WHERE p.is_active = 1
    ORDER BY last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_number            = trim((string)($_POST['file_number'] ?? ''));
    $matter_name            = trim((string)($_POST['matter_name'] ?? ''));
    $matter_type            = trim((string)($_POST['matter_type'] ?? ''));
    $assigned_to            = trim((string)($_POST['assigned_to'] ?? ''));
    $department_id          = (int)($_POST['department_id'] ?? 0) ?: null;
    $requestedby_id         = (int)($_POST['requestedby_id'] ?? 0) ?: null;
    $status                 = $_POST['status'] ?? 'New';
    $date_started           = trim((string)($_POST['date_started'] ?? ''));
    $matter_long_description = trim((string)($_POST['matter_long_description'] ?? ''));

    // Basic validation
    if ($file_number === '') $errors[] = "File Number is required.";
    if ($matter_name === '') $errors[] = "Matter Name is required.";
    if ($date_started === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_started)) {
        $errors[] = "Valid Start Date (YYYY-MM-DD) is required.";
    }

    // Check unique file_number
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT 1 FROM legal_matters WHERE file_number = ?");
        $chk->execute([$file_number]);
        if ($chk->fetchColumn()) {
            $errors[] = "File Number '$file_number' already exists.";
        }
    }

    if (empty($errors)) {
        $ins = $pdo->prepare("
            INSERT INTO legal_matters 
            (file_number, matter_name, matter_type, assigned_to, department_id, requestedby_id, status, date_started, matter_long_description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $file_number,
            $matter_name,
            $matter_type ?: null,
            $assigned_to ?: null,
            $department_id,
            $requestedby_id,
            $status,
            $date_started,
            $matter_long_description ?: null,
            current_person_id()  // or current_person()['full_name'] if you prefer name
        ]);

        $success = true;
        // Optional: redirect or show success message
    }
}

include __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h1 class="h3 mb-4">Create New Legal Matter</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    Legal matter <strong><?= h($file_number) ?></strong> created successfully.
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
                                       value="<?= h($_POST['file_number'] ?? '') ?>" 
                                       placeholder="e.g. 2026-L-045">
                            </div>

                            <!-- Matter Name -->
                            <div class="col-md-8">
                                <label class="form-label">Matter Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="matter_name" required 
                                       value="<?= h($_POST['matter_name'] ?? '') ?>" 
                                       placeholder="e.g. Ting-Oakhall Greenway Netting Dispute">
                            </div>

                            <!-- Matter Type -->
                            <div class="col-md-6">
                                <label class="form-label">Matter Type</label>
                                <select class="form-select" name="matter_type">
                                    <option value="">-- Select --</option>
                                    <option value="Contract Review"        <?= ($_POST['matter_type'] ?? '') === 'Contract Review' ? 'selected' : '' ?>>Contract Review</option>
                                    <option value="Contract Dispute"       <?= ($_POST['matter_type'] ?? '') === 'Contract Dispute' ? 'selected' : '' ?>>Contract Dispute</option>
                                    <option value="Litigation"             <?= ($_POST['matter_type'] ?? '') === 'Litigation' ? 'selected' : '' ?>>Litigation</option>
                                    <option value="Policy / Ordinance"     <?= ($_POST['matter_type'] ?? '') === 'Policy / Ordinance' ? 'selected' : '' ?>>Policy / Ordinance</option>
                                    <option value="Real Estate / Land Use" <?= ($_POST['matter_type'] ?? '') === 'Real Estate / Land Use' ? 'selected' : '' ?>>Real Estate / Land Use</option>
                                    <option value="Employment / HR"        <?= ($_POST['matter_type'] ?? '') === 'Employment / HR' ? 'selected' : '' ?>>Employment / HR</option>
                                    <option value="Intergovernmental"      <?= ($_POST['matter_type'] ?? '') === 'Intergovernmental' ? 'selected' : '' ?>>Intergovernmental</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Assigned To (free text) -->
                            <div class="col-md-6">
                                <label class="form-label">Assigned To</label>
                                <input type="text" class="form-control" name="assigned_to" 
                                       value="<?= h($_POST['assigned_to'] ?? '') ?>" 
                                       placeholder="e.g. John Schifano, Legal Team">
                            </div>

                            <!-- Department -->
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">-- None / Town-wide --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['department_id'] ?>" 
                                                <?= ((int)($_POST['department_id'] ?? 0) === (int)$d['department_id']) ? 'selected' : '' ?>>
                                            <?= h($d['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Requested By (person dropdown) -->
                            <div class="col-md-6">
                                <label class="form-label">Requested By</label>
                                <select class="form-select" name="requestedby_id">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($people as $p): ?>
                                        <option value="<?= (int)$p['person_id'] ?>" 
                                                <?= ((int)($_POST['requestedby_id'] ?? 0) === (int)$p['person_id']) ? 'selected' : '' ?>>
                                            <?= h($p['full_name']) ?>
                                            <?php if (!empty($p['department_name'])): ?>
                                                <small class="text-muted"> (<?= h($p['department_name']) ?>)</small>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="col-md-4">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="New" <?= ($_POST['status'] ?? 'New') === 'New' ? 'selected' : '' ?>>New</option>
                                    <option value="In Progress" <?= ($_POST['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Under Review" <?= ($_POST['status'] ?? '') === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                                    <option value="Pending Council/Approval" <?= ($_POST['status'] ?? '') === 'Pending Council/Approval' ? 'selected' : '' ?>>Pending Council/Approval</option>
                                    <option value="On Hold" <?= ($_POST['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                                    <option value="Completed" <?= ($_POST['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Closed" <?= ($_POST['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                    <option value="Cancelled" <?= ($_POST['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>

                            <!-- Date Started -->
                            <div class="col-md-4">
                                <label class="form-label">Date Started <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_started" required 
                                       value="<?= h($_POST['date_started'] ?? date('Y-m-d')) ?>">
                            </div>

                            <!-- Long Description -->
                            <div class="col-12">
                                <label class="form-label">Matter Description / Background</label>
                                <textarea class="form-control" name="matter_long_description" rows="6"><?= h($_POST['matter_long_description'] ?? '') ?></textarea>
                                <div class="form-text">Include background, goals, risks, next steps, related contracts, etc.</div>
                            </div>

                            <!-- Submit -->
                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">Create Legal Matter</button>
                                <a href="/legal_matters_list.php" class="btn btn-outline-secondary ms-3">Cancel</a>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<!-- Optional: Add Bootstrap datepicker or client-side validation if desired -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>