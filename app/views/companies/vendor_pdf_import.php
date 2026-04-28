<?php
declare(strict_types=1);
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$errors  = $errors  ?? [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<div class="container py-4" style="max-width: 680px;">

  <div class="mb-3">
    <a href="/index.php?page=companies" class="btn btn-outline-secondary btn-sm">&larr; Back to Companies</a>
  </div>

  <h1 class="h4 mb-1">Import Vendor from PDF</h1>
  <p class="text-muted mb-4">
    Upload a completed <strong>Vendor/Supplier Information Form</strong> PDF and the system will
    extract the company details and pre-fill the company creation form for you to review and save.
  </p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post"
            action="/index.php?page=companies_vendor_pdf_import_process"
            enctype="multipart/form-data">

        <div class="mb-4">
          <label for="vendor_pdf" class="form-label fw-semibold">
            Vendor/Supplier Information Form (PDF) <span class="text-danger">*</span>
          </label>
          <input type="file"
                 class="form-control"
                 id="vendor_pdf"
                 name="vendor_pdf"
                 accept=".pdf,application/pdf"
                 required>
          <div class="form-text">Only PDF files are accepted. The form must contain selectable text (not a scanned image).</div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Extract &amp; Pre-fill Form</button>
          <a href="/index.php?page=companies" class="btn btn-outline-secondary">Cancel</a>
        </div>

      </form>
    </div>
  </div>

  <div class="card border-secondary mt-4">
    <div class="card-header bg-light fw-semibold small py-2">Fields extracted from the PDF</div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered mb-0 small">
        <thead class="table-light">
          <tr><th>PDF Field</th><th>Saved to</th></tr>
        </thead>
        <tbody>
          <tr><td>Company Name</td><td>Company Name</td></tr>
          <tr><td>Type of Business Ownership</td><td>Business Type</td></tr>
          <tr><td>Street Address</td><td>Address Line 1</td></tr>
          <tr><td>Address Line 2</td><td>Address Line 2</td></tr>
          <tr><td>City</td><td>City</td></tr>
          <tr><td>State/Province/Region</td><td>State / Region</td></tr>
          <tr><td>Postal/Zip Code</td><td>Postal Code</td></tr>
          <tr><td>Country</td><td>Country</td></tr>
          <tr><td>Phone Number</td><td>Phone</td></tr>
          <tr><td>Email</td><td>Email</td></tr>
          <tr><td>Contact Name</td><td>Contact Name &amp; Signer 1 Name</td></tr>
          <tr><td>Contact Title</td><td>Signer 1 Title</td></tr>
          <tr><td>Contact Email</td><td>Signer 1 Email</td></tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent text-muted small py-2">
      All extracted values are pre-filled for your review — nothing is saved until you click <strong>Save Company</strong> on the next page.
    </div>
  </div>

</div>
