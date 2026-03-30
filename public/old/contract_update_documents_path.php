<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

if (!is_superuser()) {
  http_response_code(403);
  exit('Forbidden');
}

$pdo = pdo();

$contract_id = (int)($_POST['contract_id'] ?? 0);
$path = trim((string)($_POST['documents_path'] ?? ''));

if ($contract_id <= 0) {
  http_response_code(400);
  exit('Missing contract');
}

/* ---- Normalize path ---- */
$path = ltrim($path, '/');
if ($path !== '' && substr($path, -1) !== '/') {
  $path .= '/';
}

/* ---- Validate characters ---- */
if ($path !== '' && !preg_match('#^[A-Za-z0-9_\-/]+/$#', $path)) {
  http_response_code(400);
  exit('Invalid path');
}

/* ---- Create folder if missing ---- */
$abs = DOCS_BASE_DIR . '/' . $path;
if ($path !== '' && !is_dir($abs)) {
  if (!mkdir($abs, 0775, true)) {
    http_response_code(500);
    exit('Could not create directory');
  }
}

/* ---- Save ---- */
$pdo->prepare("
  UPDATE contracts
  SET documents_path = ?
  WHERE contract_id = ?
")->execute([$path !== '' ? $path : null, $contract_id]);

header("Location: /contract_edit.php?id=" . $contract_id);
exit;
