<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$doc_id = (int)($_POST['id'] ?? 0);
if ($doc_id <= 0) { http_response_code(400); exit('Missing document id'); }

// Fetch doc + contract for permission check
$stmt = $pdo->prepare("
  SELECT contract_document_id, contract_id, file_path, file_name
  FROM contract_documents
  WHERE contract_document_id = ?
  LIMIT 1
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) { http_response_code(404); exit('Doc not found'); }

$contract_id = (int)$doc['contract_id'];

// Only people who can manage the contract can delete generated docs
if (!can_manage_contract($contract_id)) {
  http_response_code(403);
  exit('Forbidden');
}

// Delete DB row first (or file first; either is fine)
$del = $pdo->prepare("DELETE FROM contract_documents WHERE contract_document_id = ?");
$del->execute([$doc_id]);

// Delete file on disk (best-effort)
$absPath = __DIR__ . '/' . ltrim((string)$doc['file_path'], '/');
if (is_file($absPath)) {
  @unlink($absPath);
}

header("Location: /contract_edit.php?id=" . $contract_id);
exit;
