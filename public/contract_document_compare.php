<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';

use Jfcherng\Diff\Diff;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;

$db = db();
$contractId = (int)($_GET['contract_id'] ?? 0);

if ($contractId <= 0) {
    http_response_code(400);
    exit('Missing contract ID.');
}

// Fetch all documents for this contract
$stmt = $db->prepare(
    "SELECT contract_document_id, file_name, doc_type, file_path, created_at
     FROM contract_documents
     WHERE contract_id = ? AND file_name != ''
     ORDER BY sort_order ASC, created_at ASC"
);
$stmt->execute([$contractId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contract info for breadcrumb
$cStmt = $db->prepare("SELECT contract_number, name FROM contracts WHERE contract_id = ? LIMIT 1");
$cStmt->execute([$contractId]);
$contract = $cStmt->fetch(PDO::FETCH_ASSOC);

$diffHtml = '';
$docAId = (int)($_GET['doc_a'] ?? 0);
$docBId = (int)($_GET['doc_b'] ?? 0);
$docAName = '';
$docBName = '';

if ($docAId > 0 && $docBId > 0 && $docAId !== $docBId) {
    // Fetch both documents
    $fetchDoc = function (int $docId) use ($db, $contractId): ?array {
        $s = $db->prepare("SELECT * FROM contract_documents WHERE contract_document_id = ? AND contract_id = ? LIMIT 1");
        $s->execute([$docId, $contractId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    $docA = $fetchDoc($docAId);
    $docB = $fetchDoc($docBId);

    if (!$docA || !$docB) {
        $diffHtml = '<div class="alert alert-danger">One or both documents not found.</div>';
    } else {
        $docAName = $docA['file_name'];
        $docBName = $docB['file_name'];

        $extractText = function (array $doc) use ($db): ?string {
            $path = APP_ROOT . '/' . ltrim((string)$doc['file_path'], '/');
            if (!is_file($path)) {
                return null;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'docx') {
                $oldLevel = error_reporting(error_reporting() & ~E_DEPRECATED);
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
                error_reporting($oldLevel);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        $text .= extractElementText($element) . "\n";
                    }
                }
                return $text;
            } elseif ($ext === 'html' || $ext === 'htm') {
                $html = file_get_contents($path);
                return $html !== false ? strip_tags($html) : null;
            } elseif ($ext === 'txt') {
                return file_get_contents($path) ?: null;
            }
            return null;
        };

        $textA = $extractText($docA);
        $textB = $extractText($docB);

        if ($textA === null || $textB === null) {
            $diffHtml = '<div class="alert alert-warning">Could not extract text from one or both documents. Only DOCX, HTML, and TXT files are supported.</div>';
        } else {
            // Perform diff
            $rendererOptions = [
                'detailLevel' => 'word',
                'showHeader' => false,
            ];

            $diffHtml = DiffHelper::calculate(
                $textA,
                $textB,
                'SideBySide',
                [],
                $rendererOptions
            );
        }
    }
}

/**
 * Recursively extract text from PHPWord elements
 */
function extractElementText($element): string
{
    $text = '';

    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        foreach ($element->getElements() as $child) {
            $text .= extractElementText($child);
        }
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        $text .= $element->getText();
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        foreach ($element->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellElement) {
                    $text .= extractElementText($cellElement) . ' ';
                }
                $text .= "\t";
            }
            $text .= "\n";
        }
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
        $text .= '• ';
        $listRun = $element->getTextObject();
        if (is_string($listRun)) {
            $text .= $listRun;
        } else {
            $text .= extractElementText($listRun);
        }
    } elseif (method_exists($element, 'getText')) {
        $val = $element->getText();
        if (is_string($val)) {
            $text .= $val;
        } elseif (is_object($val)) {
            $text .= extractElementText($val);
        }
    }

    return $text;
}
?>

<div class="container py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/index.php?page=contracts">Contracts</a></li>
      <li class="breadcrumb-item"><a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>"><?= h($contract['contract_number'] ?? (string)$contractId) ?></a></li>
      <li class="breadcrumb-item active">Compare Documents</li>
    </ol>
  </nav>

  <h1 class="h4 mb-4">Compare Documents</h1>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="contract_document_compare">
        <input type="hidden" name="contract_id" value="<?= $contractId ?>">

        <div class="col-md-5">
          <label for="doc_a" class="form-label fw-bold">Original Document</label>
          <select class="form-select" name="doc_a" id="doc_a" required>
            <option value="">— Select —</option>
            <?php foreach ($documents as $d): ?>
              <option value="<?= (int)$d['contract_document_id'] ?>"
                <?= $docAId === (int)$d['contract_document_id'] ? 'selected' : '' ?>>
                <?= h($d['file_name']) ?>
                <?= $d['doc_type'] ? ' (' . h($d['doc_type']) . ')' : '' ?>
                — <?= date('m/d/y', strtotime($d['created_at'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-5">
          <label for="doc_b" class="form-label fw-bold">Revised Document</label>
          <select class="form-select" name="doc_b" id="doc_b" required>
            <option value="">— Select —</option>
            <?php foreach ($documents as $d): ?>
              <option value="<?= (int)$d['contract_document_id'] ?>"
                <?= $docBId === (int)$d['contract_document_id'] ? 'selected' : '' ?>>
                <?= h($d['file_name']) ?>
                <?= $d['doc_type'] ? ' (' . h($d['doc_type']) . ')' : '' ?>
                — <?= date('m/d/y', strtotime($d['created_at'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Compare</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($diffHtml): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">
          Differences: <span class="text-muted"><?= h($docAName) ?></span> &rarr; <span class="text-muted"><?= h($docBName) ?></span>
        </h2>
        <div>
          <span class="badge bg-danger-subtle text-danger me-1">Removed</span>
          <span class="badge bg-success-subtle text-success">Added</span>
        </div>
      </div>
      <div class="card-body p-0">
        <style>
          .diff-wrapper { overflow-x: auto; }
          .diff-wrapper table { width: 100%; border-collapse: collapse; font-size: 0.85rem; font-family: monospace; }
          .diff-wrapper td { padding: 2px 8px; vertical-align: top; border-bottom: 1px solid #eee; white-space: pre-wrap; word-break: break-word; }
          .diff-wrapper th { padding: 2px 8px; background: #f8f9fa; font-weight: normal; color: #999; text-align: right; width: 40px; border-bottom: 1px solid #eee; }
          .diff-wrapper .change del { background-color: #fdd; text-decoration: line-through; color: #c00; }
          .diff-wrapper .change ins { background-color: #dfd; text-decoration: none; color: #060; }
          .diff-wrapper .old { background-color: #fff5f5; }
          .diff-wrapper .new { background-color: #f0fff0; }
          .diff-wrapper .rep { background-color: #fffbe6; }
        </style>
        <div class="diff-wrapper">
          <?= $diffHtml ?>
        </div>
      </div>
    </div>
  <?php elseif ($docAId > 0 && $docBId > 0): ?>
    <div class="alert alert-info">Select two different documents to compare.</div>
  <?php endif; ?>

  <div class="mt-3">
    <a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Contract</a>
  </div>
</div>
