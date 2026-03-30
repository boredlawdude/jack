<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$errors = [];
$ok = false;

// Enum from your contracts table
$statuses = ['draft','in_review','signed','expired','terminated'];

// Sticky fields
$contract_number = '';
$name = '';
$status = 'draft';
$type = '';
$governing_law = '';
$start_date = '';
$end_date = '';
$auto_renew = 0;
$renewal_term_months = '';
$total_contract_value = '';
$currency = 'USD';
$department_id = '';
$contract_type_id = '';

$owner_company_id = '';
$counterparty_company_id = '';
$primary_contact_id = '';
$description = '';

function null_if_blank($v) {
  $v = trim((string)$v);
  return $v === '' ? null : $v;
}
function is_yyyy_mm_dd(string $d): bool {
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

// Load companies
$companies = $pdo->query("
  SELECT company_id, name
  FROM companies
  WHERE is_active = 1
  ORDER BY name
")->fetchAll();

// Load departments
if (is_system_admin()) {
  $departments = $pdo->query("
    SELECT department_id, department_name
    FROM departments
    WHERE is_active = 1
    ORDER BY department_name
  ")->fetchAll();
} else {
  $departments = $pdo->prepare("
    SELECT d.department_id, d.department_name
    FROM departments d
    JOIN user_department_roles udr ON udr.department_id = d.department_id
    JOIN roles r ON r.role_id = udr.role_id
    WHERE d.is_active = 1
      AND udr.user_id = ?
      AND r.role_key = 'DEPT_CONTRACT_ADMIN'
      AND r.is_active = 1
    ORDER BY d.department_name
  ");
  $departments->execute([current_user_id()]);
  $departments = $departments->fetchAll();
}
// Check for Formal Bidding Required contract types
$contract_types = $pdo->query("
  SELECT contract_type_id, contract_type, formal_bidding_required
  FROM contract_types
  WHERE is_active = 1
  ORDER BY contract_type
")->fetchAll();

// Load people (optional)
$people = [];
$has_people_table = false;
try {
  $people = $pdo->query("
    SELECT person_id, CONCAT(first_name,' ',last_name) AS full_name
    FROM people
    WHERE is_active = 1
    ORDER BY last_name, first_name
  ")->fetchAll();
  $has_people_table = true;
} catch (Throwable $e) {
  $has_people_table = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') 
  $contract_number = trim($_POST['contract_number'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $status = $_POST['status'] ?? 'draft';
  $type = trim($_POST['type'] ?? '');
  $governing_law = trim($_POST['governing_law'] ?? '');
  $start_date = trim($_POST['start_date'] ?? '');
  $end_date = trim($_POST['end_date'] ?? '');
  $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
  $renewal_term_months = trim($_POST['renewal_term_months'] ?? '');
  $total_contract_value = trim($_POST['total_contract_value'] ?? '');
  $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
  $department_id = trim($_POST['department_id'] ?? '');

  $owner_company_id = trim($_POST['owner_company_id'] ?? '');
  $counterparty_company_id = trim($_POST['counterparty_company_id'] ?? '');
  $primary_contact_id = trim($_POST['primary_contact_id'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $contract_type_id = trim($_POST['contract_type_id'] ?? '');

  // Required validation
  if ($contract_number === '') $errors[] = "Contract # is required.";
  if ($name === '') $errors[] = "Name is required.";
  if (!in_array($status, $statuses, true)) $errors[] = "Invalid status.";
  if ($owner_company_id === '' || (int)$owner_company_id <= 0) $errors[] = "Owner Company is required.";
  if ($counterparty_company_id === '' || (int)$counterparty_company_id <= 0) $errors[] = "Counterparty Company is required.";
  if ($owner_company_id !== '' && $counterparty_company_id !== '' && (int)$owner_company_id === (int)$counterparty_company_id) {
    $errors[] = "Owner and Counterparty cannot be the same company.";
  }
  if ($contract_type_id !== '' && (!ctype_digit($contract_type_id) || (int)$contract_type_id <= 0)) {
    $errors[] = "Contract Type must be blank or a valid type.";
  }

  // Department is optional, but validate if present
  if ($department_id !== '' && (!ctype_digit($department_id) || (int)$department_id <= 0)) {
    $errors[] = "Department must be blank or a valid department.";
  }
    // Require a department for non-admins (since dept role is scoped)
    if (!is_system_admin() && ($department_id === '' || (int)$department_id <= 0)) {
  $errors[] = "Department is required for contract admins.";
    }

  if ($department_id !== '' && (int)$department_id > 0) {
  if (!can_manage_contract_department((int)$department_id)) {
    $errors[] = "You do not have permission to create contracts for that department.";
  }


  // Dates
  if ($start_date !== '' && !is_yyyy_mm_dd($start_date)) $errors[] = "Start date must be YYYY-MM-DD.";
  if ($end_date !== '' && !is_yyyy_mm_dd($end_date)) $errors[] = "End date must be YYYY-MM-DD.";
  if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) $errors[] = "End date cannot be before start date.";

  // Numeric
  if ($renewal_term_months !== '' && (!ctype_digit($renewal_term_months) || (int)$renewal_term_months < 0)) {
    $errors[] = "Renewal term months must be a non-negative integer.";
  }
  if ($total_contract_value !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $total_contract_value)) {
    $errors[] = "Total contract value must look like 1000 or 1000.00";
  }
  if ($currency !== '' && strlen($currency) !== 3) {
    $errors[] = "Currency must be 3 letters (e.g., USD).";
  }
  if ($primary_contact_id !== '' && (!ctype_digit($primary_contact_id) || (int)$primary_contact_id <= 0)) {
    $errors[] = "Primary contact ID must be a positive integer or blank.";
  }

  // Unique contract_number
  if (!$errors) {
    $chk = $pdo->prepare("SELECT contract_id FROM contracts WHERE contract_number = ? LIMIT 1");
    $chk->execute([$contract_number]);
    if ($chk->fetch()) $errors[] = "That Contract # already exists.";
  }

  if (!$errors) {
    $stmt = $pdo->prepare("
      INSERT INTO contracts (
        contract_number, name, status, type, governing_law,
        start_date, end_date, auto_renew, renewal_term_months,
        total_contract_value, currency,
        owner_company_id, counterparty_company_id, owner_primary_contact_id,
        department_id,
        description,
        contract_type_id

      ) VALUES (?, ?, ?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,? )
    ");

    $stmt->execute([
      $contract_number,
      $name,
      $status,
      null_if_blank($type),
      null_if_blank($governing_law),
      null_if_blank($start_date),
      null_if_blank($end_date),
      $auto_renew,
      ($renewal_term_months === '' ? null : (int)$renewal_term_months),
      ($total_contract_value === '' ? null : $total_contract_value),
      ($currency === '' ? null : $currency),
      (int)$owner_company_id,
      (int)$counterparty_company_id,
      ($primary_contact_id === '' ? null : (int)$primary_contact_id),
      ($department_id === '' ? null : (int)$department_id),
      null_if_blank($description),
      ($contract_type_id === '' ? null : (int)$contract_type_id),
    ]);

    $ok = true;

    // Reset after success
    $contract_number = $name = $type = $governing_law = $start_date = $end_date = $description = '';
    $status = 'draft';
    $auto_renew = 0;
    $renewal_term_months = '';
    $total_contract_value = '';
    $currency = 'USD';
    $department_id = '';
    $owner_company_id = '';
    $counterparty_company_id = '';
    $primary_contact_id = '';
  }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">New Contract</h1>
  <a class="btn btn-outline-secondary btn-sm" href="/contracts_list.php">Back to Contracts</a>
</div>

<?php if ($ok): ?>
  <div class="alert alert-success py-2">Contract created.</div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <div class="row g-3">

        <div class="col-md-4">
          <label class="form-label">Contract # *</label>
          <input name="contract_number" class="form-control" required value="<?= htmlspecialchars($contract_number) ?>">
        </div>

        <div class="col-md-8">
          <label class="form-label">Short Description *</label>
          <input name="name" class="form-control" required value="<?= htmlspecialchars($name) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Status *</label>
          <select name="status" class="form-select" required>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Contract Type</label>
          <select name="contract_type_id" class="form-select">
            <option value="">(none)</option>
            <?php foreach ($contract_types as $ct): ?>
              <option value="<?= (int)$ct['contract_type_id'] ?>"
                <?= ((string)$contract_type_id === (string)$ct['contract_type_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($ct['contract_type']) ?>
                <?= ((int)$ct['formal_bidding_required'] === 1) ? ' (Formal bidding)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select">
            <option value="">(none)</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['department_id'] ?>"
                <?= ((string)$department_id === (string)$d['department_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['department_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Type</label>
          <input name="type" class="form-control" value="<?= htmlspecialchars($type) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Governing Law</label>
          <input name="governing_law" class="form-control" value="<?= htmlspecialchars($governing_law) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Currency</label>
          <input name="currency" class="form-control" maxlength="3" value="<?= htmlspecialchars($currency) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Total Contract Value</label>
          <input name="total_contract_value" class="form-control" value="<?= htmlspecialchars($total_contract_value) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div class="col-md-3">
          <div class="form-check mt-4 pt-2">
            <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew"
                   <?= ((int)$auto_renew === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="auto_renew">Auto-renew</label>
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Renewal Term (months)</label>
          <input name="renewal_term_months" class="form-control" type="number" min="0"
                 value="<?= htmlspecialchars($renewal_term_months) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Owner Company *</label>
          <select name="owner_company_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['company_id'] ?>"
                <?= ((string)$owner_company_id === (string)$c['company_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Counterparty Company *</label>
          <select name="counterparty_company_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['company_id'] ?>"
                <?= ((string)$counterparty_company_id === (string)$c['company_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Primary Contact</label>
          <?php if ($has_people_table): ?>
            <select name="primary_contact_id" class="form-select">
              <option value="">(none)</option>
              <?php foreach ($people as $p): ?>
                <option value="<?= (int)$p['person_id'] ?>"
                  <?= ((string)$primary_contact_id === (string)$p['person_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['full_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input name="primary_contact_id" class="form-control" value="<?= htmlspecialchars($primary_contact_id) ?>">
          <?php endif; ?>
        </div>

        <div class="col-12">
          <label class="form-label">Long Description (Scope of Work)</label>
          <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Create Contract</button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
