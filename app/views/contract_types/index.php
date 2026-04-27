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
    <h1 class="h3 mb-0">Contract Types</h1>
    <div class="d-flex gap-2">
      <a href="/index.php?page=merge_field_reference" class="btn btn-outline-info btn-sm">Merge Field Reference</a>
      <a href="/index.php?page=contract_types_create" class="btn btn-primary btn-sm">+ New Contract Type</a>
      <a href="/index.php?page=contracts" class="btn btn-outline-secondary btn-sm">Back to Contracts</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_messages'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php foreach ($_SESSION['flash_messages'] as $msg): ?>
        <div><?= h($msg) ?></div>
      <?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_messages']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_errors'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        <?php foreach ($_SESSION['flash_errors'] as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_errors']); ?>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>Contract Type</th>
          <th>Description</th>
          <th>Formal Bidding Required</th>
          <th>HTML Template</th>
          <th>DOCX Template</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contractTypes as $ct): ?>
        <tr>
          <td>
            <strong><?= h($ct['contract_type']) ?></strong>
          </td>
          <td>
            <small><?= h(substr($ct['description'] ?? '', 0, 50)) ?></small>
          </td>
          <td>
            <?= ($ct['formal_bidding_required'] ?? 0) ? '✓ Yes' : 'No' ?>
          </td>
          <td>
            <?php if (!empty($ct['template_file_html'])): ?>
              <span class="badge bg-success">✓ Uploaded</span>
            <?php else: ?>
              <span class="badge bg-secondary">Not uploaded</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($ct['template_file_docx'])): ?>
              <span class="badge bg-success">✓ Uploaded</span>
            <?php else: ?>
              <span class="badge bg-secondary">Not uploaded</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a href="/index.php?page=contract_types_edit&contract_type_id=<?= (int)$ct['contract_type_id'] ?>"
               class="btn btn-primary btn-sm">Edit</a>
            <form method="post"
                  action="/index.php?page=contract_types_delete&contract_type_id=<?= (int)$ct['contract_type_id'] ?>"
                  class="d-inline"
                  onsubmit="return confirm('Delete contract type &quot;<?= h(addslashes($ct['contract_type'])) ?>&quot;? This cannot be undone.')">
              <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (empty($contractTypes)): ?>
  <div class="alert alert-info">
    No contract types found. <a href="/index.php?page=contract_types_create">Create one</a>.
  </div>
  <?php endif; ?>
</div>
