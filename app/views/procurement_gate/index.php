<?php
declare(strict_types=1);

$gateConfig = $GLOBALS['gateConfig'] ?? [];
?>

<div class="container py-4">
    <h1 class="mb-3">Before You Start a Contract</h1>

    <div class="alert alert-warning">
        This screening tool helps determine whether the request should go to
        requisition/PO only, Procurement review, or the Contracts Database.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Important:</strong></p>
            <ul>
                <li>Simple purchases of goods/supplies under $30,000 may not require contract routing.</li>
                <li>If installation, construction, repair, or work on Town property is involved, a contract may still be needed.</li>
                <li>If formal or informal bidding is required, complete that process first.</li>
                <li>After bidding is complete, you may return and proceed into the Contracts Database if a contract is needed.</li>
            </ul>
        </div>
    </div>

    <form id="gateForm" class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Is this only a purchase of goods or supplies?</label>
                <select name="goods_only" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Is the total amount under $30,000?</label>
                <select name="under_30000" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Will the vendor install, assemble, repair, configure, or perform work on Town property?</label>
                <select name="install_on_property" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Is this construction or repair work?</label>
                <select name="construction_repair" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Does this require formal or informal bidding, RFQ, RFP, or Procurement review?</label>
                <select name="bidding_required" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Is this architectural, engineering, surveying, design-build, CM-at-risk, or similar professional service?</label>
                <select name="professional_services" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">If Procurement was required, has that process already been completed?</label>
                <select name="bid_completed" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Evaluate</button>
        </div>
    </form>

    <div id="gateResult" class="mt-4"></div>
</div>

<script>
document.getElementById('gateForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    const payload = {
        goods_only: formData.get('goods_only') === '1',
        under_30000: formData.get('under_30000') === '1',
        install_on_property: formData.get('install_on_property') === '1',
        construction_repair: formData.get('construction_repair') === '1',
        bidding_required: formData.get('bidding_required') === '1',
        professional_services: formData.get('professional_services') === '1',
        bid_completed: formData.get('bid_completed') === '1'
    };

    const response = await fetch('index.php?page=procurement_gate_evaluate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await response.json();
    const result = document.getElementById('gateResult');

    let alertClass = 'alert-info';
    if (data.status === 'requisition_only') alertClass = 'alert-success';
    if (data.status === 'procurement_review') alertClass = 'alert-warning';
    if (data.status === 'proceed_to_contract') alertClass = 'alert-primary';

    result.innerHTML = `
        <div class="alert ${alertClass}">
            <h4 class="alert-heading">Routing Result</h4>
            <p>${data.message}</p>
            <a href="${data.next_url}" class="btn btn-outline-dark">Continue</a>
        </div>
    `;
});
</script>