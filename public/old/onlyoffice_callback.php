<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$doc_id = (int)($_GET['doc_id'] ?? 0);
$sig    = (string)($_GET['sig'] ?? '');

if ($doc_id <= 0 || $sig === '') {
  http_response_code(400);
  echo json_encode(['error' => 1, 'message' => 'Missing doc_id or sig']);
  exit;
}

if (!function_exists('oo_verify') || !oo_verify(['doc_id' => $doc_id], $sig)) {
  http_response_code(403);
  echo json_encode(['error' => 1, 'message' => 'Bad signature']);
  exit;
}

$dataRaw = file_get_contents('php://input');
$data = json_decode($dataRaw ?: '', true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['error' => 1, 'message' => 'Invalid JSON']);
  exit;
}

/**
 * OnlyOffice statuses:
 * 1 = editing
 * 2 = ready for saving (download "url")
 * 4 = closed without changes
 * (others exist; we just act on 2)
 */
$status = (int)($data['status'] ?? 0);

if ($status !== 2) {
  // Acknowledge but do nothing
  echo json_encode(['error' => 0, 'message' => 'No save needed', 'status' => $status]);
  exit;
}

$fileUrl = (string)($data['url'] ?? '');
if ($fileUrl === '') {
  http_response_code(400);
  echo json_encode(['error' => 1, 'message' => 'Missing file url']);
  exit;
}

// Load doc record
$q = $pdo->prepare("
  SELECT contract_document_id, file_name, file_path, mime_type
  FROM contract_documents
  WHERE contract_document_id = ?
  LIMIT 1
");
$q->execute([$doc_id]);
$doc = $q->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
  http_response_code(404);
  echo json_encode(['error' => 1, 'message' => 'Document not found']);
  exit;
}

// Resolve disk path (support relative paths under DOCS_BASE_DIR)
$path = (string)($doc['file_path'] ?? '');
if ($path === '') {
  http_response_code(500);
  echo json_encode(['error' => 1, 'message' => 'Empty file_path']);
  exit;
}

if (defined('DOCS_BASE_DIR') && $path !== '' && $path[0] !== '/') {
  $abs = rtrim((string)DOCS_BASE_DIR, '/') . '/' . ltrim($path, '/');
} else {
  $abs = $path; // assume absolute
}

// Fetch updated file bytes from OnlyOffice
$newContent = @file_get_contents($fileUrl);
if ($newContent === false || $newContent === '') {
  http_response_code(502);
  echo json_encode(['error' => 1, 'message' => 'Failed to fetch updated file']);
  exit;
}

// Ensure directory exists
$dir = dirname($abs);
if (!is_dir($dir)) {
  if (!mkdir($dir, 0775, true)) {
    http_response_code(500);
    echo json_encode(['error' => 1, 'message' => 'Could not create output directory']);
    exit;
  }
}

// Write file back to disk (overwrite existing)
$bytes = @file_put_contents($abs, $newContent, LOCK_EX);
if ($bytes === false || $bytes < 1000) {
  http_response_code(500);
  echo json_encode(['error' => 1, 'message' => 'Failed to write updated file']);
  exit;
}

// Optional: touch DB record (your table has created_at only; keep as-is or remove)
$pdo->prepare("
  UPDATE contract_documents
  SET created_at = CURRENT_TIMESTAMP
  WHERE contract_document_id = ?
")->execute([$doc_id]);

echo json_encode(['error' => 0]);
exit;
