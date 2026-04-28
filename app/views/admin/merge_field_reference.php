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
    Use these field names in your templates to insert live contract data when a document is generated.
    <strong>Word (.docx):</strong> wrap in <code>${field_name}</code> &nbsp;|&nbsp;
    <strong>HTML:</strong> wrap in <code>{{field_name}}</code>.
    All fields are replaced with an empty string if the value is blank or null.
  </p>

  <!-- ── Core Contract Fields ──────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">Core Contract Fields</h2>
      <div class="small text-muted">Available in every contract template</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name</th><th>Description / Example</th></tr>
        </thead>
        <tbody>
          <?php
          $contractFields = [
            ['contract_id',              'Internal numeric ID of the contract'],
            ['contract_number',          'Contract number (e.g. 2024-001)'],
            ['name',                     'Contract title / short name'],
            ['description',              'Full scope / description of the contract'],
            ['status_name',              'Current status label (e.g. Active, Out For Signature)'],
            ['status_comment',           'Optional comment on the current status'],
            ['contract_type_name',       'Contract type (e.g. Development Agreement, Service Contract)'],
            ['start_date',               'Contract start / effective date (YYYY-MM-DD)'],
            ['end_date',                 'Contract end / expiration date (YYYY-MM-DD)'],
            ['total_contract_value',     'Contract dollar amount (raw decimal, e.g. 150000.00)'],
            ['currency',                 'Currency code (e.g. USD)'],
            ['po_number',                'Purchase order number'],
            ['po_amount',                'PO dollar amount (raw decimal)'],
            ['account_number',           'Account / GL number'],
            ['governing_law',            'Governing law / jurisdiction'],
            ['auto_renew',               '1 or 0 — whether contract auto-renews'],
            ['renewal_term_months',      'Renewal term length in months'],
            ['minimum_insurance_coi',    '1 or 0 — COI required'],
            ['use_standard_contract',    '1 or 0 — whether standard contract form is used'],
            ['procurement_method',       'Procurement method (e.g. Open Market, Formal Bid)'],
            ['bid_rfp_number',           'Bid or RFP reference number'],
            ['procurement_notes',        'Procurement narrative / notes'],
            ['date_approved_by_procurement', 'Date approved by procurement (YYYY-MM-DD)'],
            ['date_approved_by_manager', 'Date approved by manager (YYYY-MM-DD)'],
            ['date_approved_by_council', 'Date approved by council (YYYY-MM-DD)'],
            ['manager_approval_date',    'Manager approval date (YYYY-MM-DD)'],
            ['purchasing_approval_date', 'Purchasing approval date (YYYY-MM-DD)'],
            ['legal_approval_date',      'Legal approval date (YYYY-MM-DD)'],
            ['risk_manager_approval_date','Risk manager approval date (YYYY-MM-DD)'],
            ['council_approval_date',    'Council approval date (YYYY-MM-DD)'],
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

  <!-- ── Parties & Contacts ────────────────────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">Parties &amp; Contacts</h2>
      <div class="small text-muted">Company and contact person fields — available in every contract template</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name</th><th>Description</th></tr>
        </thead>
        <tbody>
          <?php
          $partyFields = [
            ['owner_company_id',                 'ID of the municipality / owner company'],
            ['owner_company_name',               'Name of the municipality / owner company'],
            ['owner_primary_contact_name',       'Full name of the municipality\'s primary contact'],
            ['owner_primary_contact_email',      'Email of the municipality\'s primary contact'],
            ['counterparty_company_id',          'ID of the counterparty (vendor) company'],
            ['counterparty_company_name',        'Name of the counterparty (vendor) company'],
            ['counterparty_primary_contact_name','Full name of the counterparty\'s primary contact (from contract)'],
            ['counterparty_primary_contact_email','Email of the counterparty\'s primary contact (from contract)'],
            ['department_name',                  'Department responsible for this contract'],
            ['department_code',                  'Department code / abbreviation'],
            ['payment_terms_name',               'Payment terms label (e.g. Net 30)'],
            ['payment_terms_description',        'Payment terms description / detail'],
          ];
          foreach ($partyFields as [$name, $desc]): ?>
          <tr>
            <td><code>${<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>}</code></td>
            <td class="text-muted small"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Company Address & Detail Fields ───────────────────────── -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">Company Address &amp; Detail Fields</h2>
      <div class="small text-muted">Pulled live from the Companies record — available in every contract template</div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered table-hover mb-0">
        <thead class="table-light">
          <tr><th style="width:35%">Field Name</th><th>Description</th></tr>
        </thead>
        <tbody>
          <?php
          $companyFields = [
            // Counterparty
            ['counterparty_address',          'Counterparty single-line address (legacy address field)'],
            ['counterparty_address_line1',    'Counterparty street address line 1'],
            ['counterparty_address_line2',    'Counterparty street address line 2 (suite, unit, etc.)'],
            ['counterparty_city',             'Counterparty city'],
            ['counterparty_state',            'Counterparty state / region'],
            ['counterparty_postal_code',      'Counterparty ZIP / postal code'],
            ['counterparty_city_state_zip',   'Counterparty city, state ZIP — formatted as "City, ST 00000"'],
            ['counterparty_country',          'Counterparty country'],
            ['counterparty_phone',            'Counterparty main phone number'],
            ['counterparty_email',            'Counterparty main email address'],
            ['counterparty_contact_name',     'Counterparty primary contact name (from company record)'],
            ['counterparty_website',          'Counterparty website URL'],
            ['counterparty_tax_id',           'Counterparty tax ID / EIN'],
            ['counterparty_signer1_name',     'Counterparty authorized signer 1 — name'],
            ['counterparty_signer1_title',    'Counterparty authorized signer 1 — title'],
            ['counterparty_signer1_email',    'Counterparty authorized signer 1 — email'],
            ['counterparty_signer2_name',     'Counterparty authorized signer 2 — name'],
            ['counterparty_signer2_title',    'Counterparty authorized signer 2 — title'],
            ['counterparty_signer2_email',    'Counterparty authorized signer 2 — email'],
            ['counterparty_signer3_name',     'Counterparty authorized signer 3 — name'],
            ['counterparty_signer3_title',    'Counterparty authorized signer 3 — title'],
            ['counterparty_signer3_email',    'Counterparty authorized signer 3 — email'],
            // Owner / municipality
            ['owner_company_address',         'Owner/municipality single-line address'],
            ['owner_address_line1',           'Owner street address line 1'],
            ['owner_address_line2',           'Owner street address line 2'],
            ['owner_city',                    'Owner city'],
            ['owner_state',                   'Owner state / region'],
            ['owner_postal_code',             'Owner ZIP / postal code'],
            ['owner_city_state_zip',          'Owner city, state ZIP — formatted as "City, ST 00000"'],
            ['owner_country',                 'Owner country'],
            ['owner_phone',                   'Owner main phone number'],
            ['owner_email',                   'Owner main email address'],
            ['owner_contact_name',            'Owner primary contact name (from company record)'],
            ['owner_website',                 'Owner website URL'],
          ];
          foreach ($companyFields as [$name, $desc]): ?>
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
        <li>In <strong>Word (.docx)</strong>: type <code>${field_name}</code> directly in the document. Use <em>Find &amp; Replace</em> to verify the placeholder wasn't split across runs.</li>
        <li>In <strong>HTML templates</strong>: use <code>{{field_name}}</code> (double curly braces).</li>
        <li>If a field is blank or null, the placeholder is replaced with an empty string.</li>
        <li>Date fields are stored as <code>YYYY-MM-DD</code>. Format them in Word with a date-format switch if needed.</li>
        <li><code>da_daily_flow_maximum</code> is pre-formatted with commas and "gpd" suffix.</li>
        <li><code>co_amount_formatted</code> is pre-formatted with a $ sign and commas (e.g. <code>$1,500.00</code>).</li>
        <li>All DA fields are also available <em>without</em> the <code>da_</code> prefix unless a core contract field has the same name.</li>
      </ul>
    </div>
  </div>

</div>

<?php require_once APP_ROOT . '/app/views/layouts/footer.php'; ?>
