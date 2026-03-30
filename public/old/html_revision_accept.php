<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
$pdo = pdo();

$rid = (int)($_POST['revision_id'] ?? 0);
if ($rid <= 0) { http_response_code(400); exit('Missing revision'); }

$rev = $pdo->prepare("
  SELECT r.*, d.file_path
  FROM contract_html_revisions r
  JOIN contract_documents d ON d.contract_document_id = r.contract_document_id
  WHERE r.revision_id = ?
  LIMIT 1
");
$rev->execute([$rid]);
$r = $rev->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); exit('Not found'); }

$contract_id = (int)$r['contract_id'];
if (function_exists('can_manage_contract') && !can_manage_contract($contract_id)) {
  http_response_code(403); exit('Forbidden');
}

$abs = doc_abs_path((string)$r['file_path']);
if (!is_file($abs) || !is_writable($abs)) { http_response_code(500); exit('File not writable'); }

file_put_contents($abs, (string)$r['new_html']);

$pdo->prepare("UPDATE contract_html_revisions SET is_accepted=1, accepted_at=NOW() WHERE revision_id=?")
    ->execute([$rid]);

header("Location: /contract_edit.php?id=" . $contract_id);
exit;
