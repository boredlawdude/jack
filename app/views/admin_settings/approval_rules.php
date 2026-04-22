<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$fieldOptions    = ApprovalRulesController::FIELD_OPTIONS;
$approvalLabels  = ApprovalRulesController::APPROVAL_LABELS;
$operators       = ApprovalRulesController::OPERATORS;
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
                  <?= h(number_format((float)$rule['threshold_value'], 0)) ?>
                  <?= $rule['contract_field'] === 'total_contract_value' ? '<span class="text-muted small">(USD)</span>' : '' ?>
                </td>
                <td>
                  <span class="badge text-bg-primary"><?= h($approvalLabels[$rule['required_approval']] ?? $rule['required_approval']) ?></span>
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
        <?= _approval_rule_fields(null, $fieldOptions, $approvalLabels, $operators) ?>
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
function _approval_rule_fields(?array $rule, array $fieldOptions, array $approvalLabels, array $operators): string
{
    ob_start();
    $v = fn($k) => htmlspecialchars((string)($rule[$k] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="row g-3">

      <div class="col-12">
        <label class="form-label">Rule Name <span class="text-danger">*</span></label>
        <input class="form-control" name="rule_name" value="<?= $v('rule_name') ?>" required
               placeholder="e.g. Manager approval over $30k">
      </div>

      <div class="col-md-4">
        <label class="form-label">Contract Field <span class="text-danger">*</span></label>
        <select class="form-select" name="contract_field" required>
          <?php foreach ($fieldOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
              <?= ($rule['contract_field'] ?? '') === $key ? 'selected' : '' ?>>
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

      <div class="col-md-3">
        <label class="form-label">Threshold Value <span class="text-danger">*</span></label>
        <input class="form-control" name="threshold_value" type="number" step="0.01"
               value="<?= $v('threshold_value') ?>" required placeholder="e.g. 30000">
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

      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active_<?= uniqid() ?>"
            <?= ((int)($rule['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label">Active</label>
        </div>
      </div>

    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
function openEditModal(rule) {
    var form = document.getElementById('editRuleForm');
    form.action = '/index.php?page=approval_rules_update&rule_id=' + rule.rule_id;

    // Build the field HTML server-side equivalent via a fetch or just reload with pre-fill.
    // Instead, populate the modal body with a clone of the add-form fields filled via JS.
    var body = document.getElementById('editRuleBody');

    var fieldOptions    = <?= json_encode(ApprovalRulesController::FIELD_OPTIONS) ?>;
    var approvalLabels  = <?= json_encode(ApprovalRulesController::APPROVAL_LABELS) ?>;
    var operators       = <?= json_encode(ApprovalRulesController::OPERATORS) ?>;

    var html = '<div class="row g-3">';

    html += '<div class="col-12"><label class="form-label">Rule Name <span class="text-danger">*</span></label>'
          + '<input class="form-control" name="rule_name" value="' + escHtml(rule.rule_name) + '" required></div>';

    // contract_field select
    html += '<div class="col-md-4"><label class="form-label">Contract Field</label><select class="form-select" name="contract_field">';
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

    html += '<div class="col-md-3"><label class="form-label">Threshold</label>'
          + '<input class="form-control" name="threshold_value" type="number" step="0.01" value="' + escHtml(rule.threshold_value) + '" required></div>';

    // required_approval select
    html += '<div class="col-md-3"><label class="form-label">Required Approval</label><select class="form-select" name="required_approval">';
    for (var a in approvalLabels) {
        html += '<option value="' + a + '"' + (rule.required_approval === a ? ' selected' : '') + '>' + escHtml(approvalLabels[a]) + '</option>';
    }
    html += '</select></div>';

    html += '<div class="col-md-2"><label class="form-label">Sort Order</label>'
          + '<input class="form-control" name="sort_order" type="number" value="' + escHtml(rule.sort_order) + '"></div>';

    html += '<div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2">'
          + '<input class="form-check-input" type="checkbox" name="is_active"' + (parseInt(rule.is_active) === 1 ? ' checked' : '') + '>'
          + '<label class="form-check-label">Active</label></div></div>';

    html += '</div>';

    body.innerHTML = html;

    new bootstrap.Modal(document.getElementById('editRuleModal')).show();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>
