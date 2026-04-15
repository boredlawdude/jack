<?php
declare(strict_types=1);
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
$d = fn($field) => h($agreement[$field] ?? '');
$dt = fn($field) => (!empty($agreement[$field])) ? date('m/d/Y', strtotime($agreement[$field])) : '—';
?>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <div class="text-muted small mb-1">Development Agreement</div>
      <h1 class="h3 mb-0"><?= $d('project_name') ?></h1>
    </div>
    <div class="d-flex gap-2">
      <a href="/index.php?page=development_agreements" class="btn btn-outline-secondary btn-sm">Back to List</a>
      <a href="/index.php?page=development_agreements_edit&dev_agreement_id=<?= (int)$agreement['dev_agreement_id'] ?>"
         class="btn btn-primary btn-sm">Edit</a>
      <?php if (function_exists('is_system_admin') && is_system_admin()): ?>
        <form method="post" action="/index.php?page=development_agreements_delete" class="d-inline"
              onsubmit="return confirm('Permanently delete this development agreement?')">
          <input type="hidden" name="dev_agreement_id" value="<?= (int)$agreement['dev_agreement_id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= h($flashSuccess) ?></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- Left column -->
    <div class="col-lg-8">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Project Information</h2></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <div class="small text-muted">Project Name</div>
              <div><?= $d('project_name') ?></div>
            </div>
            <?php if (!empty($agreement['project_description'])): ?>
            <div class="col-12">
              <div class="small text-muted">Project Description</div>
              <div style="white-space:pre-wrap"><?= $d('project_description') ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($agreement['proposed_improvements'])): ?>
            <div class="col-12">
              <div class="small text-muted">Proposed Improvements</div>
              <div style="white-space:pre-wrap"><?= $d('proposed_improvements') ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Property</h2></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="small text-muted">Property Address</div>
              <div><?= $d('property_address') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">PIN</div>
              <div><?= $d('property_pin') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Real Estate ID</div>
              <div><?= $d('property_realestateid') ?: '—' ?></div>
            </div>
            <div class="col-md-2">
              <div class="small text-muted">Acreage</div>
              <div><?= !empty($agreement['property_acerage']) ? number_format((float)$agreement['property_acerage'], 4) : '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Current Zoning</div>
              <div><?= $d('current_zoning') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Proposed Zoning</div>
              <div><?= $d('proposed_zoning') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Comp Plan Designation</div>
              <div><?= $d('comp_plan_designation') ?: '—' ?></div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Right column -->
    <div class="col-lg-4">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Parties</h2></div>
        <div class="card-body">
          <div class="mb-2">
            <div class="small text-muted">Applicant</div>
            <div><?= $d('applicant_name') ?: '—' ?></div>
          </div>
          <div class="mb-2">
            <div class="small text-muted">Property Owner</div>
            <div><?= $d('property_owner_name') ?: '—' ?></div>
          </div>
          <div>
            <div class="small text-muted">Attorney</div>
            <div><?= $d('attorney_name') ?: '—' ?></div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Dates</h2></div>
        <div class="card-body">
          <div class="mb-2">
            <div class="small text-muted">Anticipated Start</div>
            <div><?= $dt('anticipated_start_date') ?></div>
          </div>
          <div class="mb-2">
            <div class="small text-muted">Anticipated End</div>
            <div><?= $dt('anticipated_end_date') ?></div>
          </div>
          <div>
            <div class="small text-muted">Agreement Termination</div>
            <div><?= $dt('agreement_termination_date') ?></div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Record</h2></div>
        <div class="card-body">
          <div class="mb-2">
            <div class="small text-muted">Agreement ID</div>
            <div><?= (int)$agreement['dev_agreement_id'] ?></div>
          </div>
          <div class="mb-2">
            <div class="small text-muted">Created</div>
            <div><?= !empty($agreement['created_at']) ? date('m/d/Y H:i', strtotime($agreement['created_at'])) : '—' ?></div>
          </div>
          <div>
            <div class="small text-muted">Last Updated</div>
            <div><?= !empty($agreement['updated_at']) ? date('m/d/Y H:i', strtotime($agreement['updated_at'])) : '—' ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
