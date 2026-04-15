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

    <!-- Property -->
    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-1">Property</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label" for="property_address">Property Address</label>
        <input class="form-control" type="text" id="property_address" name="property_address"
               value="<?= $v('property_address') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="property_pin">Property PIN</label>
        <input class="form-control" type="text" id="property_pin" name="property_pin"
               value="<?= $v('property_pin') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="property_realestateid">Real Estate ID</label>
        <input class="form-control" type="text" id="property_realestateid" name="property_realestateid"
               value="<?= $v('property_realestateid') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="property_acerage">Acreage</label>
        <input class="form-control" type="number" step="0.0001" min="0" id="property_acerage" name="property_acerage"
               value="<?= $v('property_acerage') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="current_zoning">Current Zoning</label>
        <input class="form-control" type="text" id="current_zoning" name="current_zoning"
               value="<?= $v('current_zoning') ?>">
      </div>
      <div class="col-md-3">
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
    </div>

  </div><!-- /card-body -->

  <div class="card-footer bg-white d-flex gap-2">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Agreement' ?></button>
    <a href="/index.php?page=development_agreements" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
