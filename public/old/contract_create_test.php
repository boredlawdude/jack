<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

// Critical: make PDO throw exceptions on error
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = false;
$debug_log = [];

$debug_log[] = "Script started at " . date('c');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_log[] = "POST request detected";
    $debug_log[] = "POST data: " . print_r($_POST, true);

    $name = trim($_POST['name'] ?? '');
    $counterparty_company_id = (int)($_POST['counterparty_company_id'] ?? 0);

    $debug_log[] = "Parsed name: '$name'";
    $debug_log[] = "Parsed counterparty_company_id: $counterparty_company_id";

    if ($name === '') {
        $errors[] = "Name is required.";
        $debug_log[] = "Validation failed: name empty";
    } else {
        $debug_log[] = "Validation passed (minimal)";

        try {
            $debug_log[] = "Starting insert attempt";

            $contract_number = 'TEST-' . date('Ymd-His') . '-' . mt_rand(1000,9999);

            $stmt = $pdo->prepare("
                INSERT INTO contracts (
                    contract_number,
                    name,
                    status,
                    counterparty_company_id
                ) VALUES (
                    ?, ?, 'draft', ?
                )
            ");

            $debug_log[] = "Prepared statement";

            $stmt->execute([
                $contract_number,
                $name,
                $counterparty_company_id ?: null   // allow null if not set
            ]);

            $new_id = (int)$pdo->lastInsertId();

            $debug_log[] = "Insert executed - new ID: $new_id";
            $success = true;

        } catch (PDOException $e) {
            $errors[] = "DB error: " . $e->getMessage();
            $debug_log[] = "PDOException: " . $e->getMessage() . " | Code: " . $e->getCode();
        } catch (Throwable $e) {
            $errors[] = "Unexpected error: " . $e->getMessage();
            $debug_log[] = "Throwable: " . $e->getMessage();
        }
    }

    // Write debug to file for persistence
    file_put_contents(
        __DIR__ . '/insert_debug.log',
        implode("\n", $debug_log) . "\n\n---\n\n",
        FILE_APPEND
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract Insert Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">

<h1>Test Contract Create (Minimal)</h1>

<?php if ($success): ?>
    <div class="alert alert-success">
        Success! New contract ID: <?= $new_id ?> (number: <?= h($contract_number) ?>)
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Errors:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card p-4">
    <div class="mb-3">
        <label class="form-label">Contract Name (required)</label>
        <input class="form-control" name="name" required value="<?= h($name ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Counterparty Company ID (optional, integer)</label>
        <input class="form-control" name="counterparty_company_id" type="number" value="<?= h($_POST['counterparty_company_id'] ?? '') ?>">
        <small class="form-text">Put any existing company_id from your DB, or leave blank</small>
    </div>

    <button type="submit" class="btn btn-primary">Create Test Contract</button>
</form>

<hr>

<h3>Debug Info (also saved to insert_debug.log)</h3>
<pre><?= implode("\n", $debug_log) ?></pre>

</body>
</html>