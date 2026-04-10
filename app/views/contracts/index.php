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

    <a href="/index.php?page=contracts_create" class="btn btn-primary">
        + New Contract
    </a>
</div>

<form method="get" action="/index.php" class="card shadow-sm mb-3">
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

            <div class="col-md-2">
                <select class="form-select" name="contract_status_id">
                    <option value="">All Status</option>
                    <?php foreach (($contractStatuses ?? []) as $status): ?>
                        <option value="<?= (int)$status['contract_status_id'] ?>" <?= ((string)($_GET['contract_status_id'] ?? '') === (string)$status['contract_status_id']) ? 'selected' : '' ?>>
                            <?= h($status['contract_status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span class="fw-semibold">Contract List</span>
        <div class="d-flex gap-2" id="contractListActions">
            <a href="#" id="btnView" class="btn btn-sm btn-outline-secondary disabled">View</a>
            <a href="#" id="btnEdit" class="btn btn-sm btn-outline-primary disabled">Edit</a>
            <button type="button" id="btnDelete" class="btn btn-sm btn-outline-danger disabled">Delete</button>
        </div>
    </div>

    <div class="card-body p-0">

        <?php if (empty($contracts)): ?>
            <div class="p-3 text-muted">No contracts found.</div>
        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle" id="contractsListTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="form-check-input" id="selectAllContracts">
                            </th>
                            <th>Contract #</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Responsible</th>
                            <th>Value</th>
                            <th>Comment</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($contracts as $c): ?>
                        <tr data-contract-id="<?= (int)$c['contract_id'] ?>"
                            data-view-url="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>"
                            data-edit-url="/index.php?page=contracts_edit&contract_id=<?= (int)$c['contract_id'] ?>"
                            data-delete-url="/index.php?page=contracts_delete&contract_id=<?= (int)$c['contract_id'] ?>">
                            <td>
                                <input type="checkbox" class="form-check-input contract-row-check"
                                       value="<?= (int)$c['contract_id'] ?>">
                            </td>
                            <td><?= h($c['contract_number'] ?? '') ?></td>

                            <td class="fw-semibold">
                                <?= h($c['name'] ?? '') ?>
                            </td>

                            <td>
                                <span class="badge text-bg-<?= status_badge($c['status_name'] ?? '') ?>">
                                    <?= h($c['status_name'] ?? '') ?>
                                </span>
                            </td>

                            <td><?= h($c['department_name'] ?? '') ?></td>

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

        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const selectAll = document.getElementById('selectAllContracts');
    const btnView   = document.getElementById('btnView');
    const btnEdit   = document.getElementById('btnEdit');
    const btnDelete = document.getElementById('btnDelete');

    function getChecked() {
        return Array.from(document.querySelectorAll('.contract-row-check:checked'));
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

    document.querySelectorAll('.contract-row-check').forEach(cb => {
        cb.addEventListener('change', function () {
            // Deselect others (single-select behavior for View/Edit; multi allowed for Delete)
            if (this.checked) {
                document.querySelectorAll('.contract-row-check').forEach(o => {
                    if (o !== this) o.checked = false;
                });
            }
            updateButtons();
        });
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.contract-row-check').forEach(cb => {
                cb.checked = false;
            });
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
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>