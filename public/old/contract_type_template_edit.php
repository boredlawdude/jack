<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_superuser();

$pdo = pdo();
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) exit('Missing id');

$stmt = $pdo->prepare("
  SELECT contract_type_id, contract_type, template_file
  FROM contract_types
  WHERE contract_type_id = ?
");
$stmt->execute([$id]);
$type = $stmt->fetch();
if (!$type) exit('Not found');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Upload failed.';
  } else {
    $ext = strtolower(pathinfo($_FILES['template']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'docx') {
      $errors[] = 'Only DOCX files allowed.';
    }
  }

  if (!$errors) {
    $dir = __DIR__ . '/templates';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $safe = 'contract_type_' . $id . '.docx';
    $dest = $dir . '/' . $safe;

    move_uploaded_file($_FILES['template']['tmp_name'], $dest);

    $pdo->prepare("
      UPDATE contract_types
      SET template_file = ?
      WHERE contract_type_id = ?
    ")->execute([
      'templates/' . $safe,
      $id
    ]);

    header("Location: /contract_type_templates.php");
    exit;
  }
}

include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">Template – <?= h($type['contract_type']) ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card shadow-sm">
  <div class="card-body">

    <?php if ($type['template_file']): ?>
      <p>
        Current:
        <a href="/<?= h($type['template_file']) ?>" target="_blank">
          <?= basename($type['template_file']) ?>
        </a>
      </p>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Upload DOCX Template</label>
      <input class="form-control" type="file" name="template" accept=".docx" required>
    </div>

  </div>
  <div class="card-footer">
    <button class="btn btn-primary">Upload</button>
    <a class="btn btn-outline-secondary" href="/contract_type_templates.php">Back</a>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
