<?php
declare(strict_types=1);

//require APP_ROOT . '/app/views/layouts/header.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>

<style>
/* ── column resize handle ── */
#companiesTable th {
    position: relative;
    white-space: nowrap;
    user-select: none;
}
#companiesTable th .col-resize-handle {
    position: absolute;
    right: 0;
    top: 0;
    width: 6px;
    height: 100%;
    cursor: col-resize;
    z-index: 1;
}
#companiesTable th.col-drag-over {
    outline: 2px dashed #2c5d8a;
    outline-offset: -2px;
}
#companiesTable th[draggable="true"] {
    cursor: grab;
}
</style>

<?php if (!empty($_SESSION['flash_messages'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php foreach ($_SESSION['flash_messages'] as $msg): ?>
        <div><?= h($msg) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash_messages']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_errors'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php foreach ($_SESSION['flash_errors'] as $msg): ?>
        <div><?= h($msg) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash_errors']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="post" action="/index.php?page=companies_bulk_delete" id="companiesBulkForm">

<div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto">Companies</h1>
    <div class="d-flex gap-2 align-items-center">
        <button type="submit" class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display:none;"
                onclick="return confirm('Delete selected companies? This cannot be undone.');">
            Delete Selected
        </button>
        <a class="btn btn-primary btn-sm" href="/index.php?page=companies_create">New Company</a>
        <a class="btn btn-outline-secondary btn-sm" href="/index.php?page=companies_vendor_pdf_import">Import from PDF</a>
    </div>
</div>

<div class="mb-3" style="max-width: 360px;">
    <input type="search" id="companySearch" class="form-control"
           placeholder="Search by company name…" autocomplete="off">
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="companiesTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 36px;">
                        <input type="checkbox" id="selectAllCompanies" title="Select all">
                    </th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>SOS ID</th>
                    <th>Vendor ID</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th style="width: 120px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($companies)): ?>
                    <?php foreach ($companies as $r): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="company_ids[]"
                                       value="<?= (int)$r['company_id'] ?>"
                                       class="company-checkbox">
                            </td>
                            <td><?= (int)$r['company_id'] ?></td>
                            <td>
                                <a href="/index.php?page=companies_show&company_id=<?= (int)$r['company_id'] ?>">
                                    <?= h($r['name'] ?? '') ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($r['sosid'])): ?>
                                    <a href="https://sosnc.gov/online_services/search/by_title/search_Business_Registration"
                                       target="_blank" rel="noopener noreferrer"
                                       title="Search NC SOS Business Registration">
                                        <?= h($r['sosid']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['vendor_id'] ?? '') ?></td>
                            <td><?= h($r['contact_name'] ?? '') ?></td>
                            <td><?= h($r['email'] ?? '') ?></td>
                            <td><?= h($r['phone'] ?? '') ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/index.php?page=companies_edit&company_id=<?= (int)$r['company_id'] ?>">
                                    Edit
                                </a>
                                <a class="btn btn-sm btn-outline-secondary ms-1"
                                   href="/index.php?page=contracts_search&company_id=<?= (int)$r['company_id'] ?>">
                                    Contracts
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-muted p-3">No companies yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</form>

<script>
(function () {
    var table      = document.getElementById('companiesTable');
    var selectAll  = document.getElementById('selectAllCompanies');
    var deleteBtn  = document.getElementById('bulkDeleteBtn');
    var checkboxes = document.querySelectorAll('.company-checkbox');

    /* ── bulk-delete checkbox logic ── */
    function updateDeleteBtn() {
        var anyChecked = Array.from(checkboxes).some(function (cb) { return cb.checked; });
        deleteBtn.style.display = anyChecked ? '' : 'none';
        selectAll.checked = checkboxes.length > 0 && Array.from(checkboxes).every(function (cb) { return cb.checked; });
        selectAll.indeterminate = anyChecked && !selectAll.checked;
    }
    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
        updateDeleteBtn();
    });
    checkboxes.forEach(function (cb) { cb.addEventListener('change', updateDeleteBtn); });

    /* ── column resize ── */
    // Force fixed layout so width assignments take effect
    table.style.tableLayout = 'fixed';
    // Give each column an initial explicit pixel width
    var allHeaders = Array.from(table.querySelectorAll('thead th'));
    allHeaders.forEach(function (th) {
        th.style.width = th.offsetWidth + 'px';
    });

    allHeaders.forEach(function (th) {
        var handle = document.createElement('div');
        handle.className = 'col-resize-handle';
        th.appendChild(handle);

        var startX, startW;
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startX = e.clientX;
            startW = th.offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
        function onMouseMove(e) {
            var newW = Math.max(50, startW + (e.clientX - startX));
            th.style.width = newW + 'px';
        }
        function onMouseUp() {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }
    });

    /* ── column drag-to-reorder ── */
    // Determine index dynamically from the DOM so closures stay accurate
    function colIndexOf(th) {
        return Array.from(table.querySelectorAll('thead th')).indexOf(th);
    }

    var dragSrcTh = null;

    allHeaders.forEach(function (th) {
        th.setAttribute('draggable', 'true');

        th.addEventListener('dragstart', function (e) {
            if (e.target.classList.contains('col-resize-handle')) {
                e.preventDefault();
                return;
            }
            dragSrcTh = th;
            e.dataTransfer.effectAllowed = 'move';
            th.style.opacity = '0.5';
        });

        th.addEventListener('dragover', function (e) {
            if (!dragSrcTh || th === dragSrcTh) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            allHeaders.forEach(function (h) { h.classList.remove('col-drag-over'); });
            th.classList.add('col-drag-over');
        });

        th.addEventListener('dragleave', function () {
            th.classList.remove('col-drag-over');
        });

        th.addEventListener('drop', function (e) {
            e.preventDefault();
            th.classList.remove('col-drag-over');
            if (!dragSrcTh || dragSrcTh === th) return;

            var srcIdx = colIndexOf(dragSrcTh);
            var dstIdx = colIndexOf(th);

            // Reorder cells in every row
            Array.from(table.querySelectorAll('tr')).forEach(function (row) {
                var cells = Array.from(row.children);
                if (cells.length <= Math.max(srcIdx, dstIdx)) return;
                var moving = cells[srcIdx];
                var target = cells[dstIdx];
                if (srcIdx < dstIdx) {
                    row.insertBefore(moving, target.nextSibling);
                } else {
                    row.insertBefore(moving, target);
                }
            });
        });

        th.addEventListener('dragend', function () {
            th.style.opacity = '';
            allHeaders.forEach(function (h) { h.classList.remove('col-drag-over'); });
            dragSrcTh = null;
        });
    });
})();
</script>

<script>
(function () {
    var input = document.getElementById('companySearch');
    var tbody = document.querySelector('#companiesTable tbody');
    input.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
            // Name is in the 3rd td (index 2)
            var name = (row.cells[2] ? row.cells[2].textContent : '').toLowerCase();
            row.style.display = (!q || name.includes(q)) ? '' : 'none';
        });
    });
    // Autofocus on page load
    input.focus();
})();
</script>

<?php require APP_ROOT . '/app/views/layouts/footer.php'; ?>