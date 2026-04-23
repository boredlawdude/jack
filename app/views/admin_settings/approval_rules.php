<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$fieldOptions    = ApprovalRulesController::FIELD_OPTIONS;
$approvalLabels  = ApprovalRulesController::APPROVAL_LABELS;
$operators       = ApprovalRulesController::OPERATORS;
// $contractTypes is passed by the controller as array of [contract_type_id, contract_type]
$contractTypesById = [];
foreach (($contractTypes ?? []) as $ct) {
    $contractTypesById[(int)$ct['contract_type_id']] = $ct['contract_type'];
}
?>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-0">Approval Rules</h1>
      <div class="text-muted small mt-1">
        Define threshold-based rules that determine which approvals a contract requires.
      </div>
    </div>
    <a href="/index.php?page=admin_settings" class="btn btn-outline-secondary btn-sm">← System Settings</a>
  </div>

  <?php if (!empty($flashMessages)): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?php foreach ($flashMessages as $m): ?><div><?= h($m) ?></div><?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if (!empty($flashErrors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?php foreach ($flashErrors as $m): ?><div><?= h($m) ?></div><?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- ── Existing rules ── -->
  <div class="card shadow-sm mb-5">
    <div class="card-header bg-white fw-semibold">Active Rules</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Rule Name</th>
            <th>When…</th>
            <th>Requires</th>
            <th class="text-center">Waived if<br>Std Contract?</th>
            <th class="text-center">Waived if<br>COI ≥$5M?</th>
            <th class="text-center">Active</th>
            <th class="text-center">Order</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rules)): ?>
            <tr><td colspan="6" class="text-muted p-3">No rules yet.</td></tr>
          <?php else: ?>
            <?php foreach ($rules as $rule): ?>
              <tr>
                <td><?= h($rule['rule_name']) ?></td>
                <td>
                  <span class="text-muted"><?= h($fieldOptions[$rule['contract_field']] ?? $rule['contract_field']) ?></span>
                  <strong class="mx-1"><?= h($rule['operator']) ?></strong>
                  <?php if ($rule['contract_field'] === 'contract_type_id'): ?>
                    <?= h($contractTypesById[(int)$rule['threshold_value']] ?? 'Type #' . (int)$rule['threshold_value']) ?>
                  <?php else: ?>
                    <?= h(number_format((float)$rule['threshold_value'], 0)) ?>
                    <?= $rule['contract_field'] === 'total_contract_value' ? '<span class="text-muted small">(USD)</span>' : '' ?>
                  <?php endif; ?>
                  <?php if (!empty($rule['contract_field_2']) && !empty($rule['operator_2']) && isset($rule['threshold_value_2'])): ?>
                    <span class="text-muted small ms-1">AND</span>
                    <span class="text-muted"><?= h($fieldOptions[$rule['contract_field_2']] ?? $rule['contract_field_2']) ?></span>
                    <strong class="mx-1"><?= h($rule['operator_2']) ?></strong>
                    <?php if ($rule['contract_field_2'] === 'contract_type_id'): ?>
                      <?= h($contractTypesById[(int)$rule['threshold_value_2']] ?? 'Type #' . (int)$rule['threshold_value_2']) ?>
                    <?php else: ?>
                      <?= h(number_format((float)$rule['threshold_value_2'], 0)) ?>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge text-bg-primary"><?= h($approvalLabels[$rule['required_approval']] ?? $rule['required_approval']) ?></span>
                </td>
                <td class="text-center">
                  <?= !empty($rule['waived_by_standard_contract'])
                    ? '<span class="badge text-bg-info">Yes</span>'
                    : '<span class="text-muted small">—</span>' ?>
                </td>
                <td class="text-center">
                  <?= !empty($rule['waived_by_min_insurance'])
                    ? '<span class="badge text-bg-success">Yes</span>'
                    : '<span class="text-muted small">—</span>' ?>
                </td>
                <td class="text-center">
                  <?= (int)$rule['is_active'] === 1
                    ? '<span class="badge text-bg-success">Yes</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
                </td>
                <td class="text-center"><?= (int)$rule['sort_order'] ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="openEditModal(<?= htmlspecialchars(json_encode($rule), ENT_QUOTES) ?>)">
                    Edit
                  </button>
                  <form method="post" action="/index.php?page=approval_rules_delete&rule_id=<?= (int)$rule['rule_id'] ?>"
                        class="d-inline" onsubmit="return confirm('Delete this rule?');">
                    <button class="btn btn-sm btn-outline-danger ms-1">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Add new rule ── -->
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Add New Rule</div>
    <div class="card-body">
      <form method="post" action="/index.php?page=approval_rules_store">
        <?= _approval_rule_fields(null, $fieldOptions, $approvalLabels, $operators, $contractTypes ?? []) ?>
        <div class="mt-3">
          <button class="btn btn-primary">Add Rule</button>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- ── Edit modal ── -->
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" id="editRuleForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Rule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="editRuleBody">
        <!-- filled by JS -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php
// Helper to render the shared form fields (used both in add form and modal)
function _approval_rule_fields(?array $rule, array $fieldOptions, array $approvalLabels, array $operators, array $contractTypes = []): string
{
    ob_start();
    $uid = uniqid();
    $v = fn($k) => htmlspecialchars((string)($rule[$k] ?? ''), ENT_QUOTES, 'UTF-8');
    $currentField = $rule['contract_field'] ?? 'total_contract_value';
    ?>
    <div class="row g-3">

      <div class="col-12">
        <label class="form-label">Rule Name <span class="text-danger">*</span></label>
        <input class="form-control" name="rule_name" value="<?= $v('rule_name') ?>" required
               placeholder="e.g. Manager approval over $30k">
      </div>

      <div class="col-md-4">
        <label class="form-label">Contract Field <span class="text-danger">*</span></label>
        <select class="form-select" name="contract_field" id="cf_<?= $uid ?>" required
                onchange="toggleThreshold_<?= $uid ?>(this.value)">
          <?php foreach ($fieldOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
              <?= $currentField === $key ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Operator <span class="text-danger">*</span></label>
        <select class="form-select" name="operator" required>
          <?php foreach ($operators as $op => $label): ?>
            <option value="<?= htmlspecialchars($op, ENT_QUOTES) ?>"
              <?= ($rule['operator'] ?? '>') === $op ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Numeric threshold (shown for value/months) -->
      <div class="col-md-3" id="threshNum_<?= $uid ?>"
           style="<?= $currentField === 'contract_type_id' ? 'display:none' : '' ?>">
        <label class="form-label">Threshold Value <span class="text-danger">*</span></label>
        <input class="form-control" name="threshold_value" id="threshNumIn_<?= $uid ?>"
               type="number" step="0.01"
               value="<?= $currentField !== 'contract_type_id' ? $v('threshold_value') : '' ?>"
               placeholder="e.g. 30000"
               <?= $currentField !== 'contract_type_id' ? 'required' : 'disabled' ?>>
      </div>

      <!-- Contract-type dropdown (shown for contract_type_id) -->
      <div class="col-md-3" id="threshType_<?= $uid ?>"
           style="<?= $currentField !== 'contract_type_id' ? 'display:none' : '' ?>">
        <label class="form-label">Contract Type <span class="text-danger">*</span></label>
        <select class="form-select" name="threshold_value" id="threshTypeIn_<?= $uid ?>"
                <?= $currentField !== 'contract_type_id' ? 'disabled' : '' ?>>
          <?php foreach ($contractTypes as $ct): ?>
            <option value="<?= (int)$ct['contract_type_id'] ?>"
              <?= ((int)($rule['threshold_value'] ?? 0) === (int)$ct['contract_type_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ct['contract_type'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Required Approval <span class="text-danger">*</span></label>
        <select class="form-select" name="required_approval" required>
          <?php foreach ($approvalLabels as $key => $label): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
              <?= ($rule['required_approval'] ?? '') === $key ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Sort Order</label>
        <input class="form-control" name="sort_order" type="number" value="<?= $v('sort_order') ?: '0' ?>">
      </div>

      <div class="col-12">
        <hr class="my-1">
        <div class="text-muted small mb-2">Optional: Add a second condition (AND logic — both must match)</div>
        <div class="row g-3">
          <?php
            $currentField2 = $rule['contract_field_2'] ?? '';
          ?>
          <div class="col-md-4">
            <label class="form-label">AND Field</label>
            <select class="form-select" name="contract_field_2" id="cf2_<?= $uid ?>"
                    onchange="toggleThreshold2_<?= $uid ?>(this.value)">
              <option value="">(none)</option>
              <?php foreach ($fieldOptions as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
                  <?= $currentField2 === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Operator</label>
            <select class="form-select" name="operator_2">
              <?php foreach ($operators as $op => $label): ?>
                <option value="<?= htmlspecialchars($op, ENT_QUOTES) ?>"
                  <?= ($rule['operator_2'] ?? '') === $op ? 'selected' : '' ?>>
                  <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3" id="thresh2Num_<?= $uid ?>"
               style="<?= $currentField2 === 'contract_type_id' ? 'display:none' : '' ?>">
            <label class="form-label">Threshold</label>
            <input class="form-control" name="threshold_value_2" id="thresh2NumIn_<?= $uid ?>"
                   type="number" step="0.01"
                   value="<?= $currentField2 !== 'contract_type_id' ? $v('threshold_value_2') : '' ?>"
                   placeholder="e.g. 90000">
          </div>
          <div class="col-md-3" id="thresh2Type_<?= $uid ?>"
               style="<?= $currentField2 !== 'contract_type_id' ? 'display:none' : '' ?>">
            <label class="form-label">Contract Type</label>
            <select class="form-select" name="threshold_value_2" id="thresh2TypeIn_<?= $uid ?>"
                    <?= $currentField2 !== 'contract_type_id' ? 'disabled' : '' ?>>
              <option value="">(select)</option>
              <?php foreach ($contractTypes as $ct): ?>
                <option value="<?= (int)$ct['contract_type_id'] ?>"
                  <?= ((int)($rule['threshold_value_2'] ?? 0) === (int)$ct['contract_type_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ct['contract_type'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active_<?= $uid ?>"
            <?= ((int)($rule['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active_<?= $uid ?>">Active</label>
        </div>
      </div>

      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="waived_by_standard_contract"
                 id="waived_<?= $uid ?>"
            <?= !empty($rule['waived_by_standard_contract']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="waived_<?= $uid ?>">
            Waived if "Use Standard Contract" is checked
          </label>
        </div>
      </div>

      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="waived_by_min_insurance"
                 id="waived_ins_<?= $uid ?>"
            <?= !empty($rule['waived_by_min_insurance']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="waived_ins_<?= $uid ?>">
            Waived if "COI ≥$5M" is checked
          </label>
        </div>
      </div>

    </div>
    <script>
    function toggleThreshold_<?= $uid ?>(field) {
        var numDiv  = document.getElementById('threshNum_<?= $uid ?>');
        var typeDiv = document.getElementById('threshType_<?= $uid ?>');
        var numIn   = document.getElementById('threshNumIn_<?= $uid ?>');
        var typeIn  = document.getElementById('threshTypeIn_<?= $uid ?>');
        if (field === 'contract_type_id') {
            numDiv.style.display  = 'none';  numIn.disabled  = true;  numIn.required  = false;
            typeDiv.style.display = '';      typeIn.disabled = false;
        } else {
            numDiv.style.display  = '';      numIn.disabled  = false; numIn.required  = true;
            typeDiv.style.display = 'none'; typeIn.disabled = true;
        }
    }
    function toggleThreshold2_<?= $uid ?>(field) {
        var numDiv  = document.getElementById('thresh2Num_<?= $uid ?>');
        var typeDiv = document.getElementById('thresh2Type_<?= $uid ?>');
        var numIn   = document.getElementById('thresh2NumIn_<?= $uid ?>');
        var typeIn  = document.getElementById('thresh2TypeIn_<?= $uid ?>');
        if (!numDiv) return;
        if (field === 'contract_type_id') {
            numDiv.style.display  = 'none';  if (numIn) { numIn.disabled = true; }
            typeDiv.style.display = '';      if (typeIn) { typeIn.disabled = false; }
        } else if (field === '') {
            numDiv.style.display  = 'none';  if (numIn) { numIn.disabled = true; }
            typeDiv.style.display = 'none';  if (typeIn) { typeIn.disabled = true; }
        } else {
            numDiv.style.display  = '';      if (numIn) { numIn.disabled = false; }
            typeDiv.style.display = 'none';  if (typeIn) { typeIn.disabled = true; }
        }
    }
    </script>
    <?php
    return ob_get_clean();
}
?>

<script>
function openEditModal(rule) {
    var form = document.getElementById('editRuleForm');
    form.action = '/index.php?page=approval_rules_update&rule_id=' + rule.rule_id;

    var body = document.getElementById('editRuleBody');

    var fieldOptions    = <?= json_encode(ApprovalRulesController::FIELD_OPTIONS) ?>;
    var approvalLabels  = <?= json_encode(ApprovalRulesController::APPROVAL_LABELS) ?>;
    var operators       = <?= json_encode(ApprovalRulesController::OPERATORS) ?>;
    var contractTypes   = <?= json_encode(array_values($contractTypes ?? [])) ?>;

    var isTypeField = (rule.contract_field === 'contract_type_id');

    var html = '<div class="row g-3">';

    html += '<div class="col-12"><label class="form-label">Rule Name <span class="text-danger">*</span></label>'
          + '<input class="form-control" name="rule_name" value="' + escHtml(rule.rule_name) + '" required></div>';

    // contract_field select
    html += '<div class="col-md-4"><label class="form-label">Contract Field</label>'
          + '<select class="form-select" name="contract_field" onchange="editModalToggleThreshold(this.value)">';
    for (var k in fieldOptions) {
        html += '<option value="' + k + '"' + (rule.contract_field === k ? ' selected' : '') + '>' + escHtml(fieldOptions[k]) + '</option>';
    }
    html += '</select></div>';

    // operator select
    html += '<div class="col-md-2"><label class="form-label">Operator</label><select class="form-select" name="operator">';
    for (var op in operators) {
        html += '<option value="' + op + '"' + (rule.operator === op ? ' selected' : '') + '>' + op + '</option>';
    }
    html += '</select></div>';

    // Numeric threshold
    html += '<div class="col-md-3" id="editThreshNum" style="' + (isTypeField ? 'display:none' : '') + '">'
          + '<label class="form-label">Threshold</label>'
          + '<input class="form-control" name="threshold_value" id="editThreshNumIn" type="number" step="0.01"'
          + ' value="' + (isTypeField ? '' : escHtml(rule.threshold_value)) + '"'
          + (isTypeField ? ' disabled' : ' required') + '></div>';

    // Contract-type select
    html += '<div class="col-md-3" id="editThreshType" style="' + (isTypeField ? '' : 'display:none') + '">'
          + '<label class="form-label">Contract Type</label>'
          + '<select class="form-select" name="threshold_value" id="editThreshTypeIn"'
          + (isTypeField ? '' : ' disabled') + '>';
    contractTypes.forEach(function(ct) {
        html += '<option value="' + ct.contract_type_id + '"'
              + (parseInt(rule.threshold_value) === ct.contract_type_id ? ' selected' : '') + '>'
              + escHtml(ct.contract_type) + '</option>';
    });
    html += '</select></div>';

    // required_approval select
    html += '<div class="col-md-3"><label class="form-label">Required Approval</label><select class="form-select" name="required_approval">';
    for (var a in approvalLabels) {
        html += '<option value="' + a + '"' + (rule.required_approval === a ? ' selected' : '') + '>' + escHtml(approvalLabels[a]) + '</option>';
    }
    html += '</select></div>';

    html += '<div class="col-md-2"><label class="form-label">Sort Order</label>'
          + '<input class="form-control" name="sort_order" type="number" value="' + escHtml(rule.sort_order) + '"></div>';

    // ── Second condition (AND) ──────────────────────────────────────────────
    var field2    = rule.contract_field_2 || '';
    var isType2   = (field2 === 'contract_type_id');
    var thresh2Val = rule.threshold_value_2 || '';

    html += '<div class="col-12"><hr class="my-1"><div class="text-muted small mb-2">Optional: Add a second condition (AND logic)</div>'
          + '<div class="row g-3">';

    html += '<div class="col-md-4"><label class="form-label">AND Field</label>'
          + '<select class="form-select" name="contract_field_2" onchange="editModalToggleThreshold2(this.value)">'
          + '<option value="">(none)</option>';
    for (var k2 in fieldOptions) {
        html += '<option value="' + k2 + '"' + (field2 === k2 ? ' selected' : '') + '>' + escHtml(fieldOptions[k2]) + '</option>';
    }
    html += '</select></div>';

    html += '<div class="col-md-2"><label class="form-label">Operator</label><select class="form-select" name="operator_2">';
    for (var op2 in operators) {
        html += '<option value="' + op2 + '"' + ((rule.operator_2 || '') === op2 ? ' selected' : '') + '>' + op2 + '</option>';
    }
    html += '</select></div>';

    html += '<div class="col-md-3" id="editThresh2Num" style="' + (isType2 || !field2 ? 'display:none' : '') + '">'
          + '<label class="form-label">Threshold</label>'
          + '<input class="form-control" name="threshold_value_2" id="editThresh2NumIn" type="number" step="0.01"'
          + ' value="' + (isType2 ? '' : escHtml(thresh2Val)) + '"></div>';

    html += '<div class="col-md-3" id="editThresh2Type" style="' + (isType2 ? '' : 'display:none') + '">'
          + '<label class="form-label">Contract Type</label>'
          + '<select class="form-select" name="threshold_value_2" id="editThresh2TypeIn"' + (isType2 ? '' : ' disabled') + '>'
          + '<option value="">(select)</option>';
    contractTypes.forEach(function(ct) {
        html += '<option value="' + ct.contract_type_id + '"'
              + (parseInt(thresh2Val) === ct.contract_type_id ? ' selected' : '') + '>'
              + escHtml(ct.contract_type) + '</option>';
    });
    html += '</select></div>';

    html += '</div></div>'; // close row g-3 + col-12

    html += '<div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2">'
          + '<input class="form-check-input" type="checkbox" name="is_active"' + (parseInt(rule.is_active) === 1 ? ' checked' : '') + '>'
          + '<label class="form-check-label">Active</label></div></div>';

    html += '<div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2">'
          + '<input class="form-check-input" type="checkbox" name="waived_by_standard_contract"'
          + (parseInt(rule.waived_by_standard_contract) === 1 ? ' checked' : '') + '>'
          + '<label class="form-check-label">Waived if &ldquo;Use Standard Contract&rdquo; is checked</label>'
          + '</div></div>';

    html += '<div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2">'
          + '<input class="form-check-input" type="checkbox" name="waived_by_min_insurance"'
          + (parseInt(rule.waived_by_min_insurance) === 1 ? ' checked' : '') + '>'
          + '<label class="form-check-label">Waived if &ldquo;COI &ge;$5M&rdquo; is checked</label>'
          + '</div></div>';

    html += '</div>';

    body.innerHTML = html;

    new bootstrap.Modal(document.getElementById('editRuleModal')).show();
}

function editModalToggleThreshold(field) {
    var numDiv  = document.getElementById('editThreshNum');
    var typeDiv = document.getElementById('editThreshType');
    var numIn   = document.getElementById('editThreshNumIn');
    var typeIn  = document.getElementById('editThreshTypeIn');
    if (!numDiv || !typeDiv) return;
    if (field === 'contract_type_id') {
        numDiv.style.display  = 'none';  numIn.disabled  = true;  numIn.required  = false;
        typeDiv.style.display = '';      typeIn.disabled = false;
    } else {
        numDiv.style.display  = '';      numIn.disabled  = false; numIn.required  = true;
        typeDiv.style.display = 'none'; typeIn.disabled = true;
    }
}

function editModalToggleThreshold2(field) {
    var numDiv  = document.getElementById('editThresh2Num');
    var typeDiv = document.getElementById('editThresh2Type');
    var numIn   = document.getElementById('editThresh2NumIn');
    var typeIn  = document.getElementById('editThresh2TypeIn');
    if (!numDiv) return;
    if (field === 'contract_type_id') {
        numDiv.style.display  = 'none';  if (numIn) numIn.disabled = true;
        typeDiv.style.display = '';      if (typeIn) typeIn.disabled = false;
    } else if (field === '') {
        numDiv.style.display  = 'none';  if (numIn) numIn.disabled = true;
        typeDiv.style.display = 'none';  if (typeIn) typeIn.disabled = true;
    } else {
        numDiv.style.display  = '';      if (numIn) numIn.disabled = false;
        typeDiv.style.display = 'none';  if (typeIn) typeIn.disabled = true;
    }
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>
