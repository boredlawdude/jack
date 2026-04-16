<?php
declare(strict_types=1);
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$isEdit       = ($mode ?? 'create') === 'edit';
$agrId        = (int)($agreement['dev_agreement_id'] ?? 0);
$action       = $isEdit
    ? '/index.php?page=development_agreements_update&dev_agreement_id=' . $agrId
    : '/index.php?page=development_agreements_store';
$v = fn($field) => h($agreement[$field] ?? '');
?>

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto"><?= $isEdit ? 'Edit Development Agreement' : 'New Development Agreement' ?></h1>
    <a href="/index.php?page=development_agreements" class="btn btn-outline-secondary btn-sm">Back to List</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" class="card shadow-sm">
  <?php if ($isEdit): ?>
    <input type="hidden" name="dev_agreement_id" value="<?= $agrId ?>">
  <?php endif; ?>

  <div class="card-body">

    <!-- Project Info -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Project Information</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-8">
        <label class="form-label" for="project_name">Project Name <span class="text-danger">*</span></label>
        <input class="form-control" type="text" id="project_name" name="project_name" required
               value="<?= $v('project_name') ?>">
      </div>
      <div class="col-12">
        <label class="form-label" for="project_description">Project Description</label>
        <textarea class="form-control" id="project_description" name="project_description"
                  rows="4"><?= $v('project_description') ?></textarea>
      </div>
      <div class="col-12">
        <label class="form-label" for="proposed_improvements">Proposed Improvements</label>
        <textarea class="form-control" id="proposed_improvements" name="proposed_improvements"
                  rows="4"><?= $v('proposed_improvements') ?></textarea>
      </div>
    </div>

    <!-- Parties -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Parties</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <label class="form-label" for="applicant_id">Applicant</label>
        <select class="form-select" id="applicant_id" name="applicant_id">
          <option value="">— Select —</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($agreement['applicant_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($p['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="property_owner_id">Property Owner</label>
        <select class="form-select" id="property_owner_id" name="property_owner_id">
          <option value="">— Select —</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($agreement['property_owner_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($p['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="attorney_id">Attorney</label>
        <select class="form-select" id="attorney_id" name="attorney_id">
          <option value="">— Select —</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($agreement['attorney_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($p['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Property / Tracts -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Property Tracts</h6>
    <p class="text-muted small mb-3">Add one or more parcels. Enter a Wake County PIN and click <strong>Lookup</strong> to auto-fill the address, acreage, and real estate ID from IMAPS.</p>

    <div id="tracts-container">
      <?php
        // Render existing tracts (edit mode) or one blank row (create mode)
        $existingTracts = $agreement['tracts'] ?? [];
        if (empty($existingTracts)) {
            $existingTracts = [['tract_id' => '', 'property_pin' => '', 'property_realestateid' => '', 'property_address' => '', 'property_acerage' => '', 'owner_name' => '', 'sort_order' => 0]];
        }
      ?>
      <?php foreach ($existingTracts as $ti => $tract): ?>
      <div class="tract-row card mb-2 border" data-index="<?= $ti ?>">
        <div class="card-body p-3">
          <input type="hidden" name="tracts[<?= $ti ?>][tract_id]" value="<?= h($tract['tract_id'] ?? '') ?>">
          <div class="row g-2 align-items-end">
            <!-- PIN + Lookup -->
            <div class="col-md-2">
              <label class="form-label form-label-sm mb-1">PIN</label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control tract-pin" name="tracts[<?= $ti ?>][property_pin]"
                       value="<?= h($tract['property_pin'] ?? '') ?>" maxlength="15" placeholder="digits only">
                <button type="button" class="btn btn-outline-primary tract-lookup-btn"
                        onclick="lookupTractPin(this)" title="Lookup from Wake County IMAPS">Lookup</button>
              </div>
              <div class="tract-status form-text"></div>
            </div>
            <!-- Real Estate ID -->
            <div class="col-md-2">
              <label class="form-label form-label-sm mb-1">Real Estate ID</label>
              <input type="text" class="form-control form-control-sm tract-reid" name="tracts[<?= $ti ?>][property_realestateid]"
                     value="<?= h($tract['property_realestateid'] ?? '') ?>" maxlength="50">
            </div>
            <!-- Address -->
            <div class="col-md-3">
              <label class="form-label form-label-sm mb-1">Property Address</label>
              <input type="text" class="form-control form-control-sm tract-address" name="tracts[<?= $ti ?>][property_address]"
                     value="<?= h($tract['property_address'] ?? '') ?>">
            </div>
            <!-- Acreage -->
            <div class="col-md-1">
              <label class="form-label form-label-sm mb-1">Acres</label>
              <input type="number" step="0.0001" min="0" class="form-control form-control-sm tract-acreage"
                     name="tracts[<?= $ti ?>][property_acerage]"
                     value="<?= h($tract['property_acerage'] ?? '') ?>">
            </div>
            <!-- Property Owner -->
            <div class="col-md-3">
              <label class="form-label form-label-sm mb-1">Property Owner</label>
              <input type="text" class="form-control form-control-sm tract-owner"
                     name="tracts[<?= $ti ?>][owner_name]"
                     value="<?= h($tract['owner_name'] ?? '') ?>"
                     placeholder="Auto-filled from IMAPS">
            </div>
            <!-- Sort Order + Remove -->
            <div class="col-md-1">
              <label class="form-label form-label-sm mb-1">Order</label>
              <input type="number" class="form-control form-control-sm" name="tracts[<?= $ti ?>][sort_order]"
                     value="<?= (int)($tract['sort_order'] ?? 0) ?>" min="0" style="width:60px">
            </div>
            <div class="col-auto d-flex align-items-end pb-1">
              <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTract(this)" title="Remove this tract">✕</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <button type="button" class="btn btn-outline-secondary btn-sm mb-4" onclick="addTract()">+ Add Another Tract</button>

    <!-- Zoning -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Zoning</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <label class="form-label" for="current_zoning">Current Zoning</label>
        <input class="form-control" type="text" id="current_zoning" name="current_zoning"
               value="<?= $v('current_zoning') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="proposed_zoning">Proposed Zoning</label>
        <input class="form-control" type="text" id="proposed_zoning" name="proposed_zoning"
               value="<?= $v('proposed_zoning') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="comp_plan_designation">Comp Plan Designation</label>
        <input class="form-control" type="text" id="comp_plan_designation" name="comp_plan_designation"
               value="<?= $v('comp_plan_designation') ?>">
      </div>
    </div>

    <!-- Dates -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Dates</h6>
    <div class="row g-3 mb-2">
      <div class="col-md-3">
        <label class="form-label" for="anticipated_start_date">Anticipated Start</label>
        <input class="form-control" type="date" id="anticipated_start_date" name="anticipated_start_date"
               value="<?= $v('anticipated_start_date') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="anticipated_end_date">Anticipated End</label>
        <input class="form-control" type="date" id="anticipated_end_date" name="anticipated_end_date"
               value="<?= $v('anticipated_end_date') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="agreement_termination_date">Termination Date</label>
        <input class="form-control" type="date" id="agreement_termination_date" name="agreement_termination_date"
               value="<?= $v('agreement_termination_date') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="planning_board_date">Planning Board Date</label>
        <input class="form-control" type="date" id="planning_board_date" name="planning_board_date"
               value="<?= $v('planning_board_date') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="town_council_hearing_date">Town Council Hearing Date</label>
        <input class="form-control" type="date" id="town_council_hearing_date" name="town_council_hearing_date"
               value="<?= $v('town_council_hearing_date') ?>">
      </div>
    </div>

  </div><!-- /card-body -->

  <div class="card-footer bg-white d-flex gap-2">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Agreement' ?></button>
    <a href="/index.php?page=development_agreements" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>

<script>
// ── Tract row template (used by addTract()) ───────────────────────────────
function nextTractIndex() {
  return document.querySelectorAll('.tract-row').length;
}

function buildTractRowHtml(idx) {
  return `
    <div class="tract-row card mb-2 border" data-index="${idx}">
      <div class="card-body p-3">
        <input type="hidden" name="tracts[${idx}][tract_id]" value="">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">PIN</label>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control tract-pin" name="tracts[${idx}][property_pin]"
                     maxlength="15" placeholder="digits only">
              <button type="button" class="btn btn-outline-primary tract-lookup-btn"
                      onclick="lookupTractPin(this)" title="Lookup from Wake County IMAPS">Lookup</button>
            </div>
            <div class="tract-status form-text"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Real Estate ID</label>
            <input type="text" class="form-control form-control-sm tract-reid" name="tracts[${idx}][property_realestateid]" maxlength="50">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Property Address</label>
            <input type="text" class="form-control form-control-sm tract-address" name="tracts[${idx}][property_address]">
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-1">Acres</label>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm tract-acreage" name="tracts[${idx}][property_acerage]">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Property Owner</label>
            <input type="text" class="form-control form-control-sm tract-owner"
                   name="tracts[${idx}][owner_name]"
                   placeholder="Auto-filled from IMAPS">
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-1">Order</label>
            <input type="number" class="form-control form-control-sm" name="tracts[${idx}][sort_order]" value="0" min="0" style="width:60px">
          </div>
          <div class="col-auto d-flex align-items-end pb-1">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeTract(this)" title="Remove">✕</button>
          </div>
        </div>
      </div>
    </div>`;
}

function addTract() {
  const container = document.getElementById('tracts-container');
  const idx = nextTractIndex();
  container.insertAdjacentHTML('beforeend', buildTractRowHtml(idx));
}

function removeTract(btn) {
  const row = btn.closest('.tract-row');
  // Only remove if there's more than one row
  if (document.querySelectorAll('.tract-row').length <= 1) {
    // Just clear the fields instead of removing
    row.querySelectorAll('input[type=text], input[type=number]').forEach(i => i.value = '');
    row.querySelector('select').value = '';
    return;
  }
  row.remove();
  reindexTracts();
}

function reindexTracts() {
  document.querySelectorAll('.tract-row').forEach((row, idx) => {
    row.dataset.index = idx;
    row.querySelectorAll('[name]').forEach(el => {
      el.name = el.name.replace(/tracts\[\d+\]/, `tracts[${idx}]`);
    });
  });
}

// ── IMAPS PIN Lookup ─────────────────────────────────────────────────────────
async function lookupTractPin(btn) {
  const row     = btn.closest('.tract-row');
  const pinEl   = row.querySelector('.tract-pin');
  const statusEl = row.querySelector('.tract-status');
  const pin     = pinEl.value.trim();

  if (!pin || !/^\d{1,15}$/.test(pin)) {
    statusEl.className = 'tract-status form-text text-danger';
    statusEl.textContent = 'Enter a numeric PIN first.';
    return;
  }

  btn.disabled    = true;
  btn.textContent = '…';
  statusEl.className   = 'tract-status form-text';
  statusEl.textContent = 'Looking up…';

  try {
    const res  = await fetch('/devagr_imaps_lookup.php?pin=' + encodeURIComponent(pin));
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
    statusEl.textContent = '✓ Filled from Wake County GIS.';
  } catch {
    statusEl.className   = 'tract-status form-text text-danger';
    statusEl.textContent = 'Request failed — check internet connection.';
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Lookup';
  }
}
</script>
