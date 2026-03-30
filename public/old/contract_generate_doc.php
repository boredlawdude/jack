<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$pdo = pdo();

// ────────────────────────────────────────────────
// 1. Get & validate contract ID
// ────────────────────────────────────────────────
$contractId = (int)($_GET['id'] ?? 0);
if ($contractId <= 0) {
    http_response_code(400);
    exit('Missing or invalid contract ID');
}

// Load basic contract data (you can expand this query)
$stmt = $pdo->prepare("
    SELECT contract_number, name, counterparty_company_id, governing_law
    FROM contracts
    WHERE contract_id = ?
    LIMIT 1
");
$stmt->execute([$contractId]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    http_response_code(404);
    exit('Contract not found');
}




// ────────────────────────────────────────────────
// 2. Get paths from database config
// ────────────────────────────────────────────────
$templateDir   = rtrim(get_system_setting('docx_template_dir', __DIR__ . '/templates/docx') ?? '', '/');
$templateFile  = get_system_setting('default_docx_template', 'default_template.docx');
$templatePath  = $templateDir . '/' . ltrim($templateFile, '/');

if (!file_exists($templatePath) || !is_readable($templatePath)) {
    http_response_code(500);
    exit("DOCX template file not found or unreadable: $templatePath");
}

// ────────────────────────────────────────────────
// 3. Prepare local temporary folder (visible for debugging)
// ────────────────────────────────────────────────
$tempRoot = __DIR__ . '/temp-generated';
if (!is_dir($tempRoot) && !mkdir($tempRoot, 0775, true)) {
    http_response_code(500);
    exit("Cannot create temporary folder: $tempRoot");
}

// Build unique but readable filename
$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_',
    ($contract['contract_number'] ?: 'contract_' . $contractId) .
    '_' . date('Ymd_His')
);
$tempFile = $tempRoot . '/' . $safeBase . '.docx';

// ────────────────────────────────────────────────
// 4. Generate DOCX
// ────────────────────────────────────────────────
try {
    $template = new TemplateProcessor($templatePath);

    // Replace placeholders – add all your fields here
    $template->setValue('contract_number',     $contract['contract_number'] ?? 'N/A');
    $template->setValue('contract_name',       $contract['name'] ?? 'Unnamed Contract');
    $template->setValue('governing_law',       $contract['governing_law'] ?? 'North Carolina');
    $template->setValue('date_today',          date('F j, Y'));
    $template->setValue('counterparty',        'Counterparty TBD'); // ← replace with real lookup if needed
    // ... add more: description, department, value, dates, signatures lines, etc.

    $template->saveAs($tempFile);

    if (!file_exists($tempFile) || filesize($tempFile) < 2000) {
        throw new RuntimeException("Generated file is missing or too small: $tempFile");
    }
} catch (Exception $e) {
    http_response_code(500);
    exit("DOCX generation failed: " . $e->getMessage());
}
// ────────────────────────────────────────────────
// 5. Save to permanent storage & database
// ────────────────────────────────────────────────
$finalFilename = $safeBase . '.docx';
require_once __DIR__ . '/includes/docs_helpers.php';

try {
    $result = save_generated_doc(
        $pdo,
        $contractId,
        $finalFilename,
        'generated_docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        current_person_id() ?: null,
        ['source_path' => $tempFile]
    );

    // Optional: log success
    error_log("DOCX generated & saved for contract #$contractId → DB ID: " . $result['contract_document_id']);
} catch (Exception $e) {
    // Keep temp file for debugging if save fails
    error_log("Save failed: " . $e->getMessage());
    http_response_code(500);
    exit("Failed to save generated document: " . $e->getMessage());
}

// Clean up temp file
@unlink($tempFile);

// ────────────────────────────────────────────────
// 6. Redirect back with success
// ────────────────────────────────────────────────
$_SESSION['flash_success'] = 'DOCX document generated successfully.';
header("Location: /contract_edit.php?id=$contractId");
exit;