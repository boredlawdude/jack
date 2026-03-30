<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$company_id = (int)($_GET['id'] ?? 0);
if ($company_id <= 0) { http_response_code(400); exit('Missing company id'); }

/* ---- optional permission hook ---- */
if (function_exists('require_company_view_access')) {
  require_company_view_access($company_id);
}

/* ---- handle LINK EXISTING PERSON (must run before any HTML) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'link_person') {
  if (function_exists('can_edit_company') && !can_edit_company($company_id)) {
    http_response_code(403);
    exit('Forbidden');
  }

  $person_id = (int)($_POST['person_id'] ?? 0);
  if ($person_id <= 0) { http_response_code(400); exit('Invalid person id'); }

  $up = $pdo->prepare("UPDATE people SET company_id = ? WHERE person_id = ?");
  $up->execute([$company_id, $person_id]);

  header("Location: /company_edit.php?id=" . $company_id);
  exit;
}

/* ---- handle UNLINK PERSON ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlink_person') {
  if (function_exists('can_edit_company') && !can_edit_company($company_id)) {
    http_response_code(403);
    exit('Forbidden');
  }

  $person_id = (int)($_POST['person_id'] ?? 0);
  if ($person_id <= 0) { http_response_code(400); exit('Invalid person id'); }

  $up = $pdo->prepare("UPDATE people SET company_id = NULL WHERE person_id = ? AND company_id = ?");
  $up->execute([$person_id, $company_id]);

  header("Location: /company_edit.php?id=" . $company_id);
  exit;
}

/* ---- handle COMPANY UPDATE ---- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_company') {
  if (function_exists('can_edit_company') && !can_edit_company($company_id)) {
    http_response_code(403);
    exit('Forbidden');
  }

  $name = trim((string)($_POST['name'] ?? ''));
  $type = trim((string)($_POST['type'] ?? 'vendor'));
  $tax_id = trim((string)($_POST['tax_id'] ?? ''));

  $address_line1 = trim((string)($_POST['address_line1'] ?? ''));
  $address_line2 = trim((string)($_POST['address_line2'] ?? ''));
  $city = trim((string)($_POST['city'] ?? ''));
  $state_region = trim((string)($_POST['state_region'] ?? ''));
  $postal_code = trim((string)($_POST['postal_code'] ?? ''));
  $country = trim((string)($_POST['country'] ?? ''));

  $address = trim((string)($_POST['address'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $vendor_id = trim((string)($_POST['vendor_id'] ?? ''));
  $contact_name = trim((string)($_POST['contact_name'] ?? ''));
  $verified_by = trim((string)($_POST['verified_by'] ?? ''));

  $company_type_id = trim((string)($_POST['company_type_id'] ?? ''));
  $company_type_id_db = null;
  if ($company_type_id !== '' && ctype_digit($company_type_id) && (int)$company_type_id > 0) {
    $company_type_id_db = (int)$company_type_id;
  }

  $state_of_incorporation = trim((string)($_POST['state_of_incorporation'] ?? ''));
  $is_active = isset($_POST['is_active']) ? 1 : 0;
$coi_exp_date = ($_POST['coi_exp_date'] ?? '') ?: null;
$coi_carrier  = trim((string)($_POST['coi_carrier'] ?? ''));
$coi_verified_by_person_id = ($_POST['coi_verified_by_person_id'] ?? '') !== '' ? (int)$_POST['coi_verified_by_person_id'] : null;

  if ($name === '') $errors[] = "Company name is required.";


  if (!$errors) {
    $up = $pdo->prepare("
      UPDATE companies SET
        name = ?,
        type = ?,
        tax_id = ?,
        address_line1 = ?,
        address_line2 = ?,
        city = ?,
        state_region = ?,
        postal_code = ?,
        country = ?,
        address = ?,
        phone = ?,
        email = ?,
        vendor_id = ?,
        contact_name = ?,
        verified_by = ?,
        company_type_id = ?,
        state_of_incorporation = ?,
        is_active = ?,
        coi_exp_date = ?,
        coi_carrier = ?,
        coi_verified_by_person_id = ?

      WHERE company_id = ?
    ");
    $up->execute([
      $name,
      $type,
      $tax_id !== '' ? $tax_id : null,
      $address_line1 !== '' ? $address_line1 : null,
      $address_line2 !== '' ? $address_line2 : null,
      $city !== '' ? $city : null,
      $state_region !== '' ? $state_region : null,
      $postal_code !== '' ? $postal_code : null,
      $country !== '' ? $country : null,
      $address !== '' ? $address : null,
      $phone !== '' ? $phone : null,
      $email !== '' ? $email : null,
      $vendor_id !== '' ? $vendor_id : null,
      $contact_name !== '' ? $contact_name : null,
      $verified_by !== '' ? $verified_by : null,
      $company_type_id_db,
      $state_of_incorporation !== '' ? $state_of_incorporation : null,
      $is_active,
      $coi_exp_date,  
      $coi_carrier,
      $coi_verified_by_person_id,
      $company_id
    ]);

    header("Location: /company_edit.php?id=" . $company_id);
    exit;
  }
}

/* ---- load company ---- */
$stmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ? LIMIT 1");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$company) { http_response_code(404); exit('Company not found'); }

/* ---- load company types (optional table) ---- */
$companyTypes = [];
try {
  $companyTypes = $pdo->query("
    SELECT company_type_id, company_type
    FROM company_types
    WHERE is_active = 1
    ORDER BY company_type
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // ignore if table doesn't exist yet
}

/* ---- load people for link modal ---- */
$linkPeople = $pdo->query("
  SELECT person_id, first_name, last_name, email, company_id
  FROM people
  WHERE is_active = 1
  ORDER BY last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ---- load employees linked to this company ---- */
$empQ = $pdo->prepare("
  SELECT
    p.person_id,
    CONCAT_WS(' ', p.first_name, p.last_name) AS person_name,
    p.email,
    p.officephone,
    p.cellphone,
    p.is_town_employee,
    d.department_name,
    p.is_active
  FROM people p
  LEFT JOIN departments d ON d.department_id = p.department_id
  WHERE p.company_id = ?
  ORDER BY p.is_active DESC, p.last_name, p.first_name
");
$empQ->execute([$company_id]);
$employees = $empQ->fetchAll(PDO::FETCH_ASSOC);

// Load town employees for COI
$townEmployees = $pdo->query("
  SELECT person_id,
         COALESCE(NULLIF(full_name,''), CONCAT_WS(' ', first_name, last_name)) AS display_name,
         email
  FROM people
  WHERE is_active = 1
    AND is_town_employee = 1
  ORDER BY display_name
")->fetchAll(PDO::FETCH_ASSOC);






include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">Edit Company</h1>
<div class="text-muted small mb-3">Company ID: <?= (int)$company_id ?></div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm mb-4">
  <input type="hidden" name="action" value="save_company">
  <div class="card-body">
    <div class="row g-3">

      <div class="col-md-6">
        <label class="form-label">Company Name</label>
        <input class="form-control" name="name" value="<?= h($company['name'] ?? '') ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Company Category</label>
        <select class="form-select" name="type">
          <?php foreach (['internal','customer','vendor','partner','other'] as $t): ?>
            <option value="<?= h($t) ?>" <?= (($company['type'] ?? '') === $t) ? 'selected' : '' ?>>
              <?= h(ucfirst($t)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Active</label>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
            <?= ((int)($company['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active">Active</label>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label">Company Type (LLC/Corp/etc.)</label>
        <select class="form-select" name="company_type_id">
          <option value="">(none)</option>
          <?php foreach ($companyTypes as $ct): ?>
            <option value="<?= (int)$ct['company_type_id'] ?>"
              <?= ((string)($company['company_type_id'] ?? '') === (string)$ct['company_type_id']) ? 'selected' : '' ?>>
              <?= h($ct['company_type']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">State of Incorporation</label>
        <input class="form-control" name="state_of_incorporation" value="<?= h($company['state_of_incorporation'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Vendor ID</label>
        <input class="form-control" name="vendor_id" value="<?= h($company['vendor_id'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input class="form-control" name="phone" value="<?= h($company['phone'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" value="<?= h($company['email'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Tax ID</label>
        <input class="form-control" name="tax_id" value="<?= h($company['tax_id'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Contact Name</label>
        <input class="form-control" name="contact_name" value="<?= h($company['contact_name'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Verified By (Town staff)</label>
        <input class="form-control" name="verified_by" value="<?= h($company['verified_by'] ?? '') ?>">
      </div>
      <div class="row g-3 mt-1">

      <div class="col-md-4">
        <label class="form-label">COI Expiration Date</label>
        <input type="date"
              name="coi_exp_date"
              class="form-control"
              value="<?= h($company['coi_exp_date'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">COI Carrier</label>
        <input name="coi_carrier"
              class="form-control"  
              value="<?= h($company['coi_carrier'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">COI Verified By</label>
        <select name="coi_verified_by_person_id" class="form-select">
          <option value="">(not verified)</option>
          <?php foreach ($townEmployees as $p): ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($company['coi_verified_by_person_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($p['display_name']) ?><?= $p['email'] ? ' — ' . h($p['email']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

</div>


      <div class="col-12">
        <label class="form-label">Address (single line)</label>
        <input class="form-control" name="address" value="<?= h($company['address'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Address Line 1</label>
        <input class="form-control" name="address_line1" value="<?= h($company['address_line1'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Address Line 2</label>
        <input class="form-control" name="address_line2" value="<?= h($company['address_line2'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">City</label>
        <input class="form-control" name="city" value="<?= h($company['city'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">State/Region</label>
        <input class="form-control" name="state_region" value="<?= h($company['state_region'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Postal Code</label>
        <input class="form-control" name="postal_code" value="<?= h($company['postal_code'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Country</label>
        <input class="form-control" name="country" value="<?= h($company['country'] ?? '') ?>">
      </div>

    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-outline-secondary" href="/companies_list.php">Back</a>
  </div>
</form>

<!-- People at this company -->
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="fw-semibold">People at this Company</div>
    <div class="d-flex gap-2">

      <a class="btn btn-sm btn-outline-primary"
         href="/index.php?page=people_create&company_id=<?= (int)$company_id ?>">
        Add New Person
      </a>

      <button type="button"
              class="btn btn-sm btn-outline-secondary"
              data-bs-toggle="modal"
              data-bs-target="#linkPersonModal">
        Link Existing Person
      </button>
    </div>
  </div>

  <div class="card-body p-0">
    <?php if (!$employees): ?>
      <div class="p-3 text-muted">No people linked to this company yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Office</th>
              <th>Cell</th>
              <th>Town?</th>
              <th>Department</th>
              <th>Active</th>
              <th style="width: 220px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $e): ?>
              <tr>
                <td><?= h($e['person_name'] ?? '') ?></td>
                <td><?= h($e['email'] ?? '') ?></td>
                <td><?= h($e['officephone'] ?? '') ?></td>
                <td><?= h($e['cellphone'] ?? '') ?></td>
                <td><?= ((int)($e['is_town_employee'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                <td><?= h($e['department_name'] ?? '') ?></td>
                <td><?= ((int)($e['is_active'] ?? 1) === 1) ? 'Yes' : 'No' ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary"
                     href="/people_edit.php?id=<?= (int)$e['person_id'] ?>">
                    Edit
                  </a>

                  <form method="post" class="d-inline" onsubmit="return confirm('Unlink this person from this company?');">
                    <input type="hidden" name="action" value="unlink_person">
                    <input type="hidden" name="person_id" value="<?= (int)$e['person_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Unlink</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Link Existing Person Modal (NOT nested inside another form) -->
<div class="modal fade" id="linkPersonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="link_person">

      <div class="modal-header">
        <h5 class="modal-title">Link Existing Person</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">Select Person</label>
        <select name="person_id" class="form-select" required>
          <option value="">Select…</option>
          <?php foreach ($linkPeople as $p): ?>
            <?php
              $name = trim(($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''));
              if ($name === ',' || $name === '') $name = 'Person #' . (int)$p['person_id'];
              $label = $name . (!empty($p['email']) ? ' — ' . $p['email'] : '');
              if (!empty($p['company_id'])) $label .= ' (currently linked)';
            ?>
            <option value="<?= (int)$p['person_id'] ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>

        <?php if (!$linkPeople): ?>
          <div class="text-muted small mt-2">No active people found.</div>
        <?php endif; ?>

        <div class="form-text mt-2">
          Linking will assign the person’s company_id to this company.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Link</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS required for modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/company_comments_block.php'; ?>
<?php include __DIR__ . '/footer.php'; ?>
