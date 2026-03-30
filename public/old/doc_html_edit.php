<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function doc_abs_path(string $file_path): string {
  // If file_path is relative, resolve under DOCS_BASE_DIR
  if (defined('DOCS_BASE_DIR') && $file_path !== '' && $file_path[0] !== '/') {
    return rtrim((string)DOCS_BASE_DIR, '/') . '/' . ltrim($file_path, '/');
  }
  return $file_path; // already absolute
}

$doc_id = (int)($_GET['id'] ?? 0);
if ($doc_id <= 0) { http_response_code(400); exit('Missing document id'); }

// Load doc row
$st = $pdo->prepare("
  SELECT contract_document_id, contract_id, file_name, file_path, mime_type
  FROM contract_documents
  WHERE contract_document_id = ?
  LIMIT 1
");
$st->execute([$doc_id]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); exit('Document not found'); }

// Permission
if (function_exists('can_manage_contract') && !can_manage_contract((int)$doc['contract_id'])) {
  http_response_code(403); exit('Forbidden');
}

// Resolve file on disk
$abs = doc_abs_path((string)$doc['file_path']);
if (!is_file($abs) || !is_readable($abs)) { http_response_code(404); exit('File not found on disk'); }
if (!is_writable($abs)) { http_response_code(500); exit('File is not writable'); }

$errors = [];
$notice = null;

// SAVE (writes to disk + logs revision)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_html') {
  $newHtml = (string)($_POST['html'] ?? '');

  $oldHtml = (string)file_get_contents($abs);
  if ($oldHtml !== $newHtml) {
    $ins = $pdo->prepare("
      INSERT INTO contract_html_revisions
        (document_id, contract_id, created_by_person_id, old_html, new_html)
      VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([
      (int)$doc['contract_document_id'],
      (int)$doc['contract_id'],
      function_exists('current_person_id') ? current_person_id() : null,
      $oldHtml,
      $newHtml
    ]);

    file_put_contents($abs, $newHtml);
    $notice = "Saved.";
  } else {
    $notice = "No changes to save.";
  }
}

// REVERT (writes old_html to disk + logs revision of the revert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revert_html') {
  $rid = (int)($_POST['revision_id'] ?? 0);
  if ($rid <= 0) { $errors[] = "Missing revision id."; }

  if (!$errors) {
    $rv = $pdo->prepare("
      SELECT revision_id, old_html
      FROM contract_html_revisions
      WHERE revision_id = ?
        AND document_id = ?
      LIMIT 1
    ");
    $rv->execute([$rid, (int)$doc['contract_document_id']]);
    $row = $rv->fetch(PDO::FETCH_ASSOC);
    if (!$row) $errors[] = "Revision not found.";

    if (!$errors) {
      $current = (string)file_get_contents($abs);
      $target  = (string)$row['old_html'];

      if ($current !== $target) {
        // log the revert as its own revision (current -> target)
        $ins = $pdo->prepare("
          INSERT INTO contract_html_revisions
            (document_id, contract_id, created_by_person_id, old_html, new_html)
          VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([
          (int)$doc['contract_document_id'],
          (int)$doc['contract_id'],
          function_exists('current_person_id') ? current_person_id() : null,
          $current,
          $target
        ]);

        file_put_contents($abs, $target);
        $notice = "Reverted.";
      } else {
        $notice = "Already at that version.";
      }
    }
  }
}

// Load latest file content for editor
$html = (string)file_get_contents($abs);

// Load revisions list
$revQ = $pdo->prepare("
  SELECT r.revision_id, r.created_at,
         u.full_name AS created_by_name,
         u.email AS created_by_email
  FROM contract_html_revisions r
  LEFT JOIN people u ON u.person_id = r.created_by_person_id
  WHERE r.document_id = ?
  ORDER BY r.created_at DESC, r.revision_id DESC
  LIMIT 50
");
$revQ->execute([(int)$doc['contract_document_id']]);
$revs = $revQ->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Edit HTML Document</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="/contract_edit.php?id=<?= (int)$doc['contract_id'] ?>">Back to Contract</a>
    <a class="btn btn-outline-secondary btn-sm" href="/download_doc.php?id=<?= (int)$doc['contract_document_id'] ?>">Download</a>
  </div>
</div>

<div class="mb-2 text-muted small">
  <div><strong>File:</strong> <?= h($doc['file_name'] ?? '') ?></div>
  <div><strong>Path:</strong> <?= h($doc['file_path'] ?? '') ?></div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-success py-2"><?= h($notice) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" class="card shadow-sm">
      <input type="hidden" name="action" value="save_html">
      <div class="card-header fw-semibold">HTML</div>
      <div class="card-body">
        <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
            <script>
            tinymce.init({
              selector: '#wysiwyg',
              height: 600,
              menubar: true,
              plugins: 'lists link table code searchreplace visualblocks wordcount',
              toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | removeformat | code',
              convert_urls: false,
              branding: false
            });
            </script>
              <textarea id="wysiwyg" name="html"><?= h($html) ?></textarea>
              <div class="form-text">This is WYSIWYG. Click “Save” to write to disk and log a revision.</div>

        <div class="form-text">Mode A: saving writes to disk immediately and logs a revision.</div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-outline-secondary" href="/contract_edit.php?id=<?= (int)$doc['contract_id'] ?>">Done</a>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Revision History (last 50)</div>
      <div class="card-body p-0">
        <?php if (!$revs): ?>
          <div class="p-3 text-muted">No revisions yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>When</th>
                  <th>Who</th>
                  <th style="width:110px;"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($revs as $r): ?>
                  <tr>
                    <td class="small"><?= h($r['created_at']) ?></td>
                    <td class="small">
                      <?= h($r['created_by_name'] ?? '') ?>
                      <?php if (!empty($r['created_by_email'])): ?>
                        <div class="text-muted"><?= h($r['created_by_email']) ?></div>
                      <?php endif; ?>
                      <td>
                      <a class="btn btn-sm btn-outline-secondary"
                      href="/doc_html_diff.php?rev_id=<?= (int)$r['revision_id'] ?>">
                      Show diff
                      </a>
                      </td>
                    </td>
                    <td class="text-end">
                      <form method="post" onsubmit="return confirm('Revert this document to the PREVIOUS version stored for this revision?');">
                        <input type="hidden" name="action" value="revert_html">
                        <input type="hidden" name="revision_id" value="<?= (int)$r['revision_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Revert</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="alert alert-info mt-3 small">
      <strong>How “Revert” works:</strong> it writes the saved <code>old_html</code> for that revision back to the file on disk
      and logs that revert as a new revision.
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
