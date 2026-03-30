<?php
declare(strict_types=1);
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
$contractId = (int)($contractId ?? $_GET['contract_id'] ?? 0);
?>
<div class="container mt-4" style="max-width: 600px;">
    <h2 class="h5 mb-3">Upload Document for Contract #<?= $contractId ?></h2>

    <form method="post" enctype="multipart/form-data" action="/index.php?page=contract_documents_store">
        <input type="hidden" name="contract_id" value="<?= $contractId ?>">

        <!-- Document Category -->
        <div class="mb-3">
            <label class="form-label">Document Category</label>
            <select id="doc_category" name="doc_category" class="form-select" required onchange="toggleExhibitFields()">
                <option value="">— Select —</option>
                <option value="revised_vendor">Revised by Vendor</option>
                <option value="revised_internal">Revised Internally</option>
                <option value="exhibit">Exhibit</option>
            </select>
        </div>

        <!-- Exhibit-specific fields (hidden by default) -->
        <div id="exhibit_fields" style="display:none;">
            <div class="mb-3">
                <label for="exhibit_letter" class="form-label">Exhibit Letter</label>
                <select id="exhibit_letter" name="exhibit_letter" class="form-select">
                    <option value="">— Select —</option>
                    <?php foreach (range('A', 'Z') as $letter): ?>
                        <option value="<?= $letter ?>"><?= $letter ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Exhibit Description</label>
                <input type="text" class="form-control" id="description" name="description" maxlength="255" placeholder="e.g. Scope of Work">
            </div>
        </div>

        <!-- File upload -->
        <div class="mb-3">
            <label for="file_upload" class="form-label">Select File</label>
            <input type="file" class="form-control" id="file_upload" name="file_upload" required>
        </div>

        <button type="submit" class="btn btn-primary">Upload</button>
        <a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>

<script>
function toggleExhibitFields() {
    var cat = document.getElementById('doc_category').value;
    document.getElementById('exhibit_fields').style.display = (cat === 'exhibit') ? '' : 'none';
    document.getElementById('exhibit_letter').required = (cat === 'exhibit');
}
</script>
