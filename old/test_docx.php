<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$template = new TemplateProcessor(__DIR__ . '/templates/docx/default_template.docx'); // ← use your real template path
$template->setValue('contract_number', 'TEST_999');

$tempFile = sys_get_temp_dir() . '/test_docx_' . time() . '.docx';

echo "<pre>";
echo "Trying to save to: $tempFile\n";
echo "Temp dir writable: " . (is_writable(sys_get_temp_dir()) ? 'YES' : 'NO') . "\n";

try {
    $template->saveAs($tempFile);
    echo "Success! File created. Size: " . filesize($tempFile) . " bytes\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}
echo "</pre>";