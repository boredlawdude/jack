<?php
declare(strict_types=1);

//require APP_ROOT . '/app/views/layouts/header.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function status_badge(string $status): string {
    return match (strtolower($status)) {
        'draft' => 'secondary',
        'negotiate' => 'info',
        'legal review' => 'warning',
        'dept head review' => 'primary',
        'manager review' => 'primary',
        'town council' => 'info',
        'out for signature' => 'warning',
        'executed' => 'success',
        default => 'light',
    };
}
?>

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto">Contracts</h1>

    <?php if (!empty($pendingApprovalFilter) && !empty($pendingApprovalLabel)): ?>
        <span class="badge text-bg-danger me-2 fs-6">
            Filtered: <?= h($pendingApprovalLabel) ?> approval pending
        </span>
        <a href="/index.php?page=contracts" class="btn btn-sm btn-outline-secondary me-2">Clear Filter</a>
    <?php endif; ?>

    <a href="/index.php?page=contracts_create" class="btn btn-primary">
        + New Contract
    </a>
</div>

<form method="get" action="/index.php" class="card shadow-sm mb-3" id="contractsFilterForm">
    <input type="hidden" name="page" value="contracts_search">

    <div class="card-body">
        <div class="row g-2">

            <div class="col-md-3">
                <input
                    class="form-control"
                    type="text"
                    name="q"
                    placeholder="Search name or number"
                    value="<?= h($_GET['q'] ?? '') ?>"
                >
            </div>

            <div class="col-md-3">
                <select class="form-select" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach (($departments ?? []) as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= ((string)($_GET['department_id'] ?? '') === (string)$d['department_id']) ? 'selected' : '' ?>>
                            <?= h($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select class="form-select" name="owner_primary_contact_id">
                    <option value="">All Responsible Employees</option>
                    <?php foreach (($responsiblePeople ?? []) as $p): ?>
                        <option value="<?= (int)$p['person_id'] ?>"
                            <?= ((string)($_GET['owner_primary_contact_id'] ?? '') === (string)$p['person_id']) ? 'selected' : '' ?>>
                            <?= h($p['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-outline-primary">Search</button>
            </div>

            <div class="col-md-2 d-grid">
                <a href="/index.php?page=contracts" class="btn btn-outline-secondary">Reset</a>
            </div>

        </div>
    </div>
</form>

<!-- ── Status Radio Filter (client-side, no page reload) ─────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <strong class="me-1 text-nowrap">Filter by Status:</strong>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input contracts-status-radio" type="radio"
                       name="contractsStatusFilter" id="cstatus_all" value="" checked>
                <label class="form-check-label" for="cstatus_all">All</label>
            </div>
            <?php foreach (($contractStatuses ?? []) as $status): ?>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input contracts-status-radio" type="radio"
                           name="contractsStatusFilter"
                           id="cstatus_<?= (int)$status['contract_status_id'] ?>"
                           value="<?= (int)$status['contract_status_id'] ?>">
                    <label class="form-check-label" for="cstatus_<?= (int)$status['contract_status_id'] ?>">
                        <?= h($status['contract_status_name']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex gap-2" id="contractsActions">
            <a href="#" id="contractsBtnView" class="btn btn-sm btn-outline-secondary disabled">View</a>
            <a href="#" id="contractsBtnEdit" class="btn btn-sm btn-outline-primary disabled">Edit</a>
            <button type="button" id="contractsBtnDelete" class="btn btn-sm btn-outline-danger disabled">Delete</button>
        </div>
        <span class="fw-semibold">
            Contracts
            <span class="text-muted fw-normal small ms-1" id="contractsRowCount"></span>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <a href="/index.php?page=contracts" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
            <a href="/index.php?page=contracts_create" class="btn btn-sm btn-primary">+ New Contract</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0 align-middle small" id="contractsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:32px;"><input type="checkbox" id="contractsSelectAll" class="form-check-input"></th>
                        <th style="width:180px;">Contract #</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:420px;">Name</th>
                        <th style="width:55px;">Dept</th>
                        <th style="width:90px;">Responsible</th>
                        <th style="width:75px;">Value</th>
                        <th>Comment</th>
                        <th style="width:0;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contracts as $c): ?>
                    <tr data-status-id="<?= (int)($c['contract_status_id'] ?? 0) ?>"
                        data-contract-id="<?= (int)$c['contract_id'] ?>"
                        data-view-url="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>"
                        data-edit-url="/index.php?page=contracts_edit&contract_id=<?= (int)$c['contract_id'] ?>"
                        data-delete-url="/index.php?page=contracts_delete&contract_id=<?= (int)$c['contract_id'] ?>">
                        <td><input type="checkbox" class="form-check-input contracts-row-check" value="<?= (int)$c['contract_id'] ?>"></td>
                        <td><a href="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>" class="text-decoration-underline fw-semibold"><?= h($c['contract_number'] ?? '') ?></a></td>
                        <td><span class="badge text-bg-<?= status_badge($c['status_name'] ?? '') ?>"><?= h($c['status_name'] ?? '') ?></span></td>
                        <td>
                            <?php if (!empty($c['counterparty_company_name'])): ?>
                                <small class="text-muted d-block"><?= h($c['counterparty_company_name']) ?></small>
                            <?php endif; ?>
                            <span title="<?= h($c['name'] ?? '') ?>"><?= h(mb_strlen($c['name'] ?? '') > 90 ? mb_substr($c['name'], 0, 90) . '…' : ($c['name'] ?? '')) ?></span>
                        </td>
                        <td><span title="<?= h($c['department_name'] ?? '') ?>"><?= h($c['department_code'] ?? $c['department_name'] ?? '') ?></span></td>
                        <td><?= h($c['owner_primary_contact_name'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($c['total_contract_value'])): ?>
                                $<?= number_format((float)$c['total_contract_value'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= h($c['status_comment'] ?? '') ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="contractsNoResults" class="p-3 text-muted d-none">No contracts found.</div>
    </div>
</div>

<script>
(function () {
    const checks = document.querySelectorAll('.contracts-row-check');
    const selectAll = document.getElementById('contractsSelectAll');
    const btnView = document.getElementById('contractsBtnView');
    const btnEdit = document.getElementById('contractsBtnEdit');
    const btnDelete = document.getElementById('contractsBtnDelete');
    function getChecked() {
        return Array.from(document.querySelectorAll('.contracts-row-check:checked'));
    }
    function updateButtons() {
        const checked = getChecked();
        const one = checked.length === 1;
        const any = checked.length > 0;
        if (one) {
            const row = checked[0].closest('tr');
            btnView.href = row.dataset.viewUrl;
            btnEdit.href = row.dataset.editUrl;
        } else {
            btnView.href = '#';
            btnEdit.href = '#';
        }
        btnView.classList.toggle('disabled', !one);
        btnEdit.classList.toggle('disabled', !one);
        btnDelete.classList.toggle('disabled', !any);
    }
    checks.forEach(cb => {
        cb.addEventListener('change', updateButtons);
    });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checks.forEach(cb => { cb.checked = selectAll.checked; });
            updateButtons();
        });
    }
    btnDelete.addEventListener('click', function () {
        const checked = getChecked();
        if (!checked.length) return;
        if (!confirm('Delete ' + checked.length + ' contract(s)?')) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '/index.php?page=contracts_bulk_delete';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'contract_ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    });
    updateButtons();
})();

// ── Status radio: client-side row filter ─────────────────────────────────
(function () {
    const radios = document.querySelectorAll('.contracts-status-radio');
    const noResults = document.getElementById('contractsNoResults');

    function filterRows() {
        const selected = document.querySelector('.contracts-status-radio:checked');
        const val = selected ? selected.value : '';
        const rows = document.querySelectorAll('#contractsTable tbody tr');
        let visible = 0;
        rows.forEach(function (row) {
            const show = val === '' || row.dataset.statusId === val;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
        const countEl = document.getElementById('contractsRowCount');
        if (countEl) countEl.textContent = '(' + visible + ' shown)';
        // Uncheck hidden rows
        document.querySelectorAll('.contracts-row-check').forEach(function (cb) {
            if (cb.closest('tr').style.display === 'none') cb.checked = false;
        });
        // Re-evaluate header buttons
        document.getElementById('contractsBtnView').classList.add('disabled');
        document.getElementById('contractsBtnEdit').classList.add('disabled');
        document.getElementById('contractsBtnDelete').classList.add('disabled');
        document.getElementById('contractsBtnView').href = '#';
        document.getElementById('contractsBtnEdit').href = '#';
    }

    radios.forEach(function (r) {
        r.addEventListener('change', filterRows);
    });
    filterRows();
})();

// ── Draggable column resize (with localStorage persistence) ─────────────
(function () {
    function makeResizable(table) {
        const storageKey = 'colWidths_' + table.id;
        const cols = table.querySelectorAll('thead th');
        table.style.tableLayout = 'fixed';

        // Restore saved widths
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            cols.forEach(function (th, i) {
                if (saved[i]) th.style.width = saved[i];
            });
        } catch(e) {}

        function saveWidths() {
            const widths = {};
            cols.forEach(function (th, i) { widths[i] = th.style.width; });
            try { localStorage.setItem(storageKey, JSON.stringify(widths)); } catch(e) {}
        }

        cols.forEach(function (th) {
            if (th.style.width === '0px' || th.style.width === '0') return;
            th.style.position = 'relative';
            th.style.overflow = 'hidden';
            const handle = document.createElement('div');
            handle.style.cssText = 'position:absolute;right:0;top:0;bottom:0;width:5px;cursor:col-resize;user-select:none;z-index:1;';
            th.appendChild(handle);
            let startX, startW;
            handle.addEventListener('mousedown', function (e) {
                startX = e.pageX;
                startW = th.offsetWidth;
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
                function onMove(e) {
                    const w = Math.max(30, startW + (e.pageX - startX));
                    th.style.width = w + 'px';
                }
                function onUp() {
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    saveWidths();
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                e.preventDefault();
            });
        });
    }
    makeResizable(document.getElementById('contractsTable'));
})();
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>