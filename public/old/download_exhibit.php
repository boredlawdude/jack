<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$exhibit_id = (int)($_GET['id'] ?? 0);
if ($exhibit_id <= 0) { http_response_code(400); exit('Missing exhibit id'); }

$stmt = $pdo->prepare("
  SELECT
    exhibit_id,
    contract_id,
    file_name,
    mime_type,
    file_size,
    pdf_blob
  FROM contract_exhibits
  WHERE exhibit_id = ?
    AND is_deleted = 0
  LIMIT 1
");
$stmt->execute([$exhibit_id]);
$ex = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ex) { http_response_code(404); exit('Exhibit not found'); }

// Permission check: must be able to view the contract
if (function_exists('require_contract_view_access')) {
  require_contract_view_access((int)$ex['contract_id']);
} elseif (function_exists('can_view_contract') && !can_view_contract((int)$ex['contract_id'])) {
  http_response_code(403); exit('Forbidden');
}

$mime = (string)($ex['mime_type'] ?? 'application/pdf');
$fname = (string)($ex['file_name'] ?? ('exhibit_' . $exhibit_id . '.pdf'));
$blob  = $ex['pdf_blob'];

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"','', $fname) . '"');
header('Content-Length: ' . strlen($blob));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $blob;
exit;
