<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();

$doc_id = (int)($_GET['doc_id'] ?? 0);
if ($doc_id <= 0) { http_response_code(400); exit('Invalid doc'); }

$q = $pdo->prepare("
  SELECT cd.*, c.contract_id
  FROM contract_documents cd
  JOIN contracts c ON c.contract_id = cd.contract_id
  WHERE cd.contract_document_id = ?
  LIMIT 1
");
$q->execute([$doc_id]);
$doc = $q->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); exit('Not found'); }

// Permission check (use your real helper; this is fine)
if (function_exists('can_view_contract') && !can_view_contract((int)$doc['contract_id'])) {
  http_response_code(403); exit('Forbidden');
}

/* ---- Signed URLs for OnlyOffice (Docker -> host) ---- */
if (!function_exists('oo_sign')) {
  http_response_code(500);
  exit('oo_sign() not defined');
}

$docSig = oo_sign(['id' => $doc_id]);
$cbSig  = oo_sign(['doc_id' => $doc_id]);

$docUrl      = "http://host.docker.internal:9000/download_doc.php?id=$doc_id&sig=$docSig";
$callbackUrl = "http://host.docker.internal:9000/onlyoffice_callback.php?doc_id=$doc_id&sig=$cbSig";

/* ---- Resolve absolute file path for stable key ---- */
$path = (string)($doc['file_path'] ?? '');
if ($path === '') { http_response_code(500); exit('Empty file_path'); }

if (defined('DOCS_BASE_DIR') && $path !== '' && $path[0] !== '/') {
  $abs = rtrim((string)DOCS_BASE_DIR, '/') . '/' . ltrim($path, '/');
} else {
  $abs = $path; // already absolute
}

$mtime = @filemtime($abs);
if ($mtime === false) { $mtime = time(); } // don’t break editor if missing

// OnlyOffice "document.key" must be <= 128 chars and change when file changes.
$keyMaterial = $doc_id . '|' . $path . '|' . (string)$mtime;
$key = substr(hash('sha256', $keyMaterial), 0, 64);

/* ---- Optional JWT support (if your DS is started with JWT enabled) ----
   If you used:
     -e JWT_ENABLED=true -e JWT_SECRET=...
   then set ONLYOFFICE_JWT_SECRET in config.php and this will work.
*/
$jwtSecret = defined('ONLYOFFICE_JWT_SECRET') ? (string)ONLYOFFICE_JWT_SECRET : '';
$token = null;

$config = [
  'document' => [
    'fileType' => 'docx',
    'key'      => $key,
    'title'    => (string)($doc['file_name'] ?? ('Document_' . $doc_id . '.docx')),
    'url'      => $docUrl,
  ],
  'editorConfig' => [
    'callbackUrl' => $callbackUrl,
    'mode'        => 'edit',
    // Optional: show user name in editor
    'user' => [
      'id'   => (string)(current_person_id() ?? '0'),
      'name' => (string)((current_person()['name'] ?? current_person()['full_name'] ?? current_person()['email'] ?? 'User')),
    ],
  ],
];

// If JWT enabled, sign the config
if ($jwtSecret !== '' && function_exists('oo_jwt_sign')) {
  $token = oo_jwt_sign($config, $jwtSecret); // you can add this helper if needed
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Document</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="http://localhost:8081/web-apps/apps/api/documents/api.js"></script>
</head>
<body style="margin:0">
<div id="editor" style="width:100%; height:100vh;"></div>

<script>
const cfg = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;
<?php if ($token): ?>
cfg.token = <?= json_encode($token) ?>;
<?php endif; ?>

new DocsAPI.DocEditor("editor", cfg);
</script>

</body>
</html>
