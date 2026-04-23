<?php
declare(strict_types=1);
require_once APP_ROOT . '/app/models/ChangeOrder.php';
require_once APP_ROOT . '/app/models/Contract.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class ChangeOrdersController
{
    private PDO $db;
    private ChangeOrder $changeOrders;
    private Contract $contracts;

    public function __construct()
    {
        $this->db = db();
        $this->changeOrders = new ChangeOrder($this->db);
        $this->contracts = new Contract($this->db);
    }

    /**
     * Show create form for a new change order on a contract.
     */
    public function create(int $contractId): void
    {
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $mode = 'create';
        $changeOrder = [];
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);
        $old = $_SESSION['old_co_form'] ?? null;
        unset($_SESSION['old_co_form']);
        if (is_array($old) && $old) {
            $changeOrder = array_merge($changeOrder, $old);
        }
        require APP_ROOT . '/app/views/change_orders/edit.php';
    }

    /**
     * Handle POST to store a new change order.
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $contractId = (int)($_POST['contract_id'] ?? 0);
        if ($contractId <= 0 || !$this->contracts->find($contractId)) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $data = $this->collectFormData($_POST);
        $errors = $this->validate($data);
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_co_form'] = $data;
            header('Location: /index.php?page=change_orders_create&contract_id=' . $contractId);
            exit;
        }
        $this->changeOrders->create($contractId, $data);
        header('Location: /index.php?page=contracts_edit&contract_id=' . $contractId . '#change-orders');
        exit;
    }

    /**
     * Show edit form for an existing change order.
     */
    public function edit(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contractId = (int)$changeOrder['contract_id'];
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $mode = 'edit';
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);
        $old = $_SESSION['old_co_form'] ?? null;
        unset($_SESSION['old_co_form']);
        if (is_array($old) && $old) {
            $changeOrder = array_merge($changeOrder, $old);
        }
        require APP_ROOT . '/app/views/change_orders/edit.php';
    }

    /**
     * Handle POST to update an existing change order.
     */
    public function update(int $changeOrderId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contractId = (int)$changeOrder['contract_id'];
        $data = $this->collectFormData($_POST);
        $errors = $this->validate($data);
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_co_form'] = $data;
            header('Location: /index.php?page=change_orders_edit&change_order_id=' . $changeOrderId);
            exit;
        }
        $this->changeOrders->update($changeOrderId, $data);
        header('Location: /index.php?page=contracts_edit&contract_id=' . $contractId . '#change-orders');
        exit;
    }

    /**
     * Handle POST to delete a change order.
     */
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $changeOrderId = (int)($_POST['change_order_id'] ?? 0);
        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contractId = (int)$changeOrder['contract_id'];
        $this->changeOrders->delete($changeOrderId);
        header('Location: /index.php?page=contracts_edit&contract_id=' . $contractId . '#change-orders');
        exit;
    }

    /**
     * Handle POST to store a new change order then redirect to print view.
     */
    public function storeAndPrint(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $contractId = (int)($_POST['contract_id'] ?? 0);
        if ($contractId <= 0 || !$this->contracts->find($contractId)) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $data = $this->collectFormData($_POST);
        $errors = $this->validate($data);
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_co_form'] = $data;
            header('Location: /index.php?page=change_orders_create&contract_id=' . $contractId);
            exit;
        }
        $changeOrderId = $this->changeOrders->create($contractId, $data);
        header('Location: /index.php?page=change_orders_print&change_order_id=' . $changeOrderId);
        exit;
    }

    /**
     * Handle POST to update an existing change order then redirect to print view.
     */
    public function updateAndPrint(int $changeOrderId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contractId = (int)$changeOrder['contract_id'];
        $data = $this->collectFormData($_POST);
        $errors = $this->validate($data);
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_co_form'] = $data;
            header('Location: /index.php?page=change_orders_edit&change_order_id=' . $changeOrderId);
            exit;
        }
        $this->changeOrders->update($changeOrderId, $data);
        header('Location: /index.php?page=change_orders_print&change_order_id=' . $changeOrderId);
        exit;
    }

    /**
     * Generate and display a print-ready Change Order document.
     */
    public function print(int $changeOrderId): void
    {
        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contract = $this->contracts->find((int)$changeOrder['contract_id']);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }

        // Enrich contract with company names if needed
        if (empty($contract['owner_company_name']) && !empty($contract['owner_company_id'])) {
            $stmt = $this->db->prepare("SELECT name FROM companies WHERE company_id = ? LIMIT 1");
            $stmt->execute([(int)$contract['owner_company_id']]);
            $contract['owner_company_name'] = $stmt->fetchColumn() ?: '';
        }
        if (empty($contract['counterparty_company_name']) && !empty($contract['counterparty_company_id'])) {
            $stmt = $this->db->prepare("SELECT name FROM companies WHERE company_id = ? LIMIT 1");
            $stmt->execute([(int)$contract['counterparty_company_id']]);
            $contract['counterparty_company_name'] = $stmt->fetchColumn() ?: '';
        }

        // Merge fields: contract fields + change order fields
        $fields = array_merge($contract, [
            'change_order_id'     => $changeOrder['change_order_id'],
            'change_order_number' => $changeOrder['change_order_number'],
            'co_justification'    => $changeOrder['co_justification'] ?? '',
            'co_amount'           => $changeOrder['co_amount'] !== null
                                        ? '$' . number_format((float)$changeOrder['co_amount'], 2)
                                        : '',
            'approval_date'       => !empty($changeOrder['approval_date'])
                                        ? date('F j, Y', strtotime((string)$changeOrder['approval_date']))
                                        : '',
        ]);

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $contractLabel  = $h($contract['contract_number'] ?? ('#' . $contract['contract_id']));
        $contractName   = $h($contract['name'] ?? '');
        $coNumber       = $h($changeOrder['change_order_number']);
        $coAmount       = $fields['co_amount'] !== '' ? $h($fields['co_amount']) : '—';
        $approvalDate   = $fields['approval_date'] !== '' ? $h($fields['approval_date']) : '—';
        $justification  = nl2br($h($changeOrder['co_justification'] ?? ''));
        $owner          = $h($contract['owner_company_name'] ?? '');
        $counterparty   = $h($contract['counterparty_company_name'] ?? '');
        $dept           = $h($contract['department_name'] ?? '');
        $startDate      = !empty($contract['start_date'])
                            ? $h(date('F j, Y', strtotime((string)$contract['start_date']))) : '—';
        $endDate        = !empty($contract['end_date'])
                            ? $h(date('F j, Y', strtotime((string)$contract['end_date']))) : '—';
        $totalValue     = !empty($contract['total_contract_value'])
                            ? $h('$' . number_format((float)$contract['total_contract_value'], 2)) : '—';
        $govLaw         = $h($contract['governing_law'] ?? '');
        $statusName     = $h($contract['status_name'] ?? '');
        $today          = date('F j, Y');

        // Output standalone print page (no app layout)
        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Order {$coNumber}</title>
  <style>
    @page { margin: 1in; }
    @media print {
      .no-print { display: none !important; }
      body { margin: 0; }
    }
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #111; line-height: 1.5; margin: 30px; }
    .no-print { margin-bottom: 20px; }
    .no-print button { font-size: 14px; padding: 8px 20px; cursor: pointer; background: #0d6efd; color: #fff; border: none; border-radius: 4px; margin-right: 8px; }
    .no-print a { font-size: 14px; padding: 8px 16px; text-decoration: none; background: #6c757d; color: #fff; border-radius: 4px; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #999; padding-bottom: 3px; color: #444; }
    .doc-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 20px; }
    .doc-header .label { font-size: 11px; color: #666; margin-bottom: 4px; }
    .section { margin-bottom: 20px; }
    table.info { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.info td { padding: 4px 8px; vertical-align: top; }
    table.info td.lbl { font-weight: bold; width: 180px; color: #333; }
    table.info td.val { }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .justification { background: #f9f9f9; border: 1px solid #ddd; padding: 12px; min-height: 80px; margin-top: 4px; }
    .sig-block { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
    .sig-line { border-top: 1px solid #000; padding-top: 4px; margin-top: 40px; font-size: 11px; }
    .footer-note { margin-top: 30px; font-size: 10px; color: #888; text-align: center; border-top: 1px solid #ccc; padding-top: 8px; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Print / Save as PDF</button>
  <a href="/index.php?page=contracts_show&contract_id={$h($contract['contract_id'])}#change-orders">&larr; Back to Contract</a>
</div>

<div class="doc-header">
  <div class="label">CHANGE ORDER</div>
  <h1>Change Order No. {$coNumber}</h1>
  <div style="font-size:11px; color:#555; margin-top:4px;">
    Contract: <strong>{$contractLabel}</strong> &mdash; {$contractName}
  </div>
  <div style="font-size:11px; color:#555;">Date Printed: {$today}</div>
</div>

<div class="section">
  <h2>Change Order Details</h2>
  <table class="info">
    <tr><td class="lbl">Change Order Number</td><td class="val">{$coNumber}</td></tr>
    <tr><td class="lbl">Amount</td><td class="val">{$coAmount}</td></tr>
    <tr><td class="lbl">Approval Date</td><td class="val">{$approvalDate}</td></tr>
  </table>
</div>

<div class="section">
  <h2>Justification / Scope of Change</h2>
  <div class="justification">{$justification}</div>
</div>

<div class="section">
  <h2>Contract Information</h2>
  <table class="info">
    <tr><td class="lbl">Contract Number</td><td class="val">{$contractLabel}</td></tr>
    <tr><td class="lbl">Contract Name</td><td class="val">{$contractName}</td></tr>
    <tr><td class="lbl">Status</td><td class="val">{$statusName}</td></tr>
    <tr><td class="lbl">Department</td><td class="val">{$dept}</td></tr>
    <tr><td class="lbl">Owner / Municipality</td><td class="val">{$owner}</td></tr>
    <tr><td class="lbl">Counterparty / Contractor</td><td class="val">{$counterparty}</td></tr>
    <tr><td class="lbl">Contract Start Date</td><td class="val">{$startDate}</td></tr>
    <tr><td class="lbl">Contract End Date</td><td class="val">{$endDate}</td></tr>
    <tr><td class="lbl">Total Contract Value</td><td class="val">{$totalValue}</td></tr>
    <tr><td class="lbl">Governing Law</td><td class="val">{$govLaw}</td></tr>
  </table>
</div>

<div class="sig-block">
  <div>
    <h2>Owner Authorization</h2>
    <div class="sig-line">Signature</div>
    <div class="sig-line">Printed Name &amp; Title</div>
    <div class="sig-line">Date</div>
  </div>
  <div>
    <h2>Contractor / Vendor</h2>
    <div class="sig-line">Signature</div>
    <div class="sig-line">Printed Name &amp; Title</div>
    <div class="sig-line">Date</div>
  </div>
</div>

<div class="footer-note">
  Generated {$today} &bull; Change Order {$coNumber} &bull; Contract {$contractLabel}
</div>

<script>
  // Auto-open print dialog if ?autoprint=1
  if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.onload = function() { window.print(); };
  }
</script>
</body>
</html>
HTML;
        exit;
    }

    /**
     * Generate a Change Order document (docx or html) using the contract_types
     * template for type "Change Order", merging both contract and change order fields.
     * Saves the result to contract_documents.
     */
    public function generateDocument(int $changeOrderId): void
    {
        $format = strtolower(trim((string)($_GET['format'] ?? 'docx')));
        if (!in_array($format, ['docx', 'html'], true)) {
            $format = 'docx';
        }

        $changeOrder = $this->changeOrders->find($changeOrderId);
        if (!$changeOrder) {
            http_response_code(404);
            echo 'Change order not found.';
            return;
        }
        $contractId = (int)$changeOrder['contract_id'];
        $contract   = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }

        // Resolve company names
        foreach (['owner' => 'owner_company_id', 'counterparty' => 'counterparty_company_id'] as $prefix => $col) {
            if (!empty($contract[$col])) {
                $stmt = $this->db->prepare("SELECT name FROM companies WHERE company_id = ? LIMIT 1");
                $stmt->execute([(int)$contract[$col]]);
                $contract[$prefix . '_company_name'] = $stmt->fetchColumn() ?: '';
            }
        }

        // Merge Development Agreement fields if this contract is a DA
        $stmt = $this->db->prepare(
            "SELECT contract_type FROM contract_types WHERE contract_type_id = ? LIMIT 1"
        );
        $stmt->execute([$contract['contract_type_id'] ?? 0]);
        $ctName = strtolower((string)($stmt->fetchColumn() ?: ''));
        if (str_contains($ctName, 'development agreement')) {
            require_once APP_ROOT . '/app/models/DevelopmentAgreement.php';
            $daModel = new DevelopmentAgreement($this->db);
            $da = $daModel->findByContractId($contractId);
            if ($da) {
                foreach ($da as $k => $v) {
                    $contract['da_' . $k] = $v;
                    if (!isset($contract[$k])) {
                        $contract[$k] = $v;
                    }
                }
                $contract['da_parkland_dedication_label'] = !empty($da['parkland_dedication']) ? 'Yes' : 'No';
                $contract['da_transportation_tier']       = $da['transportation_tier'] ?? '';
                $contract['da_number_of_units']           = $da['number_of_units'] ?? '';
                $contract['da_daily_flow_maximum']        = $da['daily_flow_maximum'] !== null
                    ? number_format((int)$da['daily_flow_maximum']) . ' gpd' : '';
            }
        }

        // Look up the "Change Order" contract type template
        $stmt = $this->db->prepare(
            "SELECT template_file_docx, template_file_html
             FROM contract_types
             WHERE LOWER(contract_type) LIKE '%change order%'
             ORDER BY contract_type_id ASC LIMIT 1"
        );
        $stmt->execute();
        $coType = $stmt->fetch(PDO::FETCH_ASSOC);

        $templateFile = $format === 'docx'
            ? ($coType['template_file_docx'] ?? null)
            : ($coType['template_file_html'] ?? null);

        if (!$templateFile) {
            $_SESSION['flash_errors'] = [
                'No ' . strtoupper($format) . ' template is configured for the "Change Order" contract type. '
                . 'Please upload a template in Admin → Contract Types → Change Order.'
            ];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#change-orders');
            exit;
        }

        $templatePath = APP_ROOT . '/' . ltrim($templateFile, '/');
        if (!file_exists($templatePath)) {
            $_SESSION['flash_errors'] = ['Change Order template file not found on disk: ' . $templateFile];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#change-orders');
            exit;
        }

        // Build merged fields: contract fields + change order fields (co_ prefix wins on collision)
        $coFormatted = [
            'change_order_id'     => $changeOrder['change_order_id'],
            'change_order_number' => $changeOrder['change_order_number'],
            'co_justification'    => $changeOrder['co_justification'] ?? '',
            'co_amount'           => $changeOrder['co_amount'] !== null
                                        ? number_format((float)$changeOrder['co_amount'], 2) : '',
            'co_amount_formatted' => $changeOrder['co_amount'] !== null
                                        ? '$' . number_format((float)$changeOrder['co_amount'], 2) : '',
            'approval_date'       => !empty($changeOrder['approval_date'])
                                        ? date('F j, Y', strtotime((string)$changeOrder['approval_date'])) : '',
            'approval_date_short' => !empty($changeOrder['approval_date'])
                                        ? date('m/d/Y', strtotime((string)$changeOrder['approval_date'])) : '',
        ];
        $mergeFields = array_merge($contract, $coFormatted);

        $createdBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;
        $outputDir = APP_ROOT . '/storage/contracts/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        if ($format === 'docx') {
            require_once APP_ROOT . '/vendor/autoload.php';
            $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            foreach ($mergeFields as $key => $value) {
                $safe = htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $processor->setValue($key, $safe);
            }

            // Reserve a row in contract_documents first so we have the ID for the filename
            $stmt = $this->db->prepare(
                "INSERT INTO contract_documents (contract_id, doc_type, file_path, file_name, created_at, created_by_person_id)
                 VALUES (?, 'Change Order', '', '', NOW(), ?)"
            );
            $stmt->execute([$contractId, $createdBy]);
            $docId    = (int)$this->db->lastInsertId();
            $fileName = $contractId . '_CO' . $changeOrderId . '_v' . $docId . '.docx';
            $relPath  = 'storage/contracts/' . $fileName;
            $processor->saveAs($outputDir . $fileName);

            $this->db->prepare(
                "UPDATE contract_documents SET file_path = ?, file_name = ? WHERE contract_document_id = ?"
            )->execute([$relPath, $fileName, $docId]);

        } else {
            // HTML format
            $content = file_get_contents($templatePath);
            if ($content === false) {
                $_SESSION['flash_errors'] = ['Failed to read Change Order HTML template.'];
                header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#change-orders');
                exit;
            }
            foreach ($mergeFields as $key => $value) {
                $content = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value), $content);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO contract_documents (contract_id, doc_type, file_path, file_name, created_at, created_by_person_id)
                 VALUES (?, 'Change Order', '', '', NOW(), ?)"
            );
            $stmt->execute([$contractId, $createdBy]);
            $docId    = (int)$this->db->lastInsertId();
            $fileName = $contractId . '_CO' . $changeOrderId . '_v' . $docId . '.html';
            $relPath  = 'storage/contracts/' . $fileName;
            file_put_contents($outputDir . $fileName, $content);

            $this->db->prepare(
                "UPDATE contract_documents SET file_path = ?, file_name = ? WHERE contract_document_id = ?"
            )->execute([$relPath, $fileName, $docId]);
        }

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#change-orders');
        exit;
    }

    private function collectFormData(array $post): array
    {
        return [
            'change_order_number' => trim((string)($post['change_order_number'] ?? '')),
            'co_justification'    => trim((string)($post['co_justification'] ?? '')),
            'co_amount'           => trim((string)($post['co_amount'] ?? '')),
            'approval_date'       => trim((string)($post['approval_date'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['change_order_number'] === '') {
            $errors[] = 'Change Order Number is required.';
        }
        return $errors;
    }
}
