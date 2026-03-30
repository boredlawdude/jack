<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Permission gate (optional)
if (function_exists('can_edit_company') && !can_edit_company(0)) {
  // if your helper expects a real company id, just skip this check
}

$errors = [];
$ok = false;

// Company types dropdown (if you have this)
$companyTypes = [];
try {
  $companyTypes = $pdo->query("
    SELECT company_type_id, company_type
    FROM company_types
    WHERE is_active = 1
    ORDER BY company_type
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $companyTypes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ---- company fields ----
  $name = trim((string)($_POST['name'] ?? ''));
  $type = trim((string)($_POST['type'] ?? 'vendor'));
  $tax_id = trim((string)($_POST['tax_id'] ?? ''));

  $address_line1 = trim((string)($_POST['address_line1'] ?? ''));
  $address_line2 = trim((string)($_POST['address_line2'] ?? ''));
  $city = trim((string)($_POST['city'] ?? ''));
  $state_region = trim((string)($_POST['state_region'] ?? ''));
  $postal_code = trim((string)($_POST['postal_code'] ?? ''));
  $country = trim((string)($_POST['country'] ?? ''));

  $phone = trim((string)($_POST['phone'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $vendor_id = trim((string)($_POST['vendor_id'] ?? ''));
  $verified_by = trim((string)($_POST['verified_by'] ?? ''));

  // 👇 UI label is “Company Website” but we store in contact_name for now
  $company_website = trim((string)($_POST['company_website'] ?? ''));

  $company_type_id = ($_POST['company_type_id'] ?? '') !== '' ? (int)$_POST['company_type_id'] : null;
  $state_of_incorporation = trim((string)($_POST['state_of_incorporation'] ?? ''));

  if ($name === '') $errors[] = "Company Name is required.";

  // ---- people rows (up to 5, adjust as you like) ----
  $peopleRows = $_POST['people'] ?? [];
  if (!is_array($peopleRows)) $peopleRows = [];

  // Validate people minimally (optional)
  $cleanPeople = [];
  foreach ($peopleRows as $row) {
    if (!is_array($row)) continue;

    $first = trim((string)($row['first_name'] ?? ''));
    $last  = trim((string)($row['last_name'] ?? ''));
    $full  = trim((string)($row['full_name'] ?? ''));
    $pemail = trim((string)($row['email'] ?? ''));
    $office = trim((string)($row['officephone'] ?? ''));
    $cell   = trim((string)($row['cellphone'] ?? ''));

    // Skip completely blank rows
    if ($first === '' && $last === '' && $full === '' && $pemail === '' && $office === '' && $cell === '') {
      continue;
    }

    // Require at least a name
    if ($full === '' && ($first === '' || $last === '')) {
      $errors[] = "Each person needs either Full Name or First+Last name.";
      break;
    }

    $cleanPeople[] = [
      'first_name' => $first,
      'last_name' => $last,
      'full_name' => $full,
      'email' => $pemail,
      'officephone' => $office,
      'cellphone' => $cell,
    ];
  }

  if (!$errors) {
    $pdo->beginTransaction();
    try {
      // Insert company
      $ins = $pdo->prepare("
        INSERT INTO companies
          (name, type, tax_id,
           address_line1, address_line2, city, state_region, postal_code, country,
           phone, email, vendor_id, verified_by,
           contact_name,
           company_type_id, state_of_incorporation,
           is_active)
        VALUES
          (?, ?, ?,
           ?, ?, ?, ?, ?, ?,
           ?, ?, ?, ?,
           ?,
           ?, ?,
           1)
      ");
      $ins->execute([
        $name,
        $type,
        $tax_id !== '' ? $tax_id : null,
        $address_line1 !== '' ? $address_line1 : null,
        $address_line2 !== '' ? $address_line2 : null,
        $city !== '' ? $city : null,
        $state_region !== '' ? $state_region : null,
        $postal_code !== '' ? $postal_code : null,
        $country !== '' ? $country : null,
        $phone !== '' ? $phone : null,
        $email !== '' ? $email : null,
        $vendor_id !== '' ? $vendor_id : null,
        $verified_by !== '' ? $verified_by : null,

        $company_website !== '' ? $company_website : null,

        $company_type_id,
        $state_of_incorporation !== '' ? $state_of_incorporation : null,
      ]);

      $company_id = (int)$pdo->lastInsertId();

      // Insert people (linked to company_id)
      if ($cleanPeople) {
        $pIns = $pdo->prepare("
          INSERT INTO people
            (company_id, first_name, last_name, full_name, email, officephone, cellphone, is_active)
          VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($cleanPeople as $p) {
          $pIns->execute([
            $company_id,
            $p['first_name'] !== '' ? $p['first_name'] : null,
            $p['last_name']  !== '' ? $p['last_name']  : null,
            $p['full_name']  !== '' ? $p['full_name']  : null,
            $p['email']      !== '' ? $p['email']      : null,
            $p['officephone']!== '' ? $p['officephone']: null,
            $p['cellphone']  !== '' ? $p['cellphone']  : null,
          ]);
        }
      }

      $pdo->commit();
      header("Location: /company_edit.php?id=" . $company_id);
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = "Database error: " . $e->getMessage();
    }
  }
}

include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">New Company</h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <div class="row g-3">

      <div class="col-md-8">
        <label class="form-label">Company Name</label>
        <input class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Company Category</label>
        <select class="form-select" name="type">
          <?php
            $opts = ['internal','customer','vendor','partner','other'];
            $cur = (string)($_POST['type'] ?? 'vendor');
            foreach ($opts as $o):
          ?>
            <option value="<?= h($o) ?>" <?= $cur === $o ? 'selected' : '' ?>><?= h(ucfirst($o)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Company Type (LLC/Corp/etc.)</label>
        <select class="form-select" name="company_type_id">
          <option value="">(none)</option>
          <?php $curCT = (string)($_POST['company_type_id'] ?? ''); ?>
          <?php foreach ($companyTypes as $ct): ?>
            <option value="<?= (int)$ct['company_type_id'] ?>" <?= $curCT === (string)$ct['company_type_id'] ? 'selected' : '' ?>>
              <?= h($ct['company_type']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">State of Incorporation</label>
        <input class="form-control" name="state_of_incorporation" value="<?= h($_POST['state_of_incorporation'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Company Website</label>
        <input class="form-control" name="company_website" value="<?= h($_POST['company_website'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input class="form-control" name="phone" value="<?= h($_POST['phone'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" value="<?= h($_POST['email'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Vendor ID</label>
        <input class="form-control" name="vendor_id" value="<?= h($_POST['vendor_id'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Verified by</label>
        <input class="form-control" name="verified_by" value="<?= h($_POST['verified_by'] ?? '') ?>">
      </div>

      <div class="col-12"><hr class="my-2"></div>

      <div class="col-12">
        <label class="form-label">Address</label>
        <input class="form-control mb-2" name="address_line1" placeholder="Line 1" value="<?= h($_POST['address_line1'] ?? '') ?>">
        <input class="form-control" name="address_line2" placeholder="Line 2" value="<?= h($_POST['address_line2'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">City</label>
        <input class="form-control" name="city" value="<?= h($_POST['city'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">State/Region</label>
        <input class="form-control" name="state_region" value="<?= h($_POST['state_region'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Postal Code</label>
        <input class="form-control" name="postal_code" value="<?= h($_POST['postal_code'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Country</label>
        <input class="form-control" name="country" value="<?= h($_POST['country'] ?? '') ?>">
      </div>

      <div class="col-12"><hr class="my-2"></div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">People at this Company (optional)</h2>
          <div class="text-muted small">Fill any rows you want; blank rows are ignored.</div>
        </div>
      </div>

      <?php for ($i=0; $i<5; $i++): ?>
        <div class="col-md-3">
          <label class="form-label">First Name</label>
          <input class="form-control" name="people[<?= $i ?>][first_name]" value="<?= h($_POST['people'][$i]['first_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Name</label>
          <input class="form-control" name="people[<?= $i ?>][last_name]" value="<?= h($_POST['people'][$i]['last_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input class="form-control" name="people[<?= $i ?>][email]" value="<?= h($_POST['people'][$i]['email'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Office Phone</label>
          <input class="form-control" name="people[<?= $i ?>][officephone]" value="<?= h($_POST['people'][$i]['officephone'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Cell Phone</label>
          <input class="form-control" name="people[<?= $i ?>][cellphone]" value="<?= h($_POST['people'][$i]['cellphone'] ?? '') ?>">
        </div>
        <div class="col-md-9">
          <label class="form-label">Full Name (optional override)</label>
          <input class="form-control" name="people[<?= $i ?>][full_name]" value="<?= h($_POST['people'][$i]['full_name'] ?? '') ?>">
          <div class="form-text">If filled, it can be used for display instead of First+Last.</div>
        </div>

        <div class="col-12"><hr class="my-2"></div>
      <?php endfor; ?>

    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary">Create Company</button>
    <a class="btn btn-outline-secondary" href="/companies_list.php">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
