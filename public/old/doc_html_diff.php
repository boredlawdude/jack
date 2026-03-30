<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$rev_id = (int)($_GET['rev_id'] ?? 0);
if ($rev_id <= 0) { http_response_code(400); exit('Missing rev_id'); }

/**
 * IMPORTANT: Update table/column names below to match yours.
 * I’m assuming a table like: doc_html_revisions
 * with: revision_id, contract_document_id, before_html, after_html, saved_at, saved_by_person_id
 */
$st = $pdo->prepare("
  SELECT
    r.revision_id,
    r.document_id,
    r.old_html,
    r.new_html,
    r.created_at,
    r.created_by_person_id,
    cd.contract_id,
    cd.file_name
  FROM contract_html_revisions r
  JOIN contract_documents cd ON cd.contract_document_id = r.document_id
  WHERE r.revision_id = ?
  LIMIT 1
");
$st->execute([$rev_id]);
$rev = $st->fetch(PDO::FETCH_ASSOC);

if (!$rev) { http_response_code(404); exit('Revision not found'); }

// Permission check (use your existing helper if present)
if (function_exists('can_view_contract') && !can_view_contract((int)$rev['contract_id'])) {
  http_response_code(403);
  exit('Forbidden');
}

$before = (string)($rev['old_html'] ?? '');
$after  = (string)($rev['new_html'] ?? '');

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h5 me-auto mb-0">HTML Diff</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm"
       href="/doc_html_edit.php?id=<?= (int)$rev['document_id'] ?>">
      Back to Editor
    </a>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="small text-muted">
      Document: <strong><?= h($rev['file_name'] ?? '') ?></strong><br>
      Revision #<?= (int)$rev['revision_id'] ?> • Saved: <?= h($rev['saved_at'] ?? '') ?>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex gap-2 mb-3">
      <button class="btn btn-sm btn-outline-primary" onclick="renderDiff('pretty')">Pretty diff</button>
      <button class="btn btn-sm btn-outline-secondary" onclick="renderDiff('raw')">Raw text diff</button>
    </div>

    <div id="diffOut" class="border rounded p-3 bg-white" style="min-height:300px;"></div>

    <textarea id="beforeHtml" style="display:none;"><?= htmlspecialchars($before, ENT_NOQUOTES, 'UTF-8'); ?></textarea>
    <textarea id="afterHtml" style="display:none;"><?= htmlspecialchars($after, ENT_NOQUOTES, 'UTF-8'); ?></textarea>
  </div>
</div>

<!-- diff-match-patch -->
<script src="https://cdn.jsdelivr.net/npm/diff-match-patch@1.0.5/index.min.js"></script>
<script>
function renderDiff(mode){
  const before = document.getElementById('beforeHtml').value || '';
  const after  = document.getElementById('afterHtml').value || '';

  const dmp = new diff_match_patch();
  // speed + readability tuning
  dmp.Diff_Timeout = 1.0;

  // If your HTML is Word-generated garbage, diffing can be noisy.
  // This helps a lot by diffing “pretty printed” text:
  const a = (mode === 'pretty') ? normalizeHtml(before) : before;
  const b = (mode === 'pretty') ? normalizeHtml(after)  : after;

  const diffs = dmp.diff_main(a, b);
  dmp.diff_cleanupSemantic(diffs);

  const html = dmp.diff_prettyHtml(diffs);
  document.getElementById('diffOut').innerHTML = html;
}

// very lightweight "pretty" formatter (not a full HTML formatter)
function normalizeHtml(s){
  return (s || '')
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+/g, ' ')
    .replace(/>\s+</g, '>\n<')
    .trim();
}

renderDiff('pretty');
</script>

<style>
/* diff-match-patch uses these tags */
#diffOut del { background: #ffe6e6; text-decoration: none; }
#diffOut ins { background: #e6ffe6; text-decoration: none; }
</style>

<?php include __DIR__ . '/footer.php'; ?>
