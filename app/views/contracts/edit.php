<?php
declare(strict_types=1);

//require APP_ROOT . '/app/views/layouts/header.php';

$isEdit = ($mode ?? 'create') === 'edit';
$contractId = $contract['contract_id'] ?? null;

$action = $isEdit
    ? '/index.php?page=contracts_update&contract_id=' . urlencode((string)$contractId)
    : '/index.php?page=contracts_store';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto"><?= $isEdit ? 'Edit Contract' : 'Create Contract' ?></h1>
</div>

<?php if (!empty($flashErrors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($flashErrors as $err): ?>
        <li><?= h($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= h($action) ?>" class="card shadow-sm">

    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label" for="contract_name">Contract Name</label>
                <input class="form-control" type="text" id="contract_name" name="name" required
                       value="<?= h($contract['name'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="contract_number">Contract Number (Auto-Generated)</label>
                <input class="form-control bg-light text-muted" type="text" id="contract_number" name="contract_number"
                       value="<?= h($contract['contract_number'] ?? '') ?>">
            </div>


            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <?php
                  $descVal = $contract['description'] ?? '';
                  if ($descVal === '' && empty($isEdit)) {
                      $cName = trim((string)($contract['name'] ?? ''));
                      $descVal = ($cName !== '' ? $cName : '[Contract Name]') . ' as further described under the terms and conditions set forth in Exhibit A';
                  }
                ?>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="[Contract Name] as further described under the terms and conditions set forth in Exhibit A"><?= h($descVal) ?></textarea>
            </div>



            <div class="col-md-4">
                <label class="form-label">Contract Type</label>
                <select class="form-select" name="contract_type_id">
                    <option value="">(none)</option>
                    <?php foreach (($types ?? []) as $t): ?>
                        <option value="<?= (int)$t['contract_type_id'] ?>"
                            <?= ((string)($contract['contract_type_id'] ?? '') === (string)$t['contract_type_id']) ? 'selected' : '' ?>>
                            <?= h($t['contract_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Payment Terms</label>
                <select class="form-select" name="payment_terms_id">
                    <option value="">(none)</option>
                    <?php foreach (($paymentTerms ?? []) as $pt): ?>
                        <option value="<?= (int)$pt['payment_terms_id'] ?>"
                            <?= ((string)($contract['payment_terms_id'] ?? '') === (string)$pt['payment_terms_id']) ? 'selected' : '' ?>>
                            <?= h($pt['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Department</label>
                <select class="form-select" name="department_id">
                    <option value="">(none)</option>
                    <?php foreach (($departments ?? []) as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= ((string)($contract['department_id'] ?? '') === (string)$d['department_id']) ? 'selected' : '' ?>>
                            <?= h($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <?php $currentStatusId = $contract['contract_status_id'] ?? ''; ?>
                <select class="form-select" name="contract_status_id" required>
                    <option value="">Select…</option>
                    <?php foreach (($contractStatuses ?? []) as $status): ?>
                        <option value="<?= (int)$status['contract_status_id'] ?>" <?= ((string)$currentStatusId === (string)$status['contract_status_id']) ? 'selected' : '' ?>>
                            <?= h($status['contract_status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="col-md-4">
                <label class="form-label">Owner Company</label>
                <select class="form-select" name="owner_company_id" required>
                    <option value="">Select…</option>
                    <?php foreach (($companies ?? []) as $co): ?>
                        <option value="<?= (int)$co['company_id'] ?>"
                            <?= ((string)($contract['owner_company_id'] ?? '') === (string)$co['company_id']) ? 'selected' : '' ?>>
                            <?= h($co['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Responsible Person</label>
                <select class="form-select" name="owner_primary_contact_id">
                    <option value="">(none)</option>
                    <?php foreach (($ownerPeople ?? []) as $p): ?>
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

            <div class="col-12">
              <div class="border rounded p-3">
                <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label">Counterparty Company</label>
                <select class="form-select" name="counterparty_company_id" required>
                    <option value="">Select…</option>
                    <?php foreach (($companies ?? []) as $co): ?>
                        <option value="<?= (int)$co['company_id'] ?>"
                            <?= ((string)($contract['counterparty_company_id'] ?? '') === (string)$co['company_id']) ? 'selected' : '' ?>>
                            <?= h($co['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Counterparty Primary Contact</label>
                <select class="form-select" name="counterparty_primary_contact_id">
                    <option value="">(none)</option>
                    <?php foreach (($counterpartyPeople ?? []) as $p): ?>
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

                </div>
              </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="start_date">Start Date</label>
                <input class="form-control" type="date" id="start_date" name="start_date"
                       value="<?= h($contract['start_date'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="end_date">End Date</label>
                <input class="form-control" type="date" id="end_date" name="end_date"
                       value="<?= h($contract['end_date'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="governing_law">Governing Law</label>
                <input class="form-control" type="text" id="governing_law" name="governing_law"
                       value="<?= h($contract['governing_law'] ?? 'North Carolina') ?>">
            </div>


            <div class="col-md-3">
                <label class="form-label" for="total_contract_value">Total Contract Value</label>
                <input class="form-control" type="text" id="total_contract_value" name="total_contract_value"
                       value="<?= h($contract['total_contract_value'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="po_number">PO Number</label>
                <input class="form-control" type="text" id="po_number" name="po_number"
                       maxlength="20"
                       value="<?= h($contract['po_number'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="po_amount">PO Amount</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input class="form-control" type="text" id="po_amount" name="po_amount"
                           placeholder="0.00"
                           value="<?= h($contract['po_amount'] ?? '') ?>">
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="date_approved_by_procurement">Date Approved by Procurement</label>
                <input class="form-control" type="date" id="date_approved_by_procurement" name="date_approved_by_procurement"
                       value="<?= h($contract['date_approved_by_procurement'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="date_approved_by_manager">Date Approved by Manager</label>
                <input class="form-control" type="date" id="date_approved_by_manager" name="date_approved_by_manager"
                       value="<?= h($contract['date_approved_by_manager'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="date_approved_by_council">Date Approved by Council</label>
                <input class="form-control" type="date" id="date_approved_by_council" name="date_approved_by_council"
                       value="<?= h($contract['date_approved_by_council'] ?? '') ?>">
            </div>

            <div class="col-12">
              <hr class="my-2">
              <h6 class="text-muted mb-3">
                <?php if (!empty($complianceInfoLink)): ?>
                  <a href="<?= h($complianceInfoLink) ?>" target="_blank" rel="noopener noreferrer">Procurement &amp; Public Bidding Compliance</a>
                <?php else: ?>
                  Procurement &amp; Public Bidding Compliance
                <?php endif; ?>
              </h6>
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label" for="procurement_method">Procurement Method</label>
                  <select class="form-select" id="procurement_method" name="procurement_method">
                    <option value="">— Select —</option>
                    <?php
                      $procMethods = [
                        'Competitive Bid (IFB)',
                        'Request for Proposals (RFP)',
                        'Sole Source / Single Source',
                        'Emergency Purchase',
                        'Cooperative / Piggyback Purchase',
                        'Small / Informal Purchase (below threshold)',
                        'Professional Services (QBS)',
                        'Service (non QBS)',
                        'Not Required',
                      ];
                      $currentMethod = $contract['procurement_method'] ?? '';
                      foreach ($procMethods as $m):
                    ?>
                      <option value="<?= h($m) ?>" <?= $currentMethod === $m ? 'selected' : '' ?>><?= h($m) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label" for="bid_rfp_number">Bid / RFP Number</label>
                  <input class="form-control" type="text" id="bid_rfp_number" name="bid_rfp_number" maxlength="100"
                         placeholder="e.g. IFB-2025-012"
                         value="<?= h($contract['bid_rfp_number'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label" for="bid_documents_path">Bid Documents Path</label>
                  <input class="form-control" type="text" id="bid_documents_path" name="bid_documents_path" maxlength="500"
                         placeholder="e.g. \\server\procurement\2025\IFB-2025-012"
                         value="<?= h($contract['bid_documents_path'] ?? '') ?>">
                </div>

                <div class="col-12">
                  <label class="form-label" for="procurement_notes">Explain Compliance with Public Bidding / Procurement Laws</label>
                  <textarea class="form-control" id="procurement_notes" name="procurement_notes" rows="5"
                            placeholder="Describe how this contract complies with public bidding and procurement requirements, any exemptions that apply, or why competitive bidding was not required."><?= h($contract['procurement_notes'] ?? '') ?></textarea>
                </div>

              </div>
            </div>

            <div class="col-12 d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Update Contract' : 'Create Contract' ?>
                </button>

                <a href="/index.php?page=contracts" class="btn btn-outline-secondary">Cancel</a>

                <?php if ($isEdit && $contractId): ?>
                    <a href="/index.php?page=contracts_show&contract_id=<?= urlencode((string)$contractId) ?>"
                       class="btn btn-outline-secondary">
                        Back to Details
                    </a>
                    <a href="/index.php?page=contracts_generate_html&contract_id=<?= urlencode((string)$contractId) ?>"
                       target="_blank"
                       class="btn btn-outline-success">
                        Generate HTML
                    </a>
                    <a href="/index.php?page=contracts_generate_word&contract_id=<?= urlencode((string)$contractId) ?>"
                       class="btn btn-outline-info">
                        Generate Word Doc
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</form>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>