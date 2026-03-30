<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $html = (string)($_POST['contract_body_html'] ?? '');
  $pdo->prepare("UPDATE contracts SET contract_body_html = ? WHERE contract_id = ?")
      ->execute([$html, $contract_id]);
  header("Location: /contract_body_edit_custom.php?id=" . $contract_id);
  exit;
}


include __DIR__ . '/header.php';
$contract_id = (int)($_GET['id'] ?? 0);
if ($contract_id <= 0) { http_response_code(400); exit('Missing contract id'); }

?>
<h1 class="h4 mb-3">Contract Body Editor</h1>

<form method="post" onsubmit="syncHtml()">
  <div class="btn-group mb-2" role="group">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('bold')"><b>B</b></button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('italic')"><i>I</i></button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('underline')"><u>U</u></button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('insertUnorderedList')">• List</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('insertOrderedList')">1. List</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('formatBlock','h2')">H2</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cmd('formatBlock','p')">P</button>
  </div>

  <div id="editor"
       contenteditable="true"
       class="border rounded bg-white p-3"
       style="min-height: 500px;">
    <?= $body /* already trusted HTML you've stored */ ?>
  </div>

  <input type="hidden" name="contract_body_html" id="contract_body_html">

  <button class="btn btn-primary mt-3">Save</button>
  <a class="btn btn-outline-secondary mt-3" href="/contract_edit.php?id=<?= (int)$contract_id ?>">Back</a>
</form>

<script>
function cmd(command, value = null) {
  document.execCommand(command, false, value);
  document.getElementById('editor').focus();
}
function syncHtml() {
  document.getElementById('contract_body_html').value =
    document.getElementById('editor').innerHTML;
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
