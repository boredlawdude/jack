<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

if (!can_edit_company()) { http_response_code(403); exit('Forbidden'); }

$pdo = pdo();

$company_id = (int)($_POST['company_id'] ?? 0);
$text = trim((string)($_POST['comment_text'] ?? ''));

if ($company_id <= 0 || $text === '') { http_response_code(400); exit('Missing data'); }

$ins = $pdo->prepare("
  INSERT INTO company_comments (company_id, person_id, comment_text)
  VALUES (?, ?, ?)
");
$ins->execute([$company_id, current_person_id(), $text]);

header("Location: /index.php?page=companies_edit&company_id=" . $company_id);
exit;
