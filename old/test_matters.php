<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Restrict to Legal department only (same check as create page)
$me = current_person();
$legal_dept = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = 'Legal' LIMIT 1");
$legal_dept->execute();
$legal_department_id = (int)($legal_dept->fetchColumn() ?: 0);
echo 'hello world';

echo current_person_id()

?>
