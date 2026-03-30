<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

$templatePath = __DIR__ . '/templates/docx/default_template.docx'; // adjust if needed
$outputPath   = __DIR__ . '/test_merged.docx';

if (!file_exists($templatePath)) die("Template missing");

$template = new TemplateProcessor($templatePath);

// Set some test values
$template->setValue('contract_number', 'TEST-2026-999');
$template->setValue('governing_law',   'North Carolina – test merge');
$template->setValue('date_today',      date('F j, Y'));
$template->setValue('counterparty',    'Town of Fuquay-Varina');

try {
    $template->saveAs($outputPath);
    echo "<p style='color:green'>Success! Check file: $outputPath</p>";
    echo "<p>Open it in Word and see if fields were replaced.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Failed: " . $e->getMessage() . "</p>";
}