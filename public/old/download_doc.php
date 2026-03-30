<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();


$pdo = pdo();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Missing document id');
}

/*
   1️⃣ Load document metadata from DB
*/
$stmt = $pdo->prepare("
    SELECT contract_id, file_name, file_path
    FROM contract_documents
    WHERE contract_document_id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$doc) {
    http_response_code(404);
    exit('Document not found');
}

/*
   2️⃣ Permission check (optional but recommended)
*/
if (function_exists('can_manage_contract') && !can_manage_contract((int)$doc['contract_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

/*
   3️⃣ Build absolute path
*/
$relativePath = (string)$doc['file_path'];

if (!defined('DOCS_BASE_DIR')) {
    http_response_code(500);
    exit('DOCS_BASE_DIR not defined');
}
$baseDir = rtrim(get_system_setting('storage_base_dir', __DIR__ . '/storage'), '/');
$fullPath = $baseDir . '/' . ltrim($relativePath, '/');

/*
   4️⃣ Validate file exists
*/
if (!is_file($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    exit('File missing on server');
    
}

/*
   5️⃣ Detect mime type safely
*/
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
// finfo_close($finfo);

/*
   6️⃣ Stream file
*/
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
