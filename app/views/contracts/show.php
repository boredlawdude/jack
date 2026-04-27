<?php
$contractTitle  = trim((string)($contract['name'] ?? 'Contract'));
$contractNumber = trim((string)($contract['contract_number'] ?? ''));
$status         = trim((string)($contract['status_name'] ?? ''));
$isDevAgreement = isset($devAgreement) && is_array($devAgreement);
?>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <div class="text-muted small mb-1"><?= $isDevAgreement ? 'Development Agreement' : 'Contract Detail' ?></div>
      <h1 class="h3 mb-1"><?= h($contractTitle) ?></h1>
      <div class="text-muted">
        <?php if ($contractNumber !== ''): ?>
          <span class="me-3"><strong>No.</strong> <?= h($contractNumber) ?></span>
        <?php endif; ?>
        <?php if ($isDevAgreement): ?>
          <span class="badge text-bg-info me-1">Development Agreement</span>
        <?php endif; ?>
        <?php if ($status !== ''): ?>
          <?php if (!function_exists('status_badge')) {
              function status_badge(string $status): string {
                  return match (strtolower($status)) {
                      'draft' => 'secondary',
                      'negotiate' => 'info',
                      'procurement review' => 'info',
                      'legal review' => 'warning',
                      'dept head review' => 'primary',
                      'manager review' => 'primary',
                      'town council' => 'info',
                      'out for signature' => 'warning',
                      'executed' => 'success',
                      default => 'light',
                  };
              }
          } ?>
          <span class="badge text-bg-<?= status_badge($status) ?>"><?= h($status) ?></span>
          <?php if (!empty($contract['status_comment'])): ?>
            <span class="text-muted small ms-1"><?= h($contract['status_comment']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
      <a href="/index.php?page=<?= $isDevAgreement ? 'development_agreements' : 'contracts' ?>" class="btn btn-outline-secondary btn-sm">Back</a>
      <?php if ($isDevAgreement && !empty($devAgreement['dev_agreement_id'])): ?>
        <a href="/index.php?page=development_agreements_edit&dev_agreement_id=<?= (int)$devAgreement['dev_agreement_id'] ?>" class="btn btn-warning btn-sm">Edit Dev Agreement Details</a>
      <?php endif; ?>
      <a href="/index.php?page=contracts_edit&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-primary btn-sm">Change Contract Info</a>
      <a href="/index.php?page=contracts_generate_html&contract_id=<?= (int)$contract['contract_id'] ?>" target="_blank" class="btn btn-outline-success btn-sm">Generate HTML</a>
      <a href="/index.php?page=contracts_generate_word&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-info btn-sm">Generate Word Doc</a>
    </div>
  </div>

  <div class="row g-4">

    <div class="col-lg-8">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Summary</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            
            <div class="col-md-6">
              <div class="small text-muted">Department</div>
              <div><?= h($contract['department_name'] ?? '') ?: '—' ?></div>
              <?php if (!empty($contract['department_code'])): ?>
                <div class="text-muted small">Code: <?= h($contract['department_code']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Counterparty Company</div>
              <div><?= h($contract['counterparty_company_name'] ?? '') ?: '—' ?></div>
            </div>
            

            <div class="col-md-6">
              <div class="small text-muted">Contract Type</div>
              <div>
                <?= h($contract['contract_type_name'] ?? '') ?: '—' ?>
                <?php if (!empty($contract['use_standard_contract'])): ?>
                  <span class="badge text-bg-info ms-1">Standard Contract</span>
                <?php endif; ?>
                <?php if (!empty($contract['minimum_insurance_coi'])): ?>
                  <span class="badge text-bg-success ms-1">COI ≥$5M</span>
                <?php endif; ?>
              </div>
            </div>
             <div class="col-md-6">
            <div class="small text-muted">Counterparty Primary Contact</div>
            <div><?= h($contract['counterparty_primary_contact_name'] ?? '') ?: '—'  ?></div>
            <?php if (!empty($contract['counterparty_primary_contact_email'])): ?>
              ( <a href="mailto:<?= h($contract['counterparty_primary_contact_email']) ?>">
                <?= h($contract['counterparty_primary_contact_email']) ?>
              </a> )
            <?php endif; ?>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Payment Type</div>
              <div><?= h($contract['payment_terms_name'] ?? '') ?: '—' ?></div>
            </div>

           
           
        

            <div class="col-md-6">
              <div class="small text-muted">Start Date</div>
              <div><?= h($contract['start_date'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">End Date</div>
              <div><?= h($contract['end_date'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Auto Renew</div>
              <div><?= !empty($contract['auto_renew']) ? 'Yes' : 'No' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Renewal Term (Months)</div>
              <div><?= h($contract['renewal_term_months'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Created At</div>
              <div><?= h($contract['created_at'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-muted">Updated At</div>
              <div><?= h($contract['updated_at'] ?? '') ?: '—' ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Financial / Terms</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="small text-muted">Total Contract Value</div>
              <div><?= !empty($contract['total_contract_value']) ? '$' . number_format((float)$contract['total_contract_value'], 2) : '—' ?></div>
            </div>

            <div class="col-md-4">
              <div class="small text-muted">PO Number</div>
              <div><?= h($contract['po_number'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-4">
              <div class="small text-muted">Account Number</div>
              <div><?= h($contract['account_number'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-4">
              <div class="small text-muted">PO Amount</div>
              <div><?= !empty($contract['po_amount']) ? '$' . number_format((float)$contract['po_amount'], 2) : '—' ?></div>
            </div>

            <div class="col-md-4">
              <div class="small text-muted">Governing Law</div>
              <div><?= h($contract['governing_law'] ?? '') ?: '—' ?></div>
            </div>

            <div class="col-md-12">
              <div class="small text-muted">Payment Type</div>
              <div><?= h($contract['payment_terms_name'] ?? '') ?: '—' ?></div>
            </div>

            <?php if (!empty($contract['documents_path'])): ?>
            <div class="col-12">
              <div class="small text-muted">Contract Documents Path</div>
              <div class="font-monospace small">
                <a href="<?= h($contract['documents_path']) ?>" target="_blank" rel="noopener noreferrer">
                  <?= h($contract['documents_path']) ?>
                </a>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php
      // Change Orders tile
      require_once APP_ROOT . '/app/models/ChangeOrder.php';
      $coModel      = new ChangeOrder(db());
      $changeOrders = $coModel->allForContract((int)$contract['contract_id']);
      ?>
      <div class="card shadow-sm mb-4" id="change-orders">
        <div class="card-header bg-white d-flex align-items-center">
          <h2 class="h6 mb-0 me-auto">Change Orders</h2>
          <a href="/index.php?page=change_orders_create&contract_id=<?= (int)$contract['contract_id'] ?>"
             class="btn btn-sm btn-primary">+ Add Change Order</a>
        </div>
        <?php if (empty($changeOrders)): ?>
          <div class="card-body text-muted">No change orders recorded yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>CO #</th>
                  <th>Amount</th>
                  <th>Approval Date</th>
                  <th>Justification</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($changeOrders as $co): ?>
                  <tr>
                    <td><?= h($co['change_order_number']) ?></td>
                    <td>
                      <?php if ($co['co_amount'] !== null && $co['co_amount'] !== ''): ?>
                        $<?= number_format((float)$co['co_amount'], 2) ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($co['approval_date'])): ?>
                        <?= date('m/d/Y', strtotime((string)$co['approval_date'])) ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-truncate" style="max-width:300px;"><?= h($co['co_justification'] ?? '') ?></td>
                    <td class="text-end text-nowrap">
                      <a href="/index.php?page=change_orders_edit&change_order_id=<?= (int)$co['change_order_id'] ?>"
                         class="btn btn-outline-secondary btn-sm">Edit</a>
                      <a href="/index.php?page=change_orders_generate_doc&change_order_id=<?= (int)$co['change_order_id'] ?>&format=docx"
                         class="btn btn-outline-primary btn-sm">&#128196; Generate Doc</a>
                      <form method="post" action="/index.php?page=change_orders_delete" class="d-inline"
                            onsubmit="return confirm('Delete change order <?= h($co['change_order_number']) ?>?');">
                        <input type="hidden" name="change_order_id" value="<?= (int)$co['change_order_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($isDevAgreement && $devAgreement): ?>

      <!-- Dev Agreement: Property Information -->
      <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
          <h2 class="h6 mb-0 text-info">Property Information</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <!-- Tracts table -->
            <div class="col-12">
              <?php if (!empty($devAgreementTracts)): ?>
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="text-center" style="width:2.5rem">#</th>
                    <th>PIN</th>
                    <th>Real Estate ID</th>
                    <th>Address</th>
                    <th>Acres</th>
                    <th>Property Owner</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($devAgreementTracts as $i => $tract): ?>
                  <tr>
                    <td class="text-center text-muted"><?= $i + 1 ?></td>
                    <td><?= h($tract['property_pin'] ?? '') ?: '—' ?></td>
                    <td><?= h($tract['property_realestateid'] ?? '') ?: '—' ?></td>
                    <td><?= h($tract['property_address'] ?? '') ?: '—' ?></td>
                    <td><?= h($tract['property_acerage'] ?? '') ?: '—' ?></td>
                    <td><?= h($tract['owner_name'] ?? '') ?: '—' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php else: ?>
              <span class="text-muted fst-italic">No tracts added yet.</span>
              <?php endif; ?>
            </div>
            <!-- Zoning -->
            <div class="col-md-4">
              <div class="small text-muted">Current Zoning</div>
              <div><?= h($devAgreement['current_zoning'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Proposed Zoning</div>
              <div><?= h($devAgreement['proposed_zoning'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Comp Plan Designation</div>
              <div><?= h($devAgreement['comp_plan_designation'] ?? '') ?: '—' ?></div>
            </div>
            <?php if (!empty($devAgreement['project_description'])): ?>
            <div class="col-12">
              <div class="small text-muted">Project Description</div>
              <div style="white-space:pre-wrap"><?= h($devAgreement['project_description']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($devAgreement['proposed_improvements'])): ?>
            <div class="col-12">
              <div class="small text-muted">Proposed Improvements</div>
              <div style="white-space:pre-wrap"><?= h($devAgreement['proposed_improvements']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Dev Agreement: Developer Entity & Parties -->
      <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
          <h2 class="h6 mb-0 text-info">Developer Entity</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="small text-muted">Corporation / Entity Name</div>
              <div><?= h($devAgreement['developer_entity_name'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Name of Contact</div>
              <div><?= h($devAgreement['developer_contact_name'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Type of Legal Entity</div>
              <div><?= h($devAgreement['developer_entity_type'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Address</div>
              <div><?= h($devAgreement['developer_address'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">State of Incorporation</div>
              <div><?= h($devAgreement['developer_state_of_incorporation'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Phone</div>
              <div><?= h($devAgreement['developer_phone'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Email</div>
              <div><?= $devAgreement['developer_email'] ? '<a href="mailto:' . h($devAgreement['developer_email']) . '">' . h($devAgreement['developer_email']) . '</a>' : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Property Owner</div>
              <div><?= h($devAgreement['property_owner_name'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Attorney</div>
              <div><?= h($devAgreement['attorney_name'] ?? '') ?: '—' ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dev Agreement: Key Dates -->
      <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
          <h2 class="h6 mb-0 text-info">Dev Agreement Dates</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="small text-muted">Anticipated Start</div>
              <div><?= !empty($devAgreement['anticipated_start_date']) ? date('m/d/Y', strtotime($devAgreement['anticipated_start_date'])) : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Anticipated End</div>
              <div><?= !empty($devAgreement['anticipated_end_date']) ? date('m/d/Y', strtotime($devAgreement['anticipated_end_date'])) : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Termination Date</div>
              <div><?= !empty($devAgreement['agreement_termination_date']) ? date('m/d/Y', strtotime($devAgreement['agreement_termination_date'])) : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Planning Board Date</div>
              <div><?= !empty($devAgreement['planning_board_date']) ? date('m/d/Y', strtotime($devAgreement['planning_board_date'])) : '—' ?></div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Town Council Hearing Date</div>
              <div><?= !empty($devAgreement['town_council_hearing_date']) ? date('m/d/Y', strtotime($devAgreement['town_council_hearing_date'])) : '—' ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dev Agreement: Utility & Land Use -->
      <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
          <h2 class="h6 mb-0 text-info">Utility &amp; Land Use</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="small text-muted">Number of Units (SF / ERU)</div>
              <div><?= $devAgreement['number_of_units'] !== null ? h($devAgreement['number_of_units']) : '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Daily Flow Maximum</div>
              <div><?= $devAgreement['daily_flow_maximum'] !== null ? number_format((int)$devAgreement['daily_flow_maximum']) . ' gpd' : '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Transportation Tier</div>
              <div><?= h($devAgreement['transportation_tier'] ?? '') ?: '—' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Parkland Dedication</div>
              <div><?= !empty($devAgreement['parkland_dedication'])
                    ? '<span class="badge text-bg-success">Yes</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?></div>
            </div>
            <?php if (!empty($devAgreement['allocation_elements'])): ?>
            <div class="col-12">
              <div class="small text-muted">Allocation Elements</div>
              <div style="white-space:pre-wrap"><?= h($devAgreement['allocation_elements']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php endif; ?>

    </div>

    <div class="col-lg-4">

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Description</h2>
        </div>
        <div class="card-body">
          <?php $desc = trim((string)($contract['description'] ?? '')); ?>
          <?php if ($desc !== ''): ?>
            <div style="white-space: pre-wrap;"><?= h($desc) ?></div>
          <?php else: ?>
            <div class="text-muted">No description entered.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$isDevAgreement): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0">Procurement &amp; Public Bidding Compliance</h2>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php if (!empty($contract['procurement_method'])): ?>
            <div class="col-md-4">
              <div class="small text-muted">Procurement Method</div>
              <div><?= h($contract['procurement_method']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($contract['bid_rfp_number'])): ?>
            <div class="col-md-3">
              <div class="small text-muted">Bid / RFP Number</div>
              <div><?= h($contract['bid_rfp_number']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($contract['date_approved_by_procurement'])): ?>
            <div class="col-md-4">
              <div class="small text-muted">Date Approved by Procurement</div>
              <div><?= date('m/d/Y', strtotime($contract['date_approved_by_procurement'])) ?></div>
            </div>
            <?php endif; ?>
            <?php $procNotes = trim((string)($contract['procurement_notes'] ?? '')); ?>
            <div class="col-12">
              <div class="small text-muted">Compliance Explanation</div>
              <?php if ($procNotes !== ''): ?>
                <div style="white-space: pre-wrap;"><?= h($procNotes) ?></div>
              <?php else: ?>
                <div class="text-muted fst-italic">No compliance notes entered.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Approval Status Panel ── -->
      <?php
        $approvalMeta = [
            'manager'      => ['label' => 'Manager',      'col' => 'manager_approval_date'],
            'purchasing'   => ['label' => 'Purchasing',   'col' => 'purchasing_approval_date'],
            'legal'        => ['label' => 'Legal',        'col' => 'legal_approval_date'],
            'risk_manager' => ['label' => 'Risk Manager', 'col' => 'risk_manager_approval_date'],
            'council'      => ['label' => 'Council',      'col' => 'council_approval_date'],
        ];
        $requiredApprovals = $requiredApprovals ?? [];
        $userApprovalRoles = $userApprovalRoles ?? [];
        $anyRequired = !empty($requiredApprovals);
        $pendingCount = 0;
        foreach ($requiredApprovals as $rk) {
            if (empty($contract[$approvalMeta[$rk]['col']])) $pendingCount++;
        }
        $approvalRoleLabels = [
            'manager'      => 'Town Manager (TOWN_MANAGER)',
            'purchasing'   => 'Procurement (PROCUREMENT)',
            'legal'        => 'Legal Admin (LEGAL_ADMIN)',
            'risk_manager' => null,
            'council'      => 'Town Council (TOWN_COUNCIL)',
        ];
      ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Approvals</h2>
          <?php if (!$anyRequired): ?>
            <span class="badge text-bg-secondary">No Approvals Required</span>
          <?php elseif ($pendingCount === 0): ?>
            <span class="badge text-bg-success">All Required Approvals Complete</span>
          <?php else: ?>
            <span class="badge text-bg-warning text-dark"><?= $pendingCount ?> Pending</span>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Approval</th><th>Required?</th><th>Date Approved</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($approvalMeta as $key => $meta): ?>
                <?php
                  $isRequired   = in_array($key, $requiredApprovals, true);
                  $approvedDate = $contract[$meta['col']] ?? null;
                  $hasRole      = in_array($key, $userApprovalRoles, true);
                  $roleLabel    = $approvalRoleLabels[$key] ?? null;
                ?>
                <tr class="<?= ($isRequired && !$approvedDate) ? 'table-warning' : '' ?>">
                  <td class="fw-semibold"><?= h($meta['label']) ?></td>
                  <td>
                    <?= $isRequired
                        ? '<span class="badge text-bg-warning text-dark">Required</span>'
                        : '<span class="text-muted small">—</span>' ?>
                  </td>
                  <td>
                    <?php if ($approvedDate): ?>
                      <span class="text-success fw-semibold"><?= date('m/d/Y', strtotime($approvedDate)) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Not approved</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if (is_system_admin()): ?>
                      <button type="button"
                              class="btn btn-sm <?= $hasRole ? 'btn-outline-secondary' : 'btn-outline-warning' ?>"
                              onclick="openStampModal(
                                  '<?= h($key) ?>',
                                  '<?= h($meta['label']) ?>',
                                  <?= $hasRole ? 'true' : 'false' ?>,
                                  <?= $roleLabel ? "'" . h($roleLabel) . "'" : 'null' ?>
                              )">
                        <?= $approvedDate ? 'Re-stamp' : 'Stamp' ?>
                        <?php if (!$hasRole): ?><i class="text-warning">⚠</i><?php endif; ?>
                      </button>
                      <?php if ($key === 'risk_manager' && empty($approvedDate)): ?>
                        <form method="post" action="/index.php?page=approval_email_risk_manager" class="d-inline ms-1">
                          <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
                          <button type="submit" class="btn btn-sm btn-outline-primary">
                            📧 Email Risk Manager
                          </button>
                        </form>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Approval stamp modal -->
      <div class="modal fade" id="approvalStampModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form method="post" action="/index.php?page=approval_stamp" class="modal-content">
            <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <input type="hidden" name="approval_type" id="stampApprovalType">
            <input type="hidden" name="bypass_warning" id="stampBypassFlag" value="0">
            <div class="modal-header" id="stampModalHeader">
              <h5 class="modal-title" id="stampModalTitle">Confirm Approval Stamp</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="stampModalBody"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="stampSubmitBtn">Stamp Today</button>
            </div>
          </form>
        </div>
      </div>
      <script>
      function openStampModal(key, label, hasRole, roleLabel) {
          document.getElementById('stampApprovalType').value = key;
          document.getElementById('stampBypassFlag').value   = '0';

          var header = document.getElementById('stampModalHeader');
          var body   = document.getElementById('stampModalBody');
          var btn    = document.getElementById('stampSubmitBtn');
          var title  = document.getElementById('stampModalTitle');

          if (hasRole) {
              header.className = 'modal-header';
              title.textContent = 'Confirm: Stamp ' + label + ' Approval';
              body.innerHTML = '<p>Stamp <strong>' + label + '</strong> approval as <strong>today</strong>?</p>';
              btn.className = 'btn btn-primary';
              btn.textContent = 'Stamp Today';
          } else {
              header.className = 'modal-header bg-warning';
              title.textContent = '⚠ Role Warning: ' + label + ' Approval';
              var roleMsg = roleLabel ? '<br><span class="text-muted small">Required role: ' + roleLabel + '</span>' : '';
              body.innerHTML =
                  '<div class="alert alert-warning mb-3">' +
                  '<strong>You do not hold the required role</strong> to stamp this approval.' + roleMsg +
                  '</div>' +
                  '<p>You may still stamp this approval, but <strong>it will be flagged as a bypass in contract history.</strong></p>' +
                  '<div class="form-check">' +
                  '<input class="form-check-input" type="checkbox" id="bypassConfirmCheck" onchange="document.getElementById(\'stampBypassFlag\').value = this.checked ? \'1\' : \'0\'; document.getElementById(\'stampSubmitBtn\').disabled = !this.checked;">' +
                  '<label class="form-check-label" for="bypassConfirmCheck">I understand — stamp anyway and log the bypass</label>' +
                  '</div>';
              btn.className  = 'btn btn-warning';
              btn.textContent = 'Stamp Anyway (Bypass)';
              btn.disabled   = true;
          }

          new bootstrap.Modal(document.getElementById('approvalStampModal')).show();
      }
      </script>



    </div>

  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0">Documents</h2>
      <div class="d-flex gap-2">
            <a href="/index.php?page=contract_documents_merge_pdf&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-dark btn-sm">Merge as PDF</a>
            <a href="/index.php?page=contract_document_compare&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-info btn-sm">Compare Documents</a>
            <a href="/index.php?page=contract_document_create&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-secondary btn-sm">Upload Document</a>
            <a href="/index.php?page=contracts_generate_word&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-primary btn-sm">Generate Doc</a>
            <a href="/index.php?page=contracts_generate_html&contract_id=<?= (int)$contract['contract_id'] ?>" target="_blank" class="btn btn-outline-success btn-sm">Generate HTML</a>
          </div>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success m-2"><?= h($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div class="alert alert-warning m-2">
              <strong>Some documents could not be converted:</strong>
              <ul class="mb-0 mt-1">
                <?php foreach ((array)$_SESSION['flash_errors'] as $fe): ?>
                  <li><?= h($fe) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php unset($_SESSION['flash_errors']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['docusign_flash_success'])): ?>
            <div class="alert alert-success m-2"><?= h($_SESSION['docusign_flash_success']) ?></div>
            <?php unset($_SESSION['docusign_flash_success']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['docusign_flash_error'])): ?>
            <div class="alert alert-danger m-2"><?= h($_SESSION['docusign_flash_error']) ?></div>
            <?php unset($_SESSION['docusign_flash_error']); ?>
          <?php endif; ?>
          <?php if (!empty($documents)): ?>
            <form method="post" action="/index.php?page=contract_documents_save_order">
              <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>Draft</th>
                    <th>Type</th>
                    <th>PDF Stamp</th>
                    <th>Description</th>
                    <th>File</th>
                    <th>Created</th>
                    <th>Created By</th>
                    <th>Signature</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($documents as $doc): ?>
                    <tr>
                      <td>
                        <input type="number" name="order[<?= (int)$doc['contract_document_id'] ?>]" value="<?= (int)($doc['sort_order'] ?? 0) ?>" class="form-control form-control-sm" style="width:55px" min="0">
                      </td>
                      <td><?= !empty($doc['file_name']) ? h($doc['file_name']) : '—' ?></td>
                      <td><?= !empty($doc['doc_type']) ? h($doc['doc_type']) : '—' ?></td>
                      <td>
                        <input type="text" name="exhibit_label[<?= (int)$doc['contract_document_id'] ?>]" value="<?= h($doc['exhibit_label'] ?? '') ?>" class="form-control form-control-sm" style="width:140px" maxlength="50" placeholder="no stamp">
                      </td>
                      <td><?= !empty($doc['description']) ? h($doc['description']) : '—' ?></td>
                      <td>
                        <?php
                          $webPath = '';
                          if (!empty($doc['file_path'])) {
                            $webPath = $doc['file_path'];
                            // Remove any absolute path prefix, keep only web path
                            if (strpos($webPath, '/storage/') === false && ($pos = strpos($webPath, 'storage/')) !== false) {
                              $webPath = '/' . substr($webPath, $pos);
                            } elseif (strpos($webPath, '/storage/') !== 0) {
                              $webPath = '/' . ltrim($webPath, '/');
                            }
                          }
                        ?>
                        <?php if ($webPath): ?>
                          <a href="<?= h($webPath) ?>" target="_blank">Open</a>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><?= !empty($doc['created_at']) ? date('m/d/y H:i', strtotime($doc['created_at'])) : '—' ?></td>
                      <td><?= !empty($doc['created_by_name']) ? h($doc['created_by_name']) : '—' ?></td>
                      <td style="white-space:nowrap">
                        <?php
                          $dsStatus = $doc['docusign_status'] ?? null;
                          $dsDocId  = (int)$doc['contract_document_id'];
                          $dsCtrId  = (int)$contract['contract_id'];
                          $canSend  = $dsStatus === null || in_array($dsStatus, ['voided', 'declined'], true);
                          $canVoid  = $dsStatus !== null && in_array($dsStatus, ['sent', 'delivered', 'created', 'correct'], true);
                          $dsBadgeMap = [
                            'sent'      => 'warning',
                            'delivered' => 'info',
                            'completed' => 'success',
                            'declined'  => 'danger',
                            'voided'    => 'secondary',
                            'created'   => 'secondary',
                            'correct'   => 'info',
                          ];
                          $dsBadge = $dsStatus !== null ? ($dsBadgeMap[$dsStatus] ?? 'light') : null;
                        ?>
                        <?php if ($dsStatus !== null): ?>
                          <span class="badge text-bg-<?= h($dsBadge) ?> me-1"><?= h(ucfirst($dsStatus)) ?></span>
                        <?php endif; ?>
                        <?php if ($canSend && $dsDocId > 0): ?>
                          <a href="/index.php?page=docusign_auth&doc_id=<?= $dsDocId ?>&contract_id=<?= $dsCtrId ?>"
                             class="btn btn-outline-secondary btn-sm">
                            <?= $dsStatus !== null ? 'Re-send' : 'Send for Signature' ?>
                          </a>
                        <?php endif; ?>
                        <?php if ($canVoid && $dsDocId > 0): ?>
                          <button type="button" class="btn btn-outline-warning btn-sm ms-1"
                                  onclick="if(confirm('Void this envelope? Signers will no longer be able to sign.')){let f=document.createElement('form');f.method='post';f.action='/index.php?page=docusign_void';let i1=document.createElement('input');i1.type='hidden';i1.name='doc_id';i1.value='<?= $dsDocId ?>';let i2=document.createElement('input');i2.type='hidden';i2.name='contract_id';i2.value='<?= $dsCtrId ?>';f.appendChild(i1);f.appendChild(i2);document.body.appendChild(f);f.submit();}">Void</button>
                        <?php endif; ?>
                        <?php if ($dsStatus === null && $dsDocId <= 0): ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if (!empty($doc['contract_document_id']) && (int)$doc['contract_document_id'] > 0): ?>
                          <a href="/index.php?page=contract_document_email&id=<?= (int)$doc['contract_document_id'] ?>" class="btn btn-outline-primary btn-sm">Email Doc</a>
                          <button type="button" class="btn btn-outline-danger btn-sm ms-1" onclick="if(confirm('Delete this document?')){let f=document.createElement('form');f.method='post';f.action='/index.php?page=contract_document_delete';let i=document.createElement('input');i.type='hidden';i.name='document_id';i.value='<?= (int)$doc['contract_document_id'] ?>';f.appendChild(i);document.body.appendChild(f);f.submit();}">Delete</button>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
              <div class="p-2 text-end">
                <button type="submit" class="btn btn-sm btn-outline-primary">Save Order &amp; Labels</button>
              </div>
            </form>
          <?php else: ?>
            <div class="p-3 text-muted">No drafts added.</div>
          <?php endif; ?>
        </div>
      </div>

  <!-- Bidding Compliance Log -->
  <?php if (!$isDevAgreement): ?>
  <div class="row mt-4" id="bidding-compliance">
    <div class="col-12">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Bidding Compliance Log</h2>
          <span class="badge bg-secondary"><?= count($complianceRecords ?? []) ?></span>
        </div>

        <!-- Add entry form -->
        <div class="card-body border-bottom">
          <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success py-2 mb-3"><?= h($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
          <?php endif; ?>
          <form method="post" action="/index.php?page=bidding_compliance_store" enctype="multipart/form-data">
            <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <div class="row g-2 align-items-end">
              <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Date</label>
                <input type="date" name="event_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Event</label>
                <select name="event_type" class="form-select form-select-sm" required>
                  <option value="">— Select —</option>
                  <?php foreach (\BiddingComplianceController::EVENT_TYPES as $et): ?>
                    <option value="<?= h($et) ?>"><?= h($et) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Comment</label>
                <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Optional notes…"></textarea>
              </div>
              <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">File (optional)</label>
                <input type="file" name="compliance_file" class="form-control form-control-sm" accept=".pdf,.docx,.doc,.xlsx,.xls,.png,.jpg,.jpeg">
              </div>
              <div class="col-12">
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" name="is_consortium" id="bc_is_consortium" value="1"
                         onchange="document.getElementById('bc_consortium_fields').classList.toggle('d-none', !this.checked)">
                  <label class="form-check-label small" for="bc_is_consortium">Bidding Consortium used</label>
                </div>
                <div id="bc_consortium_fields" class="d-none mt-2">
                  <div class="row g-2">
                    <div class="col-md-5">
                      <label class="form-label form-label-sm mb-1">Consortium / Group Name</label>
                      <input type="text" name="consortium_name" class="form-control form-control-sm" maxlength="200"
                             placeholder="e.g. NCPA, Sourcewell, TIPS…">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label form-label-sm mb-1">Master Contract #</label>
                      <input type="text" name="consortium_contract_number" class="form-control form-control-sm" maxlength="100"
                             placeholder="e.g. 01-112 or 4400023640">
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
              </div>
            </div>
          </form>
        </div>

        <!-- Records table -->
        <?php if (!empty($complianceRecords)): ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Event</th>
                  <th>Comment</th>
                  <th>Consortium</th>
                  <th>Document</th>
                  <th>By</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($complianceRecords as $rec): ?>
                  <tr>
                    <td class="text-nowrap"><?= date('m/d/Y', strtotime($rec['event_date'])) ?></td>
                    <td class="text-nowrap"><span class="badge bg-info text-dark"><?= h($rec['event_type']) ?></span></td>
                    <td style="white-space:pre-wrap"><?= h($rec['comment'] ?? '') ?></td>
                    <td>
                      <?php if (!empty($rec['is_consortium'])): ?>
                        <span class="badge bg-secondary">Consortium</span>
                        <?php if (!empty($rec['consortium_name'])): ?>
                          <div class="small fw-semibold"><?= h($rec['consortium_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rec['consortium_contract_number'])): ?>
                          <div class="small text-muted">Ctr# <?= h($rec['consortium_contract_number']) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($rec['doc_file_path'])): ?>
                        <?php
                          $docWeb = '/' . ltrim($rec['doc_file_path'], '/');
                        ?>
                        <a href="<?= h($docWeb) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">📄 <?= h($rec['doc_file_name']) ?></a>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= h($rec['created_by_name'] ?? '—') ?></td>
                    <td>
                      <form method="post" action="/index.php?page=bidding_compliance_delete" onsubmit="return confirm('Delete this record?')">
                        <input type="hidden" name="compliance_id" value="<?= (int)$rec['compliance_id'] ?>">
                        <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-3 text-muted">No compliance records yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; /* !$isDevAgreement */ ?>

  <!-- Contract History -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Contract History</h2>
          <span class="badge bg-secondary"><?= count($history ?? []) ?></span>
        </div>
        <div class="card-body border-bottom py-2">
          <form method="post" action="/index.php?page=contract_history_add" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
            <div class="flex-grow-1">
              <input type="text" class="form-control form-control-sm" name="note" placeholder="Add a note or comment..." required>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Add Note</button>
          </form>
        </div>
        <?php if (!empty($history)): ?>
          <?php $canDeleteHistory = function_exists('is_system_admin') && is_system_admin(); ?>
          <?php if ($canDeleteHistory): ?>
          <form method="post" action="/index.php?page=contract_history_delete" id="historyDeleteForm">
            <input type="hidden" name="contract_id" value="<?= (int)$contract['contract_id'] ?>">
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <?php if ($canDeleteHistory): ?>
                  <th style="width:36px">
                    <input type="checkbox" id="historySelectAll" title="Select all"
                           onclick="document.querySelectorAll('.history-cb').forEach(cb => cb.checked = this.checked)">
                  </th>
                  <?php endif; ?>
                  <th>Date</th>
                  <th>Event</th>
                  <th>Details</th>
                  <th>By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $entry): ?>
                  <tr>
                    <?php if ($canDeleteHistory): ?>
                    <td><input type="checkbox" class="history-cb" name="history_ids[]" value="<?= (int)$entry['history_id'] ?>"></td>
                    <?php endif; ?>
                    <td class="text-nowrap"><?= date('m/d/y H:i', strtotime($entry['changed_at'])) ?></td>
                    <td>
                      <?php
                        $eventLabels = [
                          'contract_created' => '<span class="badge bg-success">Created</span>',
                          'contract_updated' => '<span class="badge bg-info text-dark">Updated</span>',
                          'status_change' => '<span class="badge bg-warning text-dark">Status Change</span>',
                          'document_generated' => '<span class="badge bg-primary">Doc Generated</span>',
                          'document_uploaded' => '<span class="badge bg-primary">Doc Uploaded</span>',
                          'document_deleted' => '<span class="badge bg-danger">Doc Deleted</span>',
                          'document_emailed' => '<span class="badge bg-info text-dark">Doc Emailed</span>',
                          'manual_note' => '<span class="badge bg-dark">Note</span>',
                        ];
                        echo $eventLabels[$entry['event_type']] ?? '<span class="badge bg-secondary">' . h($entry['event_type']) . '</span>';
                      ?>
                    </td>
                    <td>
                      <?php if ($entry['event_type'] === 'status_change'): ?>
                        <?= h($entry['old_status'] ?? '') ?> &rarr; <?= h($entry['new_status'] ?? '') ?>
                      <?php else: ?>
                        <?= h($entry['notes'] ?? '') ?>
                      <?php endif; ?>
                    </td>
                    <td><?= h($entry['changed_by_name'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($canDeleteHistory): ?>
          <div class="p-2 border-top">
            <button type="submit" form="historyDeleteForm" class="btn btn-sm btn-danger"
                    onclick="
                      var checked = document.querySelectorAll('.history-cb:checked');
                      if (checked.length === 0) { alert('No entries selected.'); return false; }
                      return confirm('Delete ' + checked.length + ' selected entr' + (checked.length === 1 ? 'y' : 'ies') + '?');
                    ">Delete Selected</button>
          </div>
          </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="p-3 text-muted">No history recorded yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <a href="/index.php?page=contracts_edit&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-primary">Change Contract Info</a>
    <a href="/index.php?page=contract_document_create&contract_id=<?= (int)$contract['contract_id'] ?>" class="btn btn-outline-secondary">Add Document</a>
  </div>

</div>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>