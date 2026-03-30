<?php
declare(strict_types=1);

$isEdit = ($mode ?? 'create') === 'edit';
$contractId = (int)($contract['contract_id'] ?? 0);

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$action = $isEdit
    ? '/index.php?page=contracts_update&contract_id=' . $contractId
    : '/index.php?page=contracts_store';

$flashErrors = $flashErrors ?? [];
$departments = $departments ?? [];
$companies = $companies ?? [];
$types = $types ?? [];
$ownerPeople = $ownerPeople ?? [];
$counterpartyPeople = $counterpartyPeople ?? [];
?>

<?php if (!empty($flashErrors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($flashErrors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">

      <div class="col-md-4">
        <label class="form-label">Contract #</label>
        <input class="form-control" name="contract_number" value="<?= h($contract['contract_number'] ?? '') ?>">
      </div>

      <div class="col-md-8">
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="<?= h($contract['name'] ?? '') ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach (['draft','in_review','signed','expired','terminated'] as $s): ?>
            <option value="<?= h($s) ?>" <?= (($contract['status'] ?? 'draft') === $s) ? 'selected' : '' ?>>
              <?= h(ucwords(str_replace('_', ' ', $s))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Department</label>
        <select class="form-select" name="department_id">
          <option value="">(none)</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['department_id'] ?>"
              <?= ((string)($contract['department_id'] ?? '') === (string)$d['department_id']) ? 'selected' : '' ?>>
              <?= h($d['department_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Contract Type</label>
        <select class="form-select" name="contract_type_id">
          <option value="">(none)</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= (int)$t['contract_type_id'] ?>"
              <?= ((string)($contract['contract_type_id'] ?? '') === (string)$t['contract_type_id']) ? 'selected' : '' ?>>
              <?= h($t['contract_type']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Owner Company</label>
        <select class="form-select" name="owner_company_id" required>
          <option value="">Select…</option>
          <?php foreach ($companies as $co): ?>
            <option value="<?= (int)$co['company_id'] ?>"
              <?= ((string)($contract['owner_company_id'] ?? '') === (string)$co['company_id']) ? 'selected' : '' ?>>
              <?= h($co['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Counterparty Company</label>
        <select class="form-select" name="counterparty_company_id" required>
          <option value="">Select…</option>
          <?php foreach ($companies as $co): ?>
            <option value="<?= (int)$co['company_id'] ?>"
              <?= ((string)($contract['counterparty_company_id'] ?? '') === (string)$co['company_id']) ? 'selected' : '' ?>>
              <?= h($co['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Owner Primary Contact</label>
        <select class="form-select" name="owner_primary_contact_id">
          <option value="">(none)</option>
          <?php foreach ($ownerPeople as $p): ?>
            <?php
              $nm = trim((string)($p['full_name'] ?? ''));
              if ($nm === '') {
                  $nm = trim((string)($p['first_name'] ?? '') . ' ' . (string)($p['last_name'] ?? ''));
              }
              $label = $nm . (!empty($p['email']) ? ' — ' . $p['email'] : '');
            ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($contract['owner_primary_contact_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Counterparty Primary Contact</label>
        <select class="form-select" name="counterparty_primary_contact_id">
          <option value="">(none)</option>
          <?php foreach ($counterpartyPeople as $p): ?>
            <?php
              $nm = trim((string)($p['full_name'] ?? ''));
              if ($nm === '') {
                  $nm = trim((string)($p['first_name'] ?? '') . ' ' . (string)($p['last_name'] ?? ''));
              }
              $label = $nm . (!empty($p['email']) ? ' — ' . $p['email'] : '');
            ?>
            <option value="<?= (int)$p['person_id'] ?>"
              <?= ((string)($contract['counterparty_primary_contact_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Start Date</label>
        <input class="form-control" type="date" name="start_date" value="<?= h($contract['start_date'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">End Date</label>
        <input class="form-control" type="date" name="end_date" value="<?= h($contract['end_date'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Governing Law</label>
        <input class="form-control" name="governing_law" value="<?= h($contract['governing_law'] ?? 'North Carolina') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Auto Renew</label>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew" value="1"
            <?= ((int)($contract['auto_renew'] ?? 0) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="auto_renew">Yes</label>
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Renewal Term (months)</label>
        <input class="form-control" type="number" name="renewal_term_months" value="<?= h($contract['renewal_term_months'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Total Value</label>
        <input class="form-control" name="total_contract_value" value="<?= h($contract['total_contract_value'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Currency</label>
        <input class="form-control" name="currency" value="<?= h($contract['currency'] ?? 'USD') ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="3"><?= h($contract['description'] ?? '') ?></textarea>
      </div>

      <?php if (array_key_exists('contract_body_html', $contract ?? [])): ?>
        <div class="col-12">
          <label class="form-label">Contract Body HTML</label>
          <textarea class="form-control" name="contract_body_html" rows="8"><?= h($contract['contract_body_html'] ?? '') ?></textarea>
        </div>
      <?php endif; ?>

      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
          <?= $isEdit ? 'Save Contract' : 'Create Contract' ?>
        </button>

        <a class="btn btn-outline-secondary" href="/index.php?page=contracts">Cancel</a>

        <?php if ($isEdit && $contractId > 0): ?>
          <a class="btn btn-outline-secondary" href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>">
            View Details
          </a>
          <a class="btn btn-outline-primary" href="/index.php?page=contracts_generate_print&contract_id=<?= $contractId ?>" target="_blank">
            Generate and Print Contract
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</form>