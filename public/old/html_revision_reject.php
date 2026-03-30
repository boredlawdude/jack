<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
$pdo = pdo();

$rid = (int)($_POST['revision_id'] ?? 0);
if ($rid <= 0) { http_response_code(400); exit('Missing revision'); }

$st = $pdo->prepare("SELECT contract_id FROM contract_html_revisions WHERE revision_id=? LIMIT 1");
$st->execute([$rid]);
$contract_id = (int)($st->fetchColumn() ?? 0);
if ($contract_id <= 0) { http_response_code(404); exit('Not found'); }

if (function_exists('can_manage_contract') && !can_manage_contract($contract_id)) {
  http_response_code(403); exit('Forbidden');
}

$pdo->prepare("DELETE FROM contract_html_revisions WHERE revision_id=?")->execute([$rid]);

header("Location: /contract_edit.php?id=" . $contract_id);
exit;
