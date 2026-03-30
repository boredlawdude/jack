<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$c = $pdo->prepare("SELECT contract_number, name, contract_body_html FROM contracts WHERE contract_id = ? LIMIT 1");
$c->execute([$contract_id]);
$contract = $c->fetch();
if (!$contract) { http_response_code(404); exit('Not found'); }
$contract_id = (int)($_GET['id'] ?? 0);
if ($contract_id <= 0) { http_response_code(400); exit('Missing contract id'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $html = (string)($_POST['contract_body_html'] ?? '');

  // Save latest
  $pdo->prepare("UPDATE contracts SET contract_body_html = ? WHERE contract_id = ?")
      ->execute([$html, $contract_id]);

  // Save version snapshot (recommended)
  $pdo->prepare("
    INSERT INTO contract_body_versions (contract_id, body_html, created_by_person_id)
    VALUES (?, ?, ?)
  ")->execute([$contract_id, $html, function_exists('current_person_id') ? current_person_id() : null]);

  header("Location: /contract_body_edit.php?id=" . $contract_id);
  exit;
}

$c = $pdo->prepare("SELECT contract_number, name, contract_body_html FROM contracts WHERE contract_id = ? LIMIT 1");
$c->execute([$contract_id]);
$contract = $c->fetch();
if (!$contract) { http_response_code(404); exit('Not found'); }

include __DIR__ . '/header.php';
?>

<h1 class="h4 mb-3">Edit Contract Body</h1>
<div class="text-muted small mb-3">
  <?= h($contract['contract_number'] ?? '') ?> — <?= h($contract['name'] ?? '') ?>
</div>

<form method="post">
  <textarea id="editor" name="contract_body_html"><?= h($contract['contract_body_html'] ?? '') ?></textarea>
  <button class="btn btn-primary mt-3">Save</button>
  <a class="btn btn-outline-secondary mt-3" href="/contract_edit.php?id=<?= (int)$contract_id ?>">Back</a>
</form>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
tinymce.init({
  selector: '#editor',
  height: 600,
  menubar: true,
  plugins: 'lists link table code searchreplace paste wordcount',
  toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | searchreplace | code',
  paste_as_text: false
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
