<?php
declare(strict_types=1);

// Public page — no login required

// Handle AJAX evaluate POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $goodsOnly          = !empty($input['goods_only']);
    $under30k           = !empty($input['under_30000']);
    $installOnProperty  = !empty($input['install_on_property']);
    $constructionRepair = !empty($input['construction_repair']);
    $biddingRequired    = !empty($input['bidding_required']);
    $professionalSvc    = !empty($input['professional_services']);
    $bidCompleted       = !empty($input['bid_completed']);

    $result = determineOutcome(
        $goodsOnly, $under30k, $installOnProperty,
        $constructionRepair, $biddingRequired, $professionalSvc, $bidCompleted
    );

    echo json_encode($result);
    exit;
}

function determineOutcome(
    bool $goodsOnly, bool $under30k, bool $installOnProperty,
    bool $constructionRepair, bool $biddingRequired,
    bool $professionalServices, bool $bidCompleted
): array {
    if ($professionalServices) {
        if ($bidCompleted) {
            return [
                'status'   => 'proceed_to_contract',
                'message'  => 'Procurement/QBS process appears complete. You may proceed to log in and create a contract.',
                'next_url' => '/login.php?next=' . urlencode('/index.php?page=contracts_create'),
            ];
        }
        return [
            'status'   => 'procurement_review',
            'message'  => 'This appears to be a professional services procurement and should be routed through Procurement first.',
            'next_url' => null,
        ];
    }

    if ($goodsOnly) {
        if ($under30k && !$installOnProperty) {
            return [
                'status'   => 'requisition_only',
                'message'  => 'This appears to be a simple goods/supplies purchase under $30,000 with no installation or work on Town property. Use a requisition and PO — the Contracts Database is not needed.',
                'next_url' => null,
            ];
        }
        if ($biddingRequired && !$bidCompleted) {
            return [
                'status'   => 'procurement_review',
                'message'  => 'Bidding appears to be required. Complete the procurement process first, then return here if a contract is needed.',
                'next_url' => null,
            ];
        }
        return [
            'status'   => 'proceed_to_contract',
            'message'  => 'You may proceed to log in and create a contract.',
            'next_url' => '/login.php?next=' . urlencode('/index.php?page=contracts_create'),
        ];
    }

    if ($constructionRepair) {
        if ($biddingRequired && !$bidCompleted) {
            return [
                'status'   => 'procurement_review',
                'message'  => 'Construction/repair work requiring bidding must go through Procurement first.',
                'next_url' => null,
            ];
        }
        return [
            'status'   => 'proceed_to_contract',
            'message'  => 'You may proceed to log in and create a contract.',
            'next_url' => '/login.php?next=' . urlencode('/index.php?page=contracts_create'),
        ];
    }

    if ($biddingRequired && !$bidCompleted) {
        return [
            'status'   => 'procurement_review',
            'message'  => 'Complete the required procurement/bid process first.',
            'next_url' => null,
        ];
    }

    return [
        'status'   => 'proceed_to_contract',
        'message'  => 'You may proceed to log in and create a contract.',
        'next_url' => '/login.php?next=' . urlencode('/index.php?page=contracts_create'),
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Do I Need a Contract? &mdash; Town of Holly Springs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width:720px;">
    <div class="text-center mb-4">
        <h1 class="h3 fw-bold">Before You Start a Contract</h1>
        <p class="text-muted">Answer a few questions to determine the right path for your request.</p>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> Simple purchases of goods/supplies under $30,000 may not require a contract.
        If installation, construction, repair, or work on Town property is involved, a contract may still be needed.
        If formal or informal bidding is required, complete that process first.
    </div>

    <form id="gateForm" class="card shadow-sm">
        <div class="card-body">

            <div class="mb-3">
                <label class="form-label fw-semibold">Is this only a purchase of goods or supplies?</label>
                <select name="goods_only" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Is the total amount under $30,000?</label>
                <select name="under_30000" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Will the vendor install, assemble, repair, configure, or perform work on Town property?</label>
                <select name="install_on_property" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Is this construction or repair work?</label>
                <select name="construction_repair" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Does this require formal or informal bidding, RFQ, RFP, or Procurement review?</label>
                <select name="bidding_required" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Is this architectural, engineering, surveying, design-build, CM-at-risk, or similar professional service?</label>
                <select name="professional_services" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">If Procurement was required, has that process already been completed?</label>
                <select name="bid_completed" class="form-select">
                    <option value="">-- Select --</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Evaluate My Request</button>
        </div>
    </form>

    <div id="gateResult" class="mt-4"></div>

    <div class="text-center mt-4">
        <a href="/login.php" class="text-muted small">Bypass to Contracts DB</a>
    </div>
</div>

<script>
document.getElementById('gateForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const payload = {
        goods_only:           formData.get('goods_only') === '1',
        under_30000:          formData.get('under_30000') === '1',
        install_on_property:  formData.get('install_on_property') === '1',
        construction_repair:  formData.get('construction_repair') === '1',
        bidding_required:     formData.get('bidding_required') === '1',
        professional_services: formData.get('professional_services') === '1',
        bid_completed:        formData.get('bid_completed') === '1'
    };

    const response = await fetch('procurement_gate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await response.json();
    const result = document.getElementById('gateResult');

    let alertClass = 'alert-secondary';
    if (data.status === 'requisition_only')    alertClass = 'alert-success';
    if (data.status === 'procurement_review')  alertClass = 'alert-warning';
    if (data.status === 'proceed_to_contract') alertClass = 'alert-primary';

    const button = data.next_url
        ? `<a href="${data.next_url}" class="btn btn-primary mt-2">Continue &rarr;</a>`
        : '';

    result.innerHTML = `
        <div class="alert ${alertClass}">
            <h5 class="alert-heading">Result</h5>
            <p class="mb-0">${data.message}</p>
            ${button}
        </div>
    `;

    result.scrollIntoView({ behavior: 'smooth' });
});
</script>
</body>
</html>
