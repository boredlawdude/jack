<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$doc_id = (int)($_GET['id'] ?? 0);
if ($doc_id <= 0) { http_response_code(400); exit('Missing id'); }

$stmt = $pdo->prepare("
  SELECT
    cd.file_name,
    cd.file_path,
    cd.mime_type,
    cd.contract_id
  FROM contract_documents cd
  WHERE cd.contract_document_id = ?
  LIMIT 1
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) { http_response_code(404); exit('Not found'); }

// Permission check (same as download)
// require_contract_view_access((int)$doc['contract_id']);

$absPath = __DIR__ . '/' . ltrim($doc['file_path'], '/');
if (!is_file($absPath)) {
  http_response_code(404);
  exit('File missing');
}

$mime = $doc['mime_type']
  ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// INLINE instead of attachment
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . filesize($absPath));
header('Cache-Control: private');

readfile($absPath);
exit;
