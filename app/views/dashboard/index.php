<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function dashboard_status_badge(string $status): string {
    return match (strtolower($status)) {
        'draft'             => 'secondary',
        'negotiate'         => 'info',
        'legal review'      => 'warning',
        'dept head review'  => 'primary',
        'manager review'    => 'primary',
        'town council'      => 'info',
        'out for signature' => 'warning',
        'executed'          => 'success',
        default             => 'light',
    };
}

$userName = h($person['name'] ?? $person['email'] ?? 'Unknown User');
?>

<!-- ── User Card ──────────────────────────────────────────────────────────── -->
<div class="row mb-4 align-items-stretch">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-start gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:52px;height:52px;font-size:1.4rem;font-weight:600;">
                    <?= mb_strtoupper(mb_substr($person['name'] ?? $person['email'] ?? '?', 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold fs-5"><?= $userName ?></div>
                    <?php if (!empty($userRoles)): ?>
                        <?php foreach ($userRoles as $r): ?>
                            <div class="mt-1">
                                <span class="badge text-bg-secondary text-uppercase" style="font-size:.75rem;letter-spacing:.04em;">
                                    <?= h($r['role_name']) ?>
                                </span>
                                <?php if (!empty($r['description'])): ?>
                                    <span class="text-muted small fst-italic ms-1">
                                        &mdash; your job in contract management is to <?= h($r['description']) ?>.
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">No Role</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4 mt-3 mt-lg-0">
        <div class="card shadow-sm border-warning h-100">
            <div class="card-header bg-warning text-dark fw-semibold py-2">
                &#9888; Delay Warning
            </div>
            <div class="card-body">
                <p class="mb-2 small">
                    <span class="fw-semibold text-danger">&#9888;</span>
                    There are <strong><?= $staleCount ?></strong> contract<?= $staleCount !== 1 ? 's' : '' ?> that have been in drafting or negotiation for more than 5 days.
                    <?php if ($staleCount > 0): ?>
                        <span class="text-muted">(highlighted in red below)</span>
                    <?php endif; ?>
                </p>
                <p class="mb-2 small">
                    <span class="fw-semibold text-warning">&#9888;</span>
                    There are <strong><?= $pendingCount ?></strong> contract<?= $pendingCount !== 1 ? 's' : '' ?> still pending execution.
                </p>
                <p class="mb-2 small">
                    <span class="fw-semibold text-info">&#9432;</span>
                    There are <strong><?= $reviewCount ?></strong> contract<?= $reviewCount !== 1 ? 's' : '' ?> currently in the Review Phase.
                </p>
                <p class="mb-2 small">
                    <span class="fw-semibold text-primary">&#9432;</span>
                    There are <strong><?= $townCouncilCount ?></strong> contract<?= $townCouncilCount !== 1 ? 's' : '' ?> waiting for Town Council approval.
                </p>
                <p class="mb-0 small">
                    <span class="fw-semibold text-secondary">&#9432;</span>
                    There are <strong><?= $outForSignatureCount ?></strong> contract<?= $outForSignatureCount !== 1 ? 's' : '' ?> currently out for signature.
                </p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($myPendingApprovals)): ?>
<!-- ── My Pending Approvals ──────────────────────────────────────────────── -->
<div class="card shadow-sm border-danger mb-4">
    <div class="card-header bg-danger text-white fw-semibold py-2">
        &#9888; My Pending Approvals
    </div>
    <div class="card-body py-2 px-3">
        <p class="text-muted small mb-2">Contracts awaiting your approval based on your role(s):</p>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($myPendingApprovals as $pa): ?>
                <a href="/index.php?page=contracts&pending_approval=<?= h($pa['key']) ?>"
                   class="text-decoration-none">
                    <div class="border rounded px-3 py-2 text-center" style="min-width:120px;">
                        <div class="fs-3 fw-bold text-danger"><?= $pa['count'] ?></div>
                        <div class="small text-muted"><?= h($pa['label']) ?> Approval</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Status Radio Filter ───────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center" id="statusFilters">
            <strong class="me-1 text-nowrap">Filter by Status:</strong>

            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input status-radio" type="radio"
                       name="dashStatusFilter" id="status_all" value="" checked>
                <label class="form-check-label" for="status_all">All</label>
            </div>

            <?php foreach ($statuses as $st): ?>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input status-radio" type="radio"
                           name="dashStatusFilter"
                           id="status_<?= (int)$st['contract_status_id'] ?>"
                           value="<?= (int)$st['contract_status_id'] ?>">
                    <label class="form-check-label" for="status_<?= (int)$st['contract_status_id'] ?>">
                        <?= h($st['contract_status_name']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Contracts Table ───────────────────────────────────────────────────── -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex gap-2" id="dashContractsActions">
            <a href="#" id="dashBtnView" class="btn btn-sm btn-outline-secondary disabled">View</a>
            <a href="#" id="dashBtnEdit" class="btn btn-sm btn-outline-primary disabled">Edit</a>
            <button type="button" id="dashBtnDelete" class="btn btn-sm btn-outline-danger disabled">Delete</button>
        </div>
        <span class="fw-semibold">Contracts</span>
        <a href="/index.php?page=contracts_create" class="btn btn-sm btn-primary">+ New Contract</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0 align-middle small" id="dashContractsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:32px;"><input type="checkbox" id="dashSelectAll" class="form-check-input"></th>
                        <th style="width:180px;">Contract #</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:320px;">Name</th>
                        <th style="width:55px;">Dept</th>
                        <th style="width:90px;">Responsible</th>
                        <th style="width:75px;">Value</th>
                        <th>Comment</th>
                        <th style="width:0;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contracts as $c): ?>
                    <?php $isStale = isset($staleIds[(int)($c['contract_id'] ?? 0)]); ?>
                    <tr data-status-id="<?= (int)($c['contract_status_id'] ?? 0) ?>"
                        data-contract-id="<?= (int)$c['contract_id'] ?>"
                        data-view-url="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>"
                        data-edit-url="/index.php?page=contracts_edit&contract_id=<?= (int)$c['contract_id'] ?>"
                        data-delete-url="/index.php?page=contracts_delete&contract_id=<?= (int)$c['contract_id'] ?>">
                        <td><input type="checkbox" class="form-check-input dash-row-check" value="<?= (int)$c['contract_id'] ?>"></td>
                        <td><a href="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>" class="text-decoration-underline fw-semibold"><?= h($c['contract_number'] ?? '') ?></a></td>
                        <td><span class="badge text-bg-<?= dashboard_status_badge($c['status_name'] ?? '') ?>"><?= h($c['status_name'] ?? '') ?></span></td>
                        <td class="<?= $isStale ? 'text-danger' : '' ?>">
                            <?php if (!empty($c['counterparty_company_name'])): ?>
                                <small class="text-muted d-block"><?= h($c['counterparty_company_name']) ?></small>
                            <?php endif; ?>
                            <span title="<?= h($c['name'] ?? '') ?>"><?= h(mb_strlen($c['name'] ?? '') > 70 ? mb_substr($c['name'], 0, 70) . '…' : ($c['name'] ?? '')) ?></span>
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
        <div id="dashNoResults" class="p-3 text-muted d-none">No contracts match the selected status.</div>
    </div>
</div>

<script>
(function () {
    const checks = document.querySelectorAll('.dash-row-check');
    const selectAll = document.getElementById('dashSelectAll');
    const btnView = document.getElementById('dashBtnView');
    const btnEdit = document.getElementById('dashBtnEdit');
    const btnDelete = document.getElementById('dashBtnDelete');
    function getChecked() {
        return Array.from(document.querySelectorAll('.dash-row-check:checked'));
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
        checked.forEach(cb => {
            const row = cb.closest('tr');
            const form = document.createElement('form');
            form.method = 'post';
            form.action = row.dataset.deleteUrl;
            document.body.appendChild(form);
            form.submit();
        });
    });
    updateButtons();
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
    makeResizable(document.getElementById('dashContractsTable'));
})();

// ── Status radio: client-side row filter ─────────────────────────────────
(function () {
    const radios = document.querySelectorAll('.status-radio');
    const noResults = document.getElementById('dashNoResults');

    function filterRows() {
        const selected = document.querySelector('.status-radio:checked');
        const val = selected ? selected.value : '';
        const rows = document.querySelectorAll('#dashContractsTable tbody tr');
        let visible = 0;
        rows.forEach(function (row) {
            const show = val === '' || row.dataset.statusId === val;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
        // Uncheck hidden rows
        document.querySelectorAll('.dash-row-check').forEach(function (cb) {
            if (cb.closest('tr').style.display === 'none') cb.checked = false;
        });
        // Reset header buttons
        document.getElementById('dashBtnView').classList.add('disabled');
        document.getElementById('dashBtnEdit').classList.add('disabled');
        document.getElementById('dashBtnDelete').classList.add('disabled');
        document.getElementById('dashBtnView').href = '#';
        document.getElementById('dashBtnEdit').href = '#';
    }

    radios.forEach(function (r) {
        r.addEventListener('change', filterRows);
    });
    filterRows();
})();
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>
