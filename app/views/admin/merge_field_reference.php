<?php
require_login();
if (!is_system_admin()) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}
require_once APP_ROOT . '/app/views/layouts/header.php';
?>

<div class="container-fluid px-4 py-4">
  <div class="d-flex align-items-center mb-4 gap-3">
    <a href="/index.php?page=system_settings" class="btn btn-sm btn-outline-secondary">&larr; System Settings</a>
    <h1 class="h4 mb-0">Merge Field Reference</h1>
  </div>

  <p class="text-muted mb-4">
    In your Word (.docx) or HTML templates, wrap any field name below in
    <code>${"{"}</code>…<code>{"}"}</code> — for example <code>${"{"} contract_number {"}"}</code>.
    All fields listed are automatically available when a document is generated.
  </p>

  <!-- ── All Contracts ─────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">All Contract Templates</h2>
      <div class="small text-muted">Available in every template type</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name (use in template)</th><th>Description / Example</th></tr>
        </thead>
        <tbody>
          <?php
          $contractFields = [
            ['contract_id',              'Internal numeric ID of the contract'],
            ['contract_number',          'Contract number (e.g. 2024-001)'],
            ['contract_title',           'Title / short description of the contract'],
            ['contract_status',          'Current status label'],
            ['contract_type_name',       'Contract type name (e.g. Development Agreement)'],
            ['effective_date',           'Effective date (YYYY-MM-DD)'],
            ['expiration_date',          'Expiration / end date (YYYY-MM-DD)'],
            ['date_approved',            'Date the contract was approved'],
            ['contract_amount',          'Contract dollar amount (raw decimal)'],
            ['owner_company_id',         'ID of the municipality / owner company'],
            ['owner_company_name',       'Name of the municipality / owner company'],
            ['counterparty_company_id',  'ID of the counterparty company'],
            ['counterparty_company_name','Name of the counterparty company'],
            ['description',              'Long description / scope of the contract'],
            ['notes',                    'Internal notes'],
            ['po_number',                'Purchase order number'],
            ['procurement_method',       'Procurement method used'],
            ['use_standard_contract',    '1 or 0 — whether standard contract form is used'],
            ['created_at',               'Record creation timestamp'],
            ['updated_at',               'Record last-updated timestamp'],
          ];
          foreach ($contractFields as [$name, $desc]): ?>
          <tr>
            <td><code>${<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>}</code></td>
            <td class="text-muted small"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Development Agreement ─────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-info bg-opacity-10">
      <h2 class="h6 mb-0 text-info">Development Agreement Templates</h2>
      <div class="small text-muted">Available in addition to all contract fields above</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name</th><th>Description</th></tr>
        </thead>
        <tbody>
          <?php
          $daFields = [
            // prefixed versions
            ['da_project_name',                   'Project name'],
            ['da_project_description',            'Project description (long text)'],
            ['da_property_address',               'Primary property address'],
            ['da_property_pin',                   'Primary parcel PIN'],
            ['da_property_realestateid',          'Primary real estate ID'],
            ['da_property_acerage',               'Property acreage'],
            ['da_current_zoning',                 'Current zoning designation'],
            ['da_proposed_zoning',                'Proposed zoning designation'],
            ['da_comp_plan_designation',          'Comprehensive plan designation'],
            ['da_proposed_improvements',          'Proposed improvements (long text)'],
            ['da_property_owner_name',            'Property owner name'],
            ['da_developer_entity_name',          'Developer entity / corporation name'],
            ['da_developer_contact_name',         'Developer primary contact name'],
            ['da_developer_address',              'Developer mailing address'],
            ['da_developer_phone',                'Developer phone number'],
            ['da_developer_email',                'Developer email address'],
            ['da_developer_state_of_incorporation','State of incorporation'],
            ['da_developer_entity_type',          'Type of legal entity (LLC, Corp, etc.)'],
            ['da_anticipated_start_date',         'Anticipated project start date'],
            ['da_anticipated_end_date',           'Anticipated project end date'],
            ['da_agreement_termination_date',     'Agreement termination date'],
            ['da_planning_board_date',            'Planning board hearing date'],
            ['da_town_council_hearing_date',      'Town council hearing date'],
            // New utility / land-use fields
            ['da_number_of_units',               'Number of single-family homes / ERUs (integer)'],
            ['da_daily_flow_maximum',            'Daily flow maximum — formatted as "X,XXX gpd"'],
            ['da_allocation_elements',           'List of allocation elements (long text)'],
            ['da_parkland_dedication_label',     '"Yes" or "No" — parkland dedication required'],
            ['da_transportation_tier',           'Transportation tier (Tier 1 / Tier 2 / Tier 3)'],
          ];
          foreach ($daFields as [$name, $desc]): ?>
          <tr>
            <td><code>${<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>}</code></td>
            <td class="text-muted small"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-muted small">
      All DA fields are also available without the <code>da_</code> prefix unless a core contract field has the same name.
    </div>
  </div>

  <!-- ── Change Orders ─────────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-warning bg-opacity-10">
      <h2 class="h6 mb-0">Change Order Templates</h2>
      <div class="small text-muted">Available in addition to all contract fields (and DA fields if the contract is a Development Agreement)</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name</th><th>Description</th></tr>
        </thead>
        <tbody>
          <?php
          $coFields = [
            ['change_order_id',     'Internal change order ID'],
            ['change_order_number', 'Change order number (e.g. CO-001)'],
            ['co_justification',    'Justification / reason for change order'],
            ['co_amount',           'Amount as a decimal number (e.g. 1500.00)'],
            ['co_amount_formatted', 'Amount formatted with $ sign (e.g. $1,500.00)'],
            ['approval_date',       'Approval date — long format (e.g. April 23, 2026)'],
            ['approval_date_short', 'Approval date — short format (e.g. 04/23/2026)'],
          ];
          foreach ($coFields as [$name, $desc]): ?>
          <tr>
            <td><code>${<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>}</code></td>
            <td class="text-muted small"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Tips ──────────────────────────────────────────────────── -->
  <div class="card shadow-sm border-secondary-subtle">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">Tips for Building Templates</h2>
    </div>
    <div class="card-body">
      <ul class="mb-0 small">
        <li>In <strong>Word (.docx)</strong>: type <code>${field_name}</code> directly in the document — no special formatting needed. Use <em>Find &amp; Replace</em> to verify the placeholder doesn't get split across runs.</li>
        <li>In <strong>HTML templates</strong>: use <code>{{field_name}}</code> (double curly braces).</li>
        <li>If a field is blank or null, the placeholder is replaced with an empty string.</li>
        <li>Date fields are stored as <code>YYYY-MM-DD</code>. Format them in Word with a date-format switch if needed, or use the pre-formatted <code>_short</code> / <code>_label</code> variants where provided.</li>
        <li>The <code>da_daily_flow_maximum</code> field is already formatted with commas and "gpd" suffix.</li>
      </ul>
    </div>
  </div>

</div>

<?php require_once APP_ROOT . '/app/views/layouts/footer.php'; ?>
