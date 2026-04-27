<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1"><?= h($contractType['contract_type']) ?></h1>
      <p class="text-muted mb-0">Manage templates for this contract type</p>
    </div>
    <a href="/index.php?page=contract_types" class="btn btn-outline-secondary btn-sm">Back to Types</a>
    <a href="/index.php?page=merge_field_reference" class="btn btn-outline-info btn-sm">Merge Field Reference</a>
  </div>

  <?php if (!empty($flashMessages)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php foreach ($flashMessages as $msg): ?>
        <div><?= h($msg) ?></div>
      <?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($flashErrors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        <?php foreach ($flashErrors as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form method="post" action="/index.php?page=contract_types_update&contract_type_id=<?= (int)$contractType['contract_type_id'] ?>" enctype="multipart/form-data" class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">

        <div class="col-12">
          <label class="form-label fw-semibold" for="contract_type">Contract Type Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="contract_type" name="contract_type"
                 value="<?= h($contractType['contract_type']) ?>" maxlength="100" required>
        </div>

        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3"><?= h($contractType['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="formal_bidding_required" name="formal_bidding_required"
              <?= ($contractType['formal_bidding_required'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="formal_bidding_required">
              Formal Bidding Required
            </label>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-primary">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">HTML Template</h5>
            </div>
            <div class="card-body">
              <?php if (!empty($contractType['template_file_html'])): ?>
                <div class="alert alert-success alert-sm mb-3">
                  <small>
                    <strong>Current Template:</strong><br>
                    <?= h(basename($contractType['template_file_html'])) ?>
                  </small>
                </div>
              <?php else: ?>
                <div class="alert alert-warning alert-sm mb-3">
                  <small>No HTML template uploaded yet</small>
                </div>
              <?php endif; ?>
              
              <label class="form-label">Upload HTML Template</label>
              <input type="file" class="form-control" name="template_html" accept=".html,.htm,text/html"
                     help="Upload an HTML file (.html or .htm). Use {{field_name}} for template variables.">
              <small class="form-text text-muted d-block mt-2">
                Use template variables like {{contract_number}}, {{contract_name}}, {{owner_company}}, etc.
              </small>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-success">
            <div class="card-header bg-success text-white">
              <h5 class="mb-0">DOCX Template</h5>
            </div>
            <div class="card-body">
              <?php if (!empty($contractType['template_file_docx'])): ?>
                <div class="alert alert-success alert-sm mb-3">
                  <small>
                    <strong>Current Template:</strong><br>
                    <?= h(basename($contractType['template_file_docx'])) ?>
                  </small>
                </div>
              <?php else: ?>
                <div class="alert alert-warning alert-sm mb-3">
                  <small>No DOCX template uploaded yet</small>
                </div>
              <?php endif; ?>
              
              <label class="form-label">Upload DOCX Template</label>
              <input type="file" class="form-control" name="template_docx" accept=".docx"
                     help="Upload a Microsoft Word file (.docx). Use {{field_name}} for template variables.">
              <small class="form-text text-muted d-block mt-2">
                Use template variables like {{contract_number}}, {{contract_name}}, {{owner_company}}, etc.
              </small>
            </div>
          </div>
        </div>

      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="/index.php?page=contract_types" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </form>

  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">Available Template Variables</h5>
    </div>
    <div class="card-body">
      <p>Use these variables in your templates (surrounded by double braces {{ }}):</p>
      <div class="row g-3">
        <div class="col-md-6">
          <strong>Contract Information:</strong>
          <ul class="small">
            <li>{{contract_number}}</li>
            <li>{{contract_name}}</li>
            <li>{{status}}</li>
            <li>{{contract_type}}</li>
            <li>{{department_name}}</li>
            <li>{{start_date}}</li>
            <li>{{end_date}}</li>
            <li>{{governing_law}}</li>
          </ul>
        </div>
        <div class="col-md-6">
          <strong>Party Information:</strong>
          <ul class="small">
            <li>{{owner_company}}</li>
            <li>{{owner_address}}</li>
            <li>{{owner_email}}</li>
            <li>{{counterparty_company}}</li>
            <li>{{counterparty_address}}</li>
            <li>{{counterparty_email}}</li>
            <li>{{counterparty_contact_name}}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
