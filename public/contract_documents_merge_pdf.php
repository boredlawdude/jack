<?php
/**
 * Merge all documents for a contract into a single PDF.
 *
 * Supports: PDF (direct import via FPDI), DOCX (PHPWord→HTML→dompdf), HTML/TXT (dompdf).
 * Saves result to storage and adds to document list.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

$db = db();

use setasign\Fpdi\Fpdi;

$contractId = (int)($_GET['contract_id'] ?? 0);
if ($contractId <= 0) {
    http_response_code(400);
    echo 'Missing contract_id.';
    exit;
}

$stmt = $db->prepare("SELECT contract_id, contract_number, name FROM contracts WHERE contract_id = ?");
$stmt->execute([$contractId]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contract) {
    http_response_code(404);
    echo 'Contract not found.';
    exit;
}

$docsStmt = $db->prepare(
    "SELECT contract_document_id, file_path, file_name, mime_type
     FROM contract_documents
     WHERE contract_id = ?
     ORDER BY sort_order ASC, created_at ASC"
);
$docsStmt->execute([$contractId]);
$documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($documents)) {
    $_SESSION['flash_errors'] = ['No documents to merge.'];
    header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
    exit;
}

// Resolve absolute file path from DB value
function resolveFilePath(string $dbPath): ?string
{
    $appRoot = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : __DIR__ . '/..'), '/');
    if (is_file($dbPath)) {
        return realpath($dbPath);
    }
    $candidate = $appRoot . '/' . ltrim($dbPath, '/');
    if (is_file($candidate)) {
        return realpath($candidate);
    }
    $candidate = $appRoot . '/storage/' . ltrim($dbPath, '/');
    if (is_file($candidate)) {
        return realpath($candidate);
    }
    return null;
}

// Detect Pandoc binary (cross-platform)
function findPandocBinary(): ?string
{
    $candidates = [
        '/opt/homebrew/bin/pandoc',   // macOS Homebrew ARM
        '/usr/local/bin/pandoc',     // macOS Homebrew Intel / Linux
        '/usr/bin/pandoc',           // Linux system
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    // Try PATH as fallback
    $which = PHP_OS_FAMILY === 'Windows' ? 'where pandoc 2>NUL' : 'which pandoc 2>/dev/null';
    $found = trim(shell_exec($which) ?? '');
    if ($found !== '' && is_file($found)) {
        return $found;
    }
    return null;
}

// Detect Chrome/Chromium binary (cross-platform)
function findChromeBinary(): ?string
{
    $candidates = [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',   // macOS
        '/usr/bin/google-chrome',                                        // Linux
        '/usr/bin/google-chrome-stable',                                 // Linux alt
        '/usr/bin/chromium-browser',                                     // Linux Chromium
        '/usr/bin/chromium',                                             // Linux Chromium alt
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',      // Windows
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe', // Windows x86
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    // Try PATH
    $which = PHP_OS_FAMILY === 'Windows' ? 'where chrome 2>NUL' : 'which google-chrome 2>/dev/null || which chromium 2>/dev/null';
    $found = trim(shell_exec($which) ?? '');
    if ($found !== '' && is_file($found)) {
        return $found;
    }
    return null;
}

// Print HTML to PDF using Chrome headless
function chromePrintToPdf(string $htmlFilePath): ?string
{
    $chrome = findChromeBinary();
    if (!$chrome) {
        return null;
    }

    $tmpPdf = tempnam(sys_get_temp_dir(), 'merge_pdf_') . '.pdf';
    $cmd = escapeshellarg($chrome)
         . ' --headless --disable-gpu --no-sandbox'
         . ' --print-to-pdf=' . escapeshellarg($tmpPdf)
         . ' --print-to-pdf-no-header'
         . ' ' . escapeshellarg('file://' . $htmlFilePath)
         . ' 2>&1';

    exec($cmd, $output, $exitCode);

    if ($exitCode === 0 && is_file($tmpPdf) && filesize($tmpPdf) > 0) {
        return $tmpPdf;
    }
    @unlink($tmpPdf);
    return null;
}

// Convert DOCX to PDF via Pandoc (DOCX→HTML) + Chrome headless (HTML→PDF)
function docxToPdf(string $docxPath): ?string
{
    $absDocx = realpath($docxPath);
    if (!$absDocx) {
        return null;
    }

    // Step 1: Pandoc converts DOCX → standalone HTML
    $pandoc = findPandocBinary();
    if (!$pandoc) {
        error_log('Merge PDF: pandoc not found');
        return null;
    }
    $tmpHtml = tempnam(sys_get_temp_dir(), 'docx_html_') . '.html';
    $pandocCmd = escapeshellarg($pandoc) . ' ' . escapeshellarg($absDocx) . ' --standalone -o ' . escapeshellarg($tmpHtml) . ' 2>&1';
    exec($pandocCmd, $pandocOut, $pandocExit);
    if ($pandocExit !== 0 || !is_file($tmpHtml) || filesize($tmpHtml) === 0) {
        error_log('Merge PDF: Pandoc failed (exit=' . $pandocExit . '): ' . implode("\n", $pandocOut));
        @unlink($tmpHtml);
        return null;
    }

    // Step 2: Inject normalization CSS so Chrome renders at standard document size
    $normalizeCSS = '
<style>
  /* Normalize Pandoc HTML to match a printed Word document */
  html, body {
    font-family: "Times New Roman", Times, serif;
    font-size: 11pt;
    line-height: 1.5;
    color: #000;
    margin: 0;
    padding: 0;
  }
  body {
    width: 8.5in;
    max-width: 8.5in;
    padding: 1in 1in 1in 1.25in;
    box-sizing: border-box;
  }
  p { margin: 0 0 0.5em 0; }
  h1 { font-size: 14pt; }
  h2 { font-size: 13pt; }
  h3, h4, h5, h6 { font-size: 11pt; }
  table { border-collapse: collapse; width: 100%; font-size: 11pt; }
  td, th { padding: 4px 6px; }
  pre, code { font-size: 10pt; font-family: "Courier New", Courier, monospace; }
  @page { size: letter; margin: 0; }
</style>';
    $html = file_get_contents($tmpHtml);
    // Insert before </head> or at the top if no head tag
    if (stripos($html, '</head>') !== false) {
        $html = str_ireplace('</head>', $normalizeCSS . '</head>', $html);
    } else {
        $html = $normalizeCSS . $html;
    }
    file_put_contents($tmpHtml, $html);

    // Step 3: Chrome headless converts HTML → PDF
    $result = chromePrintToPdf($tmpHtml);
    if (!$result) {
        error_log('Merge PDF: Chrome headless failed for ' . $tmpHtml);
    }
    @unlink($tmpHtml);
    return $result;
}

// Convert HTML file to PDF
function htmlFileToPdf(string $htmlPath): ?string
{
    return chromePrintToPdf($htmlPath);
}

// Convert plain text to PDF
function textToPdf(string $textPath): ?string
{
    $text = file_get_contents($textPath);
    $html = '<html><body><pre style="font-family:monospace;font-size:11pt;white-space:pre-wrap;">'
          . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
          . '</pre></body></html>';
    $tmpHtml = tempnam(sys_get_temp_dir(), 'txt_html_') . '.html';
    file_put_contents($tmpHtml, $html);
    $result = chromePrintToPdf($tmpHtml);
    @unlink($tmpHtml);
    return $result;
}

// Collect PDF files to merge
$pdfFiles = [];
$tempFiles = [];
$errors = [];

foreach ($documents as $doc) {
    $filePath = resolveFilePath($doc['file_path'] ?? '');
    if (!$filePath) {
        $errors[] = 'File not found: ' . ($doc['file_name'] ?: $doc['file_path']);
        continue;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    try {
        if ($ext === 'pdf') {
            $pdfFiles[] = $filePath;
        } elseif ($ext === 'docx') {
            $converted = docxToPdf($filePath);
            if ($converted) {
                $pdfFiles[] = $converted;
                $tempFiles[] = $converted;
            } else {
                $errors[] = 'Could not convert: ' . $doc['file_name'];
            }
        } elseif (in_array($ext, ['html', 'htm'], true)) {
            $converted = htmlFileToPdf($filePath);
            if ($converted) {
                $pdfFiles[] = $converted;
                $tempFiles[] = $converted;
            } else {
                $errors[] = 'Could not convert: ' . $doc['file_name'];
            }
        } elseif ($ext === 'txt') {
            $converted = textToPdf($filePath);
            if ($converted) {
                $pdfFiles[] = $converted;
                $tempFiles[] = $converted;
            } else {
                $errors[] = 'Could not convert: ' . $doc['file_name'];
            }
        } else {
            $errors[] = 'Unsupported format: ' . $doc['file_name'] . ' (' . $ext . ')';
        }
    } catch (Throwable $e) {
        $errors[] = 'Error converting ' . $doc['file_name'] . ': ' . $e->getMessage();
    }
}

if (empty($pdfFiles)) {
    $msg = 'No documents could be converted to PDF.';
    if ($errors) {
        $msg .= ' ' . implode('; ', $errors);
    }
    $_SESSION['flash_errors'] = [$msg];
    header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
    exit;
}

// Merge all PDFs using FPDI
try {
    $merger = new Fpdi();

    foreach ($pdfFiles as $pdfFile) {
        $pageCount = $merger->setSourceFile($pdfFile);
        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $merger->importPage($i);
            $size = $merger->getTemplateSize($tplId);
            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($tplId);
        }
    }

    // Generate output filename
    $contractNum = preg_replace('/[^A-Za-z0-9_-]/', '_', $contract['contract_number'] ?? 'contract');
    $outputName = $contractNum . '_merged_' . date('Ymd_His') . '.pdf';

    // Save to storage
    $storageDir = APP_ROOT . '/storage/generated_docs/' . $contractId;
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    $savePath = $storageDir . '/' . $outputName;
    $merger->Output('F', $savePath);

    // Add to document list
    $relPath = 'storage/generated_docs/' . $contractId . '/' . $outputName;
    $insertStmt = $db->prepare(
        "INSERT INTO contract_documents (contract_id, doc_type, description, file_name, file_path, mime_type, created_by_person_id, created_at)
         VALUES (?, 'pdf', 'Merged PDF of all documents', ?, ?, 'application/pdf', ?, NOW())"
    );
    $personId = (int)($_SESSION['person']['person_id'] ?? 0);
    $insertStmt->execute([$contractId, $outputName, $relPath, $personId]);

    // Redirect back with success
    $_SESSION['flash_success'] = 'Documents merged into PDF: ' . $outputName;
    if ($errors) {
        $_SESSION['flash_errors'] = $errors;
    }
    header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
    exit;

} catch (Throwable $e) {
    $_SESSION['flash_errors'] = ['PDF merge failed: ' . $e->getMessage()];
    header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
    exit;
} finally {
    foreach ($tempFiles as $tmp) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
}
