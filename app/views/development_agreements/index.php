<?php
declare(strict_types=1);
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto">Development Agreements</h1>
    <a href="/index.php?page=development_agreements_create" class="btn btn-primary">+ New Agreement</a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success"><?= h($_SESSION['flash_success']) ?></div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (empty($agreements)): ?>
  <div class="card shadow-sm">
    <div class="card-body text-muted">No development agreements found.</div>
  </div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Project Name</th>
          <th>Property Address</th>
          <th>Applicant</th>
          <th>Property Owner</th>
          <th>Anticipated Start</th>
          <th>Anticipated End</th>
          <th>Termination Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($agreements as $agr): ?>
        <tr>
          <td><?= (int)$agr['dev_agreement_id'] ?></td>
          <td>
            <a href="/index.php?page=development_agreements_show&dev_agreement_id=<?= (int)$agr['dev_agreement_id'] ?>">
              <?= h($agr['project_name']) ?>
            </a>
          </td>
          <td><?= h($agr['property_address'] ?? '—') ?></td>
          <td><?= h($agr['applicant_name'] ?? '—') ?></td>
          <td><?= h($agr['property_owner_name'] ?? '—') ?></td>
          <td><?= $agr['anticipated_start_date'] ? date('m/d/Y', strtotime($agr['anticipated_start_date'])) : '—' ?></td>
          <td><?= $agr['anticipated_end_date']   ? date('m/d/Y', strtotime($agr['anticipated_end_date']))   : '—' ?></td>
          <td><?= $agr['agreement_termination_date'] ? date('m/d/Y', strtotime($agr['agreement_termination_date'])) : '—' ?></td>
          <td class="text-end">
            <a href="/index.php?page=development_agreements_show&dev_agreement_id=<?= (int)$agr['dev_agreement_id'] ?>"
               class="btn btn-sm btn-outline-secondary">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
