<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---- PERMISSIONS ----
// If your app uses "superuser" differently, swap this check.
// Options you might already have: is_superuser(), is_admin(), is_system_admin(), can_manage_templates()
if (function_exists('is_system_admin')) {
  if (!is_system_admin()) { http_response_code(403); exit('Forbidden'); }
} elseif (function_exists('is_admin')) {
  if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
} // else: allow (not recommended)

$DOCX_DIR_REL = 'templates/docx';
$HTML_DIR_REL = 'templates/html';
$DOCX_DIR_ABS = get_docx_template_dir();
$HTML_DIR_ABS = get_html_template_dir();


// Ensure folders exist
@mkdir($DOCX_DIR_ABS, 0775, true);
@mkdir($HTML_DIR_ABS, 0775, true);

$errors = [];
$okMsg = '';

/**
 * Normalize stored path:
 * - keep relative paths like "templates/docx/file.docx"
 * - allow absolute paths (but strongly prefer relative)
 */
function normalize_path(string $path): string {
  $path = trim($path);
  $path = str_replace('\\', '/', $path);
  // remove double slashes
  $path = preg_replace('#/+#', '/', $path) ?? $path;
  return $path;
}

function safe_filename(string $name): string {
  $name = basename($name);
  // keep letters, numbers, _, -, ., and spaces (then replace spaces)
  $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? $name;
  $name = str_replace(' ', '_', $name);
  return $name;
}

function ext(string $name): string {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

// -------------------- POST HANDLERS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $contract_type_id = (int)($_POST['contract_type_id'] ?? 0);

  if ($contract_type_id <= 0) {
    $errors[] = "Missing contract_type_id.";
  } else {
    if ($action === 'save_paths') {
      $docx = normalize_path((string)($_POST['template_file_docx'] ?? ''));
      $html = normalize_path((string)($_POST['template_file_html'] ?? ''));

      // Allow blank = no template set
      $up = $pdo->prepare("
        UPDATE contract_types
        SET template_file_docx = ?,
            template_file_html = ?
        WHERE contract_type_id = ?
        LIMIT 1
      ");
      $up->execute([
        ($docx !== '' ? $docx : null),
        ($html !== '' ? $html : null),
        $contract_type_id
      ]);

      $okMsg = "Saved template paths for contract type #{$contract_type_id}.";
    }

    elseif ($action === 'upload_docx') {
      if (!isset($_FILES['docx']) || $_FILES['docx']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "DOCX upload failed.";
      } else {
        $orig = (string)$_FILES['docx']['name'];
        if (ext($orig) !== 'docx') {
          $errors[] = "DOCX upload must be a .docx file.";
        } else {
          $fname = 'contract_type_' . $contract_type_id . '_' . date('Ymd_His') . '_' . safe_filename($orig);
          $destAbs = $DOCX_DIR_ABS . '/' . $fname;
          if (!move_uploaded_file($_FILES['docx']['tmp_name'], $destAbs)) {
            $errors[] = "Could not save DOCX to server.";
          } else {
            $destRel = $DOCX_DIR_REL . '/' . $fname;
            $up = $pdo->prepare("
              UPDATE contract_types
              SET template_file_docx = ?
              WHERE contract_type_id = ?
              LIMIT 1
            ");
            $up->execute([$destRel, $contract_type_id]);
            $okMsg = "Uploaded DOCX and saved to DB for contract type #{$contract_type_id}.";
          }
        }
      }
    }

    elseif ($action === 'upload_html') {
      if (!isset($_FILES['html']) || $_FILES['html']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "HTML upload failed.";
      } else {
        $orig = (string)$_FILES['html']['name'];
        $e = ext($orig);
        if (!in_array($e, ['html','htm'], true)) {
          $errors[] = "HTML upload must be a .html or .htm file.";
        } else {
          $fname = 'contract_type_' . $contract_type_id . '_' . date('Ymd_His') . '_' . safe_filename($orig);
          $destAbs = $HTML_DIR_ABS . '/' . $fname;
          if (!move_uploaded_file($_FILES['html']['tmp_name'], $destAbs)) {
            $errors[] = "Could not save HTML to server.";
          } else {
            $destRel = $HTML_DIR_REL . '/' . $fname;
            
            $up = $pdo->prepare("
              UPDATE contract_types
              SET template_file_html = ?
              WHERE contract_type_id = ?
              LIMIT 1
            ");
            $up->execute([$destRel, $contract_type_id]);
            $okMsg = "Uploaded HTML and saved to DB for contract type #{$contract_type_id}.";
          }
        }
      }
    }

    elseif ($action === 'clear_templates') {
      $up = $pdo->prepare("
        UPDATE contract_types
        SET template_file_docx= NULL,
            template_file_html = NULL
        WHERE contract_type_id = ?
        LIMIT 1
      ");
      $up->execute([$contract_type_id]);
      $okMsg = "Cleared templates for contract type #{$contract_type_id}.";
    }

    else {
      $errors[] = "Unknown action.";
    }
  }
}

// -------------------- LOAD DATA --------------------
$types = $pdo->query("
  SELECT
    contract_type_id,
    contract_type,
    description,
    is_active,
    template_file_docx,
    template_file_html
  FROM contract_types
  ORDER BY contract_type
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="d-flex align-items-center mb-3">
  <h1 class="h4 me-auto">Contract Type Templates</h1>
  <div class="text-muted small">
    Stored in DB fields: <code>template_file_html</code>, <code>template_file_html</code>
  </div>
</div>

<?php if ($okMsg): ?>
  <div class="alert alert-success py-2"><?= h($okMsg) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:70px;">ID</th>
          <th>Contract Type</th>
          <th style="width:32%;">DOCX Template (DB Path)</th>
          <th style="width:32%;">HTML Template (DB Path)</th>
          <th style="width:340px;" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($types as $t): ?>
        <?php
          $id = (int)$t['contract_type_id'];
          $docxPath = (string)($t['template_file_docx'] ?? '');
          $htmlPath = (string)($t['template_file_html'] ?? '');
        ?>
        <tr>
          <td><?= $id ?></td>
          <td>
            <div class="fw-semibold"><?= h($t['contract_type'] ?? '') ?></div>
            <?php if (!empty($t['description'])): ?>
              <div class="text-muted small"><?= h($t['description']) ?></div>
            <?php endif; ?>
          </td>

          <td>
            <form method="post" class="d-flex gap-2 align-items-start">
              <input type="hidden" name="action" value="save_paths">
              <input type="hidden" name="contract_type_id" value="<?= $id ?>">
              <input class="form-control form-control-sm"
                     name="template_file_docx"
                     placeholder="templates/docx/contract_type_<?= $id ?>.docx"
                     value="<?= h($docxPath) ?>">
              <!-- keep html in same save -->
              <input type="hidden" name="template_file_html" value="<?= h($htmlPath) ?>">
              <button class="btn btn-sm btn-outline-primary">Save</button>
            </form>

            <form method="post" enctype="multipart/form-data" class="mt-2 d-flex gap-2">
              <input type="hidden" name="action" value="upload_docx">
              <input type="hidden" name="contract_type_id" value="<?= $id ?>">
              <input class="form-control form-control-sm" type="file" name="docx" accept=".docx">
              <button class="btn btn-sm btn-outline-secondary">Upload</button>
            </form>

            <?php if ($docxPath !== ''): ?>
              <div class="text-muted small mt-1">
                Saved: <code><?= h($docxPath) ?></code>
              </div>
            <?php endif; ?>
          </td>

          <td>
            <form method="post" class="d-flex gap-2 align-items-start">
              <input type="hidden" name="action" value="save_paths">
              <input type="hidden" name="contract_type_id" value="<?= $id ?>">
              <input type="hidden" name="template_file_docx" value="<?= h($docxPath) ?>">
              <input class="form-control form-control-sm"
                     name="template_file_html"
                     placeholder="templates/html/contract_type_<?= $id ?>.html"
                     value="<?= h($htmlPath) ?>">
              <button class="btn btn-sm btn-outline-primary">Save</button>
            </form>

            <form method="post" enctype="multipart/form-data" class="mt-2 d-flex gap-2">
              <input type="hidden" name="action" value="upload_html">
              <input type="hidden" name="contract_type_id" value="<?= $id ?>">
              <input class="form-control form-control-sm" type="file" name="html" accept=".html,.htm,text/html">
              <button class="btn btn-sm btn-outline-secondary">Upload</button>
            </form>

            <?php if ($htmlPath !== ''): ?>
              <div class="text-muted small mt-1">
                Saved: <code><?= h($htmlPath) ?></code>
              </div>
            <?php endif; ?>
          </td>

          <td class="text-end">
            <form method="post" class="d-inline"
                  onsubmit="return confirm('Clear both templates for this contract type?');">
              <input type="hidden" name="action" value="clear_templates">
              <input type="hidden" name="contract_type_id" value="<?= $id ?>">
              <button class="btn btn-sm btn-outline-danger">Clear</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$types): ?>
        <tr><td colspan="5" class="text-muted p-3">No contract types found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="text-muted small mt-3">
  Upload folders:
  <code><?= h($DOCX_DIR_REL) ?></code> and <code><?= h($HTML_DIR_REL) ?></code>
</div>

<?php include __DIR__ . '/footer.php'; ?>
