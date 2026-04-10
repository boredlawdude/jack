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
                    <span class="fw-semibold text-warning">&#9888;</span>
                    There are <strong><?= $pendingCount ?></strong> contract<?= $pendingCount !== 1 ? 's' : '' ?> still pending execution.
                </p>
                <p class="mb-0 small">
                    <span class="fw-semibold text-danger">&#9888;</span>
                    There are <strong><?= $staleCount ?></strong> contract<?= $staleCount !== 1 ? 's' : '' ?> that have been in drafting or negotiation for more than 5 days.
                    <?php if ($staleCount > 0): ?>
                        <span class="text-muted">(highlighted in red below)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

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
        <span class="fw-semibold">Contracts</span>
        <a href="/index.php?page=contracts_create" class="btn btn-sm btn-primary">+ New Contract</a>
    </div>

    <div class="card-body p-0">
        <?php if (empty($contracts)): ?>
            <div class="p-3 text-muted">No contracts found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle" id="dashContractsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Contract #</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Responsible</th>
                            <th>Value</th>
                            <th>End Date</th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contracts as $c): ?>
                        <?php $isStale = isset($staleIds[(int)($c['contract_id'] ?? 0)]); ?>
                        <tr data-status-id="<?= (int)($c['contract_status_id'] ?? 0) ?>">
                            <td><?= h($c['contract_number'] ?? '') ?></td>

                            <td class="fw-semibold<?= $isStale ? ' text-danger' : '' ?>"><?= h($c['name'] ?? '') ?></td>

                            <td>
                                <span class="badge text-bg-<?= dashboard_status_badge($c['status_name'] ?? '') ?>">
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

                            <td><?= h($c['end_date'] ?? '') ?></td>

                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="/index.php?page=contracts_show&contract_id=<?= (int)$c['contract_id'] ?>">
                                    View
                                </a>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/index.php?page=contracts_edit&contract_id=<?= (int)$c['contract_id'] ?>">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="dashNoResults" class="p-3 text-muted d-none">No contracts match the selected status.</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const radios = document.querySelectorAll('.status-radio');
    const tbody  = document.querySelector('#dashContractsTable tbody');
    const noResults = document.getElementById('dashNoResults');

    if (!radios.length || !tbody) return;

    function applyFilter() {
        const selected = document.querySelector('.status-radio:checked');
        const val = selected ? selected.value : '';
        let visible = 0;

        tbody.querySelectorAll('tr').forEach(function (row) {
            const rowStatus = row.dataset.statusId || '';
            const show = (val === '' || rowStatus === val);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (noResults) {
            noResults.classList.toggle('d-none', visible > 0);
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', applyFilter);
    });
})();
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>
