<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$contract_id = (int)($_POST['id'] ?? 0);
if ($contract_id <= 0) { http_response_code(400); exit('Missing contract_id'); }

if (!can_manage_contract($contract_id)) {
  http_response_code(403);
  exit('Forbidden');
}

$title = trim((string)($_POST['title'] ?? ''));
$label = trim((string)($_POST['exhibit_label'] ?? ''));

if ($title === '') { http_response_code(400); exit('Title required'); }

if (!isset($_FILES['pdf']) || ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  http_response_code(400);
  exit('Upload failed');
}

$tmp = $_FILES['pdf']['tmp_name'];
$origName = (string)($_FILES['pdf']['name'] ?? 'exhibit.pdf');
$size = (int)($_FILES['pdf']['size'] ?? 0);

if ($size <= 0) { http_response_code(400); exit('Empty file'); }

// Basic server-side PDF check
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp) ?: 'application/octet-stream';
if ($mime !== 'application/pdf') {
  http_response_code(400);
  exit('Only PDF files allowed');
}

// Size limit (tune as you like)
$MAX = 20 * 1024 * 1024; // 20MB
if ($size > $MAX) {
  http_response_code(400);
  exit('PDF too large (max 20MB)');
}

$bytes = file_get_contents($tmp);
if ($bytes === false) { http_response_code(500); exit('Could not read upload'); }

$sha256 = hash('sha256', $bytes);

$stmt = $pdo->prepare("
  INSERT INTO contract_exhibits
    (contract_id, exhibit_label, title, file_name, mime_type, file_size, sha256, pdf_blob, uploaded_by_person_id)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bindValue(1, $contract_id, PDO::PARAM_INT);
$stmt->bindValue(2, $label !== '' ? $label : null, PDO::PARAM_STR);
$stmt->bindValue(3, $title, PDO::PARAM_STR);
$stmt->bindValue(4, $origName, PDO::PARAM_STR);
$stmt->bindValue(5, 'application/pdf', PDO::PARAM_STR);
$stmt->bindValue(6, $size, PDO::PARAM_INT);
$stmt->bindValue(7, $sha256, PDO::PARAM_STR);
$stmt->bindValue(8, $bytes, PDO::PARAM_LOB);
$stmt->bindValue(9, current_person_id(), PDO::PARAM_INT);

$stmt->execute();

header("Location: /contract_edit.php?id=" . $contract_id);
exit('This endpoint is deprecated. Please use the Contracts/Exhibits section in the main app.');
