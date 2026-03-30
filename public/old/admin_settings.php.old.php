<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

// Only superuser / system admin can access
if (!function_exists('is_system_admin') || !is_system_admin()) {
    http_response_code(403);
    exit('Access denied. System admin required.');
}

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ── Handle form submission ──
$errors   = [];
$success  = false;
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    // Simple anti-CSRF (session token check)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        unset($_SESSION['csrf_token']);

        $settingsToUpdate = [
            'storage_base_dir',
            'contracts_generated_subdir',
            'docx_template_dir',
            'html_template_dir',
            'default_docx_template',
            'default_html_template',
        ];

        foreach ($settingsToUpdate as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ($value === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' cannot be empty.';
                continue;
            }

            // Basic validation for absolute paths
            if (in_array($key, ['storage_base_dir', 'docx_template_dir', 'html_template_dir'])) {
                if (!str_starts_with($value, '/') || !is_dir($value)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' must be a valid absolute directory path.';
                    continue;
                }
            }

            // Update DB
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, 
                    updated_by = ?,
                    updated_at = NOW()
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, current_person_id() ?: null, $key]);
        }

        if (empty($errors)) {
            $success = true;
            $messages[] = 'Settings updated successfully.';
        }
    }
}

// ── Generate new CSRF token ──
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));

// ── Load current settings ──
$settings = [];
$stmt = $pdo->query("
    SELECT setting_key, setting_value, description 
    FROM system_settings 
    WHERE setting_key IN (
        'storage_base_dir', 'contracts_generated_subdir',
        'docx_template_dir', 'html_template_dir',
        'default_docx_template', 'default_html_template'
    )
    ORDER BY setting_key
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row;
}

include __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4">System Settings – Paths & Templates</h1>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= implode('<br>', array_map('h', $messages)) ?>
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
            <form method="post">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                <div class="row g-4">
                    <?php foreach ($settings as $key => $row): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <?= h(ucwords(str_replace('_', ' ', $key))) ?>
                            </label>
                            <?php if ($row['description']): ?>
                                <div class="form-text text-muted mb-2"><?= h($row['description']) ?></div>
                            <?php endif; ?>

                            <input type="text" 
                                   class="form-control <?= in_array($key, ['storage_base_dir','docx_template_dir','html_template_dir']) ? 'font-monospace' : '' ?>"
                                   name="<?= h($key) ?>"
                                   value="<?= h($row['setting_value']) ?>"
                                   required>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        Save Changes
                    </button>
                    <a href="/admin_settings.php" class="btn btn-outline-secondary ms-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-info mt-4 small">
        <strong>Note:</strong> Changes take effect immediately for new generations/downloads.<br>
        Existing files are not moved — update paths carefully.
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>