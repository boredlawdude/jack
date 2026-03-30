<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$comment_id = (int)($_POST['company_comment_id'] ?? 0);
$company_id = (int)($_POST['company_id'] ?? 0);
if ($comment_id <= 0 || $company_id <= 0) { http_response_code(400); exit('Missing'); }

$stmt = $pdo->prepare("SELECT person_id FROM company_comments WHERE company_comment_id = ? LIMIT 1");
$stmt->execute([$comment_id]);
$owner_id = (int)($stmt->fetchColumn() ?? 0);

if (!$owner_id) { http_response_code(404); exit('Not found'); }

if (!is_system_admin() && $owner_id !== current_person_id()) {
  http_response_code(403);
  exit('Forbidden');
}

$pdo->prepare("DELETE FROM company_comments WHERE company_comment_id = ?")->execute([$comment_id]);

header("Location: /company_edit.php?id=" . $company_id);
exit;
