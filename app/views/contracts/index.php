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
    <div class="card-header fw-semibold">
        Contract List
    </div>

    <div class="card-body p-0">

        <?php if (empty($contracts)): ?>
            <div class="p-3 text-muted">No contracts found.</div>
        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Contract #</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Responsible</th>
                            <th>Value</th>
                            <th>End Date</th>
                            <th style="width:180px;"></th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($contracts as $c): ?>
                        <tr>
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

                                <form method="post"
                                      action="/index.php?page=contracts_delete&contract_id=<?= (int)$c['contract_id'] ?>"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this contract?');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>