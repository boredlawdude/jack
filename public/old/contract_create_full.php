<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
// Creates a new contract record

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function null_if_blank($v) { $v = trim((string)$v); return $v === '' ? null : $v; }
function is_yyyy_mm_dd(string $d): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); }

/**
 * Build department initials from department_name, e.g. "Parks & Recreation" => "PR"
 * You can replace this with a department_code column later if you add one.
 */
function dept_initials(string $name): string {
  $name = strtoupper(trim($name));
  if ($name === '') return 'GEN';

  // keep letters/numbers/spaces
  $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name);
  $parts = preg_split('/\s+/', trim($name)) ?: [];

  // skip filler words
  $skip = ['AND','THE','OF','FOR','TO','&'];
  $letters = '';
  foreach ($parts as $p) {
    if ($p === '' || in_array($p, $skip, true)) continue;
    $letters .= $p[0];
    if (strlen($letters) >= 4) break; // cap length
  }
  return $letters !== '' ? $letters : 'GEN';
}

/**
 * Insert first with temp contract_number, then update to:
 *   YYYY_DEPT_######  where ###### is contract_id padded (global sequence)
 */
function insert_contract_with_autonumber(PDO $pdo, array $data, ?string $deptName): int {
  $tmp = 'TMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

  $ins = $pdo->prepare("
    INSERT INTO contracts (
      contract_number, name, status, type, governing_law,
      start_date, end_date, auto_renew, renewal_term_months,
      total_contract_value, currency,
      owner_company_id, counterparty_company_id,
      owner_primary_contact_id,
      department_id,
      description,
      contract_type_id
    ) VALUES (
      ?, ?, ?, ?, ?,
      ?, ?, ?, ?,
      ?, ?,
      ?, ?,
      ?,
      ?,
      ?,
      ?
    )
  ");

  $ins->execute([
    $tmp,
    $data['name'],
    $data['status'],
    $data['type'],
    $data['governing_law'],
    $data['start_date'],
    $data['end_date'],
    $data['auto_renew'],
    $data['renewal_term_months'],
    $data['total_contract_value'],
    $data['currency'],
    $data['owner_company_id'],
    $data['counterparty_company_id'],
    $data['owner_primary_contact_id'],
    $data['department_id'],
    $data['description'],
    $data['contract_type_id'],
  ]);

  $newId = (int)$pdo->lastInsertId();

  $year = date('Y');
  $dept = dept_initials((string)$deptName);
  $seq  = str_pad((string)$newId, 6, '0', STR_PAD_LEFT);
  $final = "{$year}_{$dept}_{$seq}";

  $up = $pdo->prepare("UPDATE contracts SET contract_number = ? WHERE contract_id = ?");
  $up->execute([$final, $newId]);

  return $newId;
}

/* --------------------------
   LOOKUPS
--------------------------- */

// Enum from contracts table
$statuses = ['draft','in_review','signed','expired','terminated'];

// Companies
$companies = $pdo->query("
  SELECT company_id, name
  FROM companies
  WHERE is_active = 1
  ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Departments (same as your logic)
if (is_system_admin()) {
  $departments = $pdo->query("
    SELECT department_id, department_name
    FROM departments
    WHERE is_active = 1
    ORDER BY department_name
  ")->fetchAll(PDO::FETCH_ASSOC);
} else {
  $st = $pdo->prepare("
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
  $st->execute([current_user_id()]);
  $departments = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Contract types
$contract_types = $pdo->query("
  SELECT contract_type_id, contract_type, formal_bidding_required
  FROM contract_types
  WHERE is_active = 1
  ORDER BY contract_type
")->fetchAll(PDO::FETCH_ASSOC);

// People (optional)
$people = [];
$has_people_table = false;
try {
  $people = $pdo->query("
    SELECT person_id, COALESCE(NULLIF(full_name,''), CONCAT(first_name,' ',last_name)) AS full_name
    FROM people
    WHERE is_active = 1
    ORDER BY last_name, first_name
  ")->fetchAll(PDO::FETCH_ASSOC);
  $has_people_table = true;
} catch (Throwable $e) {
  $has_people_table = false;
}

/* --------------------------
   STICKY FORM DEFAULTS
--------------------------- */

$errors = [];

$name = '';
$status = 'draft';
$type = '';
$governing_law = 'North Carolina';
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

/* --------------------------
   POST
--------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // read inputs
  $name = trim((string)($_POST['name'] ?? ''));
  $status = (string)($_POST['status'] ?? 'draft');
  $type = trim((string)($_POST['type'] ?? ''));
  $governing_law = trim((string)($_POST['governing_law'] ?? 'North Carolina'));
  $start_date = trim((string)($_POST['start_date'] ?? ''));
  $end_date = trim((string)($_POST['end_date'] ?? ''));
  $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
  $renewal_term_months = trim((string)($_POST['renewal_term_months'] ?? ''));
  $total_contract_value = trim((string)($_POST['total_contract_value'] ?? ''));
  $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
  $department_id = trim((string)($_POST['department_id'] ?? ''));
  $contract_type_id = trim((string)($_POST['contract_type_id'] ?? ''));

  $owner_company_id = trim((string)($_POST['owner_company_id'] ?? ''));
  $counterparty_company_id = trim((string)($_POST['counterparty_company_id'] ?? ''));
  $primary_contact_id = trim((string)($_POST['primary_contact_id'] ?? ''));
  $description = trim((string)($_POST['description'] ?? ''));

  // validation
  if ($name === '') $errors[] = "Name is required.";
  if (!in_array($status, $statuses, true)) $errors[] = "Invalid status.";

  if ($owner_company_id === '' || !ctype_digit($owner_company_id) || (int)$owner_company_id <= 0) {
    $errors[] = "Owner Company is required.";
  }
  if ($counterparty_company_id === '' || !ctype_digit($counterparty_company_id) || (int)$counterparty_company_id <= 0) {
    $errors[] = "Counterparty Company is required.";
  }
  if ($owner_company_id !== '' && $counterparty_company_id !== '' && (int)$owner_company_id === (int)$counterparty_company_id) {
    $errors[] = "Owner and Counterparty cannot be the same company.";
  }

  if ($contract_type_id !== '' && (!ctype_digit($contract_type_id) || (int)$contract_type_id <= 0)) {
    $errors[] = "Contract Type must be blank or a valid type.";
  }

  // Department rules (yours)
  if ($department_id !== '' && (!ctype_digit($department_id) || (int)$department_id <= 0)) {
    $errors[] = "Department must be blank or a valid department.";
  }
  if (!is_system_admin() && ($department_id === '' || (int)$department_id <= 0)) {
    $errors[] = "Department is required for contract admins.";
  }
  if ($department_id !== '' && (int)$department_id > 0) {
    if (!can_manage_contract_department((int)$department_id)) {
      $errors[] = "You do not have permission to create contracts for that department.";
    }
  }

  // dates
  if ($start_date !== '' && !is_yyyy_mm_dd($start_date)) $errors[] = "Start date must be YYYY-MM-DD.";
  if ($end_date !== '' && !is_yyyy_mm_dd($end_date)) $errors[] = "End date must be YYYY-MM-DD.";
  if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) $errors[] = "End date cannot be before start date.";

  // numeric
  if ($renewal_term_months !== '' && (!ctype_digit($renewal_term_months) || (int)$renewal_term_months < 0)) {
    $errors[] = "Renewal term months must be a non-negative integer.";
  }
  if ($total_contract_value !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $total_contract_value)) {
    $errors[] = "Total contract value must look like 1000 or 1000.00";
  }
  if ($currency !== '' && strlen($currency) !== 3) $errors[] = "Currency must be 3 letters (e.g., USD).";

  if ($primary_contact_id !== '' && (!ctype_digit($primary_contact_id) || (int)$primary_contact_id <= 0)) {
    $errors[] = "Primary contact must be blank or a valid person.";
  }

  if (!$errors) {
    // get department name for initials
    $deptName = null;
    if ($department_id !== '' && (int)$department_id > 0) {
      $dn = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ? LIMIT 1");
      $dn->execute([(int)$department_id]);
      $deptName = (string)($dn->fetchColumn() ?? '');
    }

    $data = [
      'name' => $name,
      'status' => $status,
      'type' => null_if_blank($type),
      'governing_law' => null_if_blank($governing_law) ?? 'North Carolina',
      'start_date' => null_if_blank($start_date),
      'end_date' => null_if_blank($end_date),
      'auto_renew' => $auto_renew,
      'renewal_term_months' => ($renewal_term_months === '' ? null : (int)$renewal_term_months),
      'total_contract_value' => ($total_contract_value === '' ? null : $total_contract_value),
      'currency' => ($currency === '' ? 'USD' : $currency),

      'owner_company_id' => (int)$owner_company_id,              // required by your validation
      'counterparty_company_id' => (int)$counterparty_company_id, // required

      'owner_primary_contact_id' => ($primary_contact_id === '' ? null : (int)$primary_contact_id),

      'department_id' => ($department_id === '' ? null : (int)$department_id),
      'description' => null_if_blank($description),
      'contract_type_id' => ($contract_type_id === '' ? null : (int)$contract_type_id),
    ];

    try {
      $pdo->beginTransaction();
      $newId = insert_contract_with_autonumber($pdo, $data, $deptName);
      $pdo->commit();

      header("Location: /contract_edit.php?id=" . $newId);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = "Database error: " . $e->getMessage();
    }
  }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">New Contract</h1>
  <a class="btn btn-outline-secondary btn-sm" href="/contracts_list.php">Back to Contracts</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <div class="row g-3">

        <div class="col-md-4">
          <label class="form-label">Contract #</label>
          <input class="form-control" value="(auto-assigned on Create)" readonly>
          <div class="form-text">Format: YYYY_DEPT_###### (global).</div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Short Description *</label>
          <input name="name" class="form-control" required value="<?= h($name) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Status *</label>
          <select name="status" class="form-select" required>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Contract Type</label>
          <select name="contract_type_id" class="form-select">
            <option value="">(none)</option>
            <?php foreach ($contract_types as $ct): ?>
              <option value="<?= (int)$ct['contract_type_id'] ?>" <?= ((string)$contract_type_id === (string)$ct['contract_type_id']) ? 'selected' : '' ?>>
                <?= h($ct['contract_type']) ?><?= ((int)$ct['formal_bidding_required'] === 1) ? ' (Formal bidding)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Department<?= is_system_admin() ? '' : ' *' ?></label>
          <select name="department_id" class="form-select" <?= is_system_admin() ? '' : 'required' ?>>
            <option value="">(none)</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['department_id'] ?>" <?= ((string)$department_id === (string)$d['department_id']) ? 'selected' : '' ?>>
                <?= h($d['department_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Type</label>
          <input name="type" class="form-control" value="<?= h($type) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Governing Law</label>
          <input name="governing_law" class="form-control" value="<?= h($governing_law ?: 'North Carolina') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Currency</label>
          <input name="currency" class="form-control" maxlength="3" value="<?= h($currency ?: 'USD') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Total Contract Value</label>
          <input name="total_contract_value" class="form-control" value="<?= h($total_contract_value) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" class="form-control" value="<?= h($start_date) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-control" value="<?= h($end_date) ?>">
        </div>

        <div class="col-md-3">
          <div class="form-check mt-4 pt-2">
            <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew" <?= ((int)$auto_renew === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="auto_renew">Auto-renew</label>
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Renewal Term (months)</label>
          <input name="renewal_term_months" class="form-control" type="number" min="0" value="<?= h($renewal_term_months) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Owner Company *</label>
          <select name="owner_company_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['company_id'] ?>" <?= ((string)$owner_company_id === (string)$c['company_id']) ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Counterparty Company *</label>
          <select name="counterparty_company_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['company_id'] ?>" <?= ((string)$counterparty_company_id === (string)$c['company_id']) ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
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
                <option value="<?= (int)$p['person_id'] ?>" <?= ((string)$primary_contact_id === (string)$p['person_id']) ? 'selected' : '' ?>>
                  <?= h($p['full_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input name="primary_contact_id" class="form-control" value="<?= h($primary_contact_id) ?>">
          <?php endif; ?>
        </div>

        <div class="col-12">
          <label class="form-label">Long Description (Scope of Work)</label>
          <textarea name="description" class="form-control" rows="4"><?= h($description) ?></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Create Contract</button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
