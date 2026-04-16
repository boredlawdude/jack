<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_ROOT . '/app/models/DevelopmentAgreementSubmission.php';

// ── Handle form submission ────────────────────────────────────────────────────
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $projectName = trim((string)($_POST['project_name'] ?? ''));
    $subName     = trim((string)($_POST['submitter_name']  ?? ''));
    $subEmail    = trim((string)($_POST['submitter_email'] ?? ''));

    if ($projectName === '') $errors[] = 'Project name is required.';
    if ($subName === '')     $errors[] = 'Your name is required.';
    if ($subEmail === '')    $errors[] = 'Your email address is required.';
    elseif (!filter_var($subEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    // Honeypot (spam prevention)
    if (!empty($_POST['_hp_field'])) {
        // Silent discard — bot filled a hidden field
        $success = true;
    }

    if (empty($errors) && !$success) {
        // Collect and sanitize tracts
        $rawTracts = $_POST['tracts'] ?? [];
        $tracts    = [];
        foreach ($rawTracts as $t) {
            $pin     = trim((string)($t['property_pin']          ?? ''));
            $address = trim((string)($t['property_address']       ?? ''));
            $reid    = trim((string)($t['property_realestateid']  ?? ''));
            if ($pin === '' && $address === '' && $reid === '') continue;
            $tracts[] = [
                'property_pin'          => $pin,
                'property_address'      => $address,
                'property_realestateid' => $reid,
                'property_acerage'      => trim((string)($t['property_acerage'] ?? '')),
                'owner_name'            => trim((string)($t['owner_name']       ?? '')),
            ];
        }

        $db    = db();
        $model = new DevelopmentAgreementSubmission($db);
        $model->create([
            'submitter_name'             => $subName,
            'submitter_email'            => $subEmail,
            'submitter_phone'            => trim((string)($_POST['submitter_phone']   ?? '')),
            'submitter_company'          => trim((string)($_POST['submitter_company'] ?? '')),
            'project_name'               => $projectName,
            'project_description'        => trim((string)($_POST['project_description']   ?? '')),
            'proposed_improvements'      => trim((string)($_POST['proposed_improvements'] ?? '')),
            'current_zoning'             => trim((string)($_POST['current_zoning']         ?? '')),
            'proposed_zoning'            => trim((string)($_POST['proposed_zoning']         ?? '')),
            'comp_plan_designation'      => trim((string)($_POST['comp_plan_designation']   ?? '')),
            'anticipated_start_date'     => $_POST['anticipated_start_date']     ?: null,
            'anticipated_end_date'       => $_POST['anticipated_end_date']       ?: null,
            'agreement_termination_date' => $_POST['agreement_termination_date'] ?: null,
            'planning_board_date'        => $_POST['planning_board_date']        ?: null,
            'town_council_hearing_date'  => $_POST['town_council_hearing_date']  ?: null,
            'tracts_json'                => json_encode($tracts),
        ]);

        $success = true;
    }
}

$appName = defined('APP_NAME') ? APP_NAME : 'Contracts';

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Re-populate form on error
$old = (!$success && $_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Development Agreement Intake — <?= h($appName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; }
    .intake-header { background: linear-gradient(90deg, #1e3a5f, #2c5d8a); color: #fff; padding: 1.2rem 0; margin-bottom: 2rem; }
    .intake-header h1 { font-size: 1.4rem; font-weight: 600; margin: 0; }
    .intake-header p  { margin: 0; font-size: .9rem; opacity: .85; }
    .section-label { font-size: .7rem; text-transform: uppercase; font-weight: 600; letter-spacing: .05em; color: #6c757d; border-bottom: 1px solid #dee2e6; padding-bottom: .4rem; margin-bottom: 1rem; }
  </style>
</head>
<body>
<div class="intake-header">
  <div class="container">
    <h1><?= h($appName) ?> — Development Agreement Intake</h1>
    <p>Complete this form to submit your development agreement information for review.</p>
  </div>
</div>

<div class="container" style="max-width:860px">

<?php if ($success): ?>
  <div class="card shadow-sm border-success mb-5">
    <div class="card-body text-center py-5">
      <div class="display-3 mb-3">✓</div>
      <h2 class="h4 text-success mb-2">Submission Received</h2>
      <p class="text-muted mb-0">Thank you. Your development agreement information has been submitted for review. You will be contacted if additional information is needed.</p>
    </div>
  </div>
<?php else: ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" action="/dev_agreement_intake.php">
  <!-- Honeypot -->
  <div style="display:none" aria-hidden="true">
    <input type="text" name="_hp_field" tabindex="-1" autocomplete="off">
  </div>

  <!-- ── Your Information ───────────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Your Information</p>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="submitter_name" required maxlength="200"
                 value="<?= h($old['submitter_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control" name="submitter_email" required maxlength="200"
                 value="<?= h($old['submitter_email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <input type="tel" class="form-control" name="submitter_phone" maxlength="50"
                 value="<?= h($old['submitter_phone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Company / Organization</label>
          <input type="text" class="form-control" name="submitter_company" maxlength="200"
                 value="<?= h($old['submitter_company'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Project Information ───────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Project Information</p>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Project Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="project_name" required maxlength="255"
                 value="<?= h($old['project_name'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Project Description</label>
          <textarea class="form-control" name="project_description" rows="4"><?= h($old['project_description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Proposed Improvements</label>
          <textarea class="form-control" name="proposed_improvements" rows="3"><?= h($old['proposed_improvements'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Property Tracts ───────────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Property Tracts</p>
      <p class="text-muted small mb-3">Add each parcel of land covered by this agreement. Enter the Wake County PIN and click <strong>Lookup</strong> to auto-fill address and acreage from public records.</p>

      <div id="tracts-container">
        <?php
        $initTracts = !empty($old['tracts']) ? $old['tracts'] : [['property_pin'=>'','property_realestateid'=>'','property_address'=>'','property_acerage'=>'','owner_name'=>'']];
        foreach ($initTracts as $ti => $tract):
        ?>
        <div class="tract-row card mb-2 border bg-light" data-index="<?= $ti ?>">
          <div class="card-body p-3">
            <input type="hidden" name="tracts[<?= $ti ?>][tract_id]" value="">
            <div class="row g-2 align-items-end">
              <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Wake County PIN</label>
                <div class="input-group input-group-sm">
                  <input type="text" class="form-control tract-pin" name="tracts[<?= $ti ?>][property_pin]"
                         value="<?= h($tract['property_pin'] ?? '') ?>" maxlength="15" placeholder="digits only">
                  <button type="button" class="btn btn-outline-primary tract-lookup-btn"
                          onclick="lookupTractPin(this)">Lookup</button>
                </div>
                <div class="tract-status form-text"></div>
              </div>
              <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Real Estate ID</label>
                <input type="text" class="form-control form-control-sm tract-reid"
                       name="tracts[<?= $ti ?>][property_realestateid]"
                       value="<?= h($tract['property_realestateid'] ?? '') ?>" maxlength="50">
              </div>
              <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Property Address</label>
                <input type="text" class="form-control form-control-sm tract-address"
                       name="tracts[<?= $ti ?>][property_address]"
                       value="<?= h($tract['property_address'] ?? '') ?>">
              </div>
              <div class="col-md-1">
                <label class="form-label form-label-sm mb-1">Acres</label>
                <input type="number" step="0.0001" min="0" class="form-control form-control-sm tract-acreage"
                       name="tracts[<?= $ti ?>][property_acerage]"
                       value="<?= h($tract['property_acerage'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Property Owner</label>
                <input type="text" class="form-control form-control-sm tract-owner"
                       name="tracts[<?= $ti ?>][owner_name]"
                       value="<?= h($tract['owner_name'] ?? '') ?>"
                       placeholder="Auto-filled by Lookup">
              </div>
              <div class="col-auto d-flex align-items-end pb-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTract(this)">✕</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addTract()">+ Add Another Parcel</button>
    </div>
  </div>

  <!-- ── Zoning ────────────────────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Zoning</p>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Current Zoning</label>
          <input type="text" class="form-control" name="current_zoning" maxlength="100"
                 value="<?= h($old['current_zoning'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Proposed Zoning</label>
          <input type="text" class="form-control" name="proposed_zoning" maxlength="100"
                 value="<?= h($old['proposed_zoning'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Comp Plan Designation</label>
          <input type="text" class="form-control" name="comp_plan_designation" maxlength="200"
                 value="<?= h($old['comp_plan_designation'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Dates ─────────────────────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Anticipated Dates <span class="fw-normal text-muted">(optional)</span></p>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Anticipated Start</label>
          <input type="date" class="form-control" name="anticipated_start_date"
                 value="<?= h($old['anticipated_start_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Anticipated End</label>
          <input type="date" class="form-control" name="anticipated_end_date"
                 value="<?= h($old['anticipated_end_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Agreement Termination</label>
          <input type="date" class="form-control" name="agreement_termination_date"
                 value="<?= h($old['agreement_termination_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Planning Board Date</label>
          <input type="date" class="form-control" name="planning_board_date"
                 value="<?= h($old['planning_board_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Town Council Hearing</label>
          <input type="date" class="form-control" name="town_council_hearing_date"
                 value="<?= h($old['town_council_hearing_date'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="d-grid mb-5">
    <button type="submit" class="btn btn-primary btn-lg">Submit Development Agreement Information</button>
  </div>
</form>
<?php endif; ?>
</div><!-- /container -->

<footer class="text-center text-muted small py-4">
  <?= h($appName) ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function nextTractIndex() {
  return document.querySelectorAll('.tract-row').length;
}

function buildTractRowHtml(idx) {
  return `
    <div class="tract-row card mb-2 border bg-light" data-index="${idx}">
      <div class="card-body p-3">
        <input type="hidden" name="tracts[${idx}][tract_id]" value="">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Wake County PIN</label>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control tract-pin" name="tracts[${idx}][property_pin]"
                     maxlength="15" placeholder="digits only">
              <button type="button" class="btn btn-outline-primary tract-lookup-btn"
                      onclick="lookupTractPin(this)">Lookup</button>
            </div>
            <div class="tract-status form-text"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Real Estate ID</label>
            <input type="text" class="form-control form-control-sm tract-reid"
                   name="tracts[${idx}][property_realestateid]" maxlength="50">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Property Address</label>
            <input type="text" class="form-control form-control-sm tract-address"
                   name="tracts[${idx}][property_address]">
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-1">Acres</label>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm tract-acreage"
                   name="tracts[${idx}][property_acerage]">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Property Owner</label>
            <input type="text" class="form-control form-control-sm tract-owner"
                   name="tracts[${idx}][owner_name]" placeholder="Auto-filled by Lookup">
          </div>
          <div class="col-auto d-flex align-items-end pb-1">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTract(this)">✕</button>
          </div>
        </div>
      </div>
    </div>`;
}

function addTract() {
  const container = document.getElementById('tracts-container');
  container.insertAdjacentHTML('beforeend', buildTractRowHtml(nextTractIndex()));
}

function removeTract(btn) {
  const row = btn.closest('.tract-row');
  if (document.querySelectorAll('.tract-row').length <= 1) {
    row.querySelectorAll('input[type=text], input[type=number]').forEach(i => i.value = '');
    return;
  }
  row.remove();
  // Reindex
  document.querySelectorAll('.tract-row').forEach((row, idx) => {
    row.dataset.index = idx;
    row.querySelectorAll('[name]').forEach(el => {
      el.name = el.name.replace(/tracts\[\d+\]/, `tracts[${idx}]`);
    });
  });
}

async function lookupTractPin(btn) {
  const row      = btn.closest('.tract-row');
  const pinEl    = row.querySelector('.tract-pin');
  const statusEl = row.querySelector('.tract-status');
  const pin      = pinEl.value.trim();

  if (!pin || !/^\d{1,15}$/.test(pin)) {
    statusEl.className   = 'tract-status form-text text-danger';
    statusEl.textContent = 'Enter a numeric PIN first.';
    return;
  }

  btn.disabled    = true;
  btn.textContent = '…';
  statusEl.className   = 'tract-status form-text';
  statusEl.textContent = 'Looking up…';

  try {
    const res  = await fetch('/devagr_imaps_lookup_public.php?pin=' + encodeURIComponent(pin));
    const data = await res.json();

    if (data.error) {
      statusEl.className   = 'tract-status form-text text-danger';
      statusEl.textContent = data.error;
      return;
    }

    row.querySelector('.tract-reid').value    = data.property_realestateid ?? '';
    row.querySelector('.tract-address').value = data.property_address      ?? '';
    if (data.property_acerage != null) {
      row.querySelector('.tract-acreage').value = data.property_acerage;
    }
    if (data.owner_name) {
      row.querySelector('.tract-owner').value = data.owner_name;
    }

    statusEl.className   = 'tract-status form-text text-success';
    statusEl.textContent = '✓ Filled from Wake County public records.';
  } catch {
    statusEl.className   = 'tract-status form-text text-danger';
    statusEl.textContent = 'Lookup failed — please fill in manually.';
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Lookup';
  }
}
</script>
</body>
</html>
