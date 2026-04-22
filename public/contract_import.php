<?php
declare(strict_types=1);
/**
 * contract_import.php
 *
 * Bulk-import contracts from a CSV exported from the legacy LF Forms spreadsheet.
 *
 * Expected CSV columns (in any order, matched by header name):
 *   Request Date | Contract Manager | Dept | Vendor Legal Name | Total Cost |
 *   Contract Term | Contract Type | LF Forms Status | Current Step |
 *   Current Step Start Date | Assigned To | Contract Request Status |
 *   Description of Purchase or Service | Requester | Purchase Requisition |
 *   Vendor ID | Vendor Contact Name | Vendor Contact Title |
 *   Vendor Contact Phone | Vendor Contact Email | MSA | Renewal |
 *   Additional Information | Instance ID
 *
 * Access: ADMIN / SUPERUSER only.
 * To use: export the SharePoint Excel as CSV (UTF-8) and upload here.
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_ROOT . '/includes/init.php';
require_once APP_ROOT . '/includes/auth.php';

require_login();
require_system_admin();

// ── helpers ───────────────────────────────────────────────────────────────────
if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Parse a dollar/comma amount string to float|null */
function parse_amount(string $v): ?float
{
    $clean = preg_replace('/[^0-9.\-]/', '', $v);
    if ($clean === '' || $clean === '-') return null;
    return (float)$clean;
}

/** Parse various date formats to YYYY-MM-DD or null */
function parse_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '' || strtolower($v) === 'n/a') return null;
    // Try common US formats first
    foreach (['m/d/Y', 'm/d/y', 'Y-m-d', 'n/j/Y', 'n/j/y', 'F j, Y', 'j-M-Y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $v);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
    }
    // strtotime fallback
    $ts = strtotime($v);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return null;
}

/** Strip UTF-8 BOM from string */
function strip_bom(string $s): string
{
    if (str_starts_with($s, "\xEF\xBB\xBF")) {
        return substr($s, 3);
    }
    return $s;
}

/** Normalise a header string: lowercase, no extra spaces */
function norm(string $s): string
{
    return strtolower(trim($s));
}

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['import_csrf'])) {
    $_SESSION['import_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['import_csrf'];

// ── Load lookup tables once ───────────────────────────────────────────────────
$pdo = db();

$depts = $pdo->query("SELECT department_id, department_code, department_name FROM departments WHERE is_active = 1")
    ->fetchAll(PDO::FETCH_ASSOC);
$deptByName = [];
$deptByCode = [];
foreach ($depts as $d) {
    $deptByName[norm($d['department_name'])] = (int)$d['department_id'];
    $deptByCode[norm($d['department_code'])] = (int)$d['department_id'];
}

$contractTypes = $pdo->query("SELECT contract_type_id, contract_type FROM contract_types WHERE is_active = 1")
    ->fetchAll(PDO::FETCH_ASSOC);
$typeByName = [];
foreach ($contractTypes as $ct) {
    $typeByName[norm($ct['contract_type'])] = (int)$ct['contract_type_id'];
}

$statuses = $pdo->query("SELECT contract_status_id, contract_status_name FROM contract_statuses")
    ->fetchAll(PDO::FETCH_ASSOC);
$statusByName = [];
foreach ($statuses as $s) {
    $statusByName[norm($s['contract_status_name'])] = (int)$s['contract_status_id'];
}

$people = $pdo->query("SELECT person_id, full_name, display_name, first_name, last_name FROM people WHERE is_active = 1")
    ->fetchAll(PDO::FETCH_ASSOC);
$personByName = [];
foreach ($people as $p) {
    $fullName = trim($p['full_name'] ?? '');
    if ($fullName === '') {
        $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
    }
    if ($fullName !== '') {
        $personByName[norm($fullName)] = (int)$p['person_id'];
    }
    $display = trim($p['display_name'] ?? '');
    if ($display !== '') {
        $personByName[norm($display)] = (int)$p['person_id'];
    }
}

// ── Phase: DELETE imported records ───────────────────────────────────────────
$deleteResult = null;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete_imported'
    && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))
    && ($_POST['confirm_delete'] ?? '') === 'DELETE'
) {
    $del = $pdo->exec("DELETE FROM contracts WHERE is_imported = 1");
    $deleteResult = (int)$del;
}

// ── Phase: CONFIRM & INSERT ───────────────────────────────────────────────────
$importResult = null;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'do_import'
    && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))
) {
    $rowsJson = $_POST['rows_json'] ?? '';
    $rows     = json_decode($rowsJson, true);

    if (!is_array($rows) || count($rows) === 0) {
        $importResult = ['error' => 'No data to import.'];
    } else {
        $inserted = 0;
        $skipped  = [];

        $insertSql = "
            INSERT INTO contracts
                (contract_number, name, description, contract_status_id,
                 department_id, contract_type_id, counterparty_company_id,
                 owner_primary_contact_id, total_contract_value, start_date,
                 bid_rfp_number, auto_renew, procurement_notes, is_imported)
            VALUES
                (:contract_number, :name, :description, :contract_status_id,
                 :department_id, :contract_type_id, :counterparty_company_id,
                 :owner_primary_contact_id, :total_contract_value, :start_date,
                 :bid_rfp_number, :auto_renew, :procurement_notes, 1)
        ";
        $stmt = $pdo->prepare($insertSql);

        foreach ($rows as $idx => $row) {
            $rowNum = (int)$row['_row'];

            // Find or create vendor company
            $vendorName = trim((string)($row['vendor_name'] ?? ''));
            $vendorId   = null;
            if ($vendorName !== '') {
                $compStmt = $pdo->prepare("SELECT company_id FROM companies WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $compStmt->execute([$vendorName]);
                $existing = $compStmt->fetchColumn();
                if ($existing !== false) {
                    $vendorId = (int)$existing;
                    // Update vendor-supplied fields if missing
                    $vContact = trim((string)($row['vendor_contact_name'] ?? ''));
                    $vPhone   = trim((string)($row['vendor_contact_phone'] ?? ''));
                    $vEmail   = trim((string)($row['vendor_contact_email'] ?? ''));
                    $vVendorId = trim((string)($row['vendor_id'] ?? ''));
                    $updateParts = [];
                    $updateParams = [];
                    if ($vContact !== '') { $updateParts[] = "contact_name = COALESCE(NULLIF(contact_name,''), ?)"; $updateParams[] = $vContact; }
                    if ($vPhone !== '')   { $updateParts[] = "phone = COALESCE(NULLIF(phone,''), ?)";               $updateParams[] = $vPhone; }
                    if ($vEmail !== '')   { $updateParts[] = "email = COALESCE(NULLIF(email,''), ?)";               $updateParams[] = $vEmail; }
                    if ($vVendorId !== ''){ $updateParts[] = "vendor_id = COALESCE(NULLIF(vendor_id,''), ?)";       $updateParams[] = $vVendorId; }
                    if (!empty($updateParts)) {
                        $updateParams[] = $vendorId;
                        $pdo->prepare("UPDATE companies SET " . implode(', ', $updateParts) . " WHERE company_id = ?")
                            ->execute($updateParams);
                    }
                } else {
                    // Create new vendor company
                    $ins = $pdo->prepare("
                        INSERT INTO companies (name, type, vendor_id, contact_name, phone, email)
                        VALUES (?, 'vendor', ?, ?, ?, ?)
                    ");
                    $ins->execute([
                        $vendorName,
                        $row['vendor_id'] ?: null,
                        $row['vendor_contact_name'] ?: null,
                        $row['vendor_contact_phone'] ?: null,
                        $row['vendor_contact_email'] ?: null,
                    ]);
                    $vendorId = (int)$pdo->lastInsertId();
                }
            }

            // Build procurement_notes composite
            $notes = [];
            if (!empty($row['lf_status']))        $notes[] = "LF Forms Status: " . $row['lf_status'];
            if (!empty($row['contract_term']))     $notes[] = "Contract Term: " . $row['contract_term'];
            if (!empty($row['request_date']))      $notes[] = "Request Date: " . $row['request_date'];
            if (!empty($row['requester']))         $notes[] = "Requester: " . $row['requester'];
            if (!empty($row['contract_req_status'])) $notes[] = "Contract Request Status: " . $row['contract_req_status'];
            if (!empty($row['vendor_contact_title'])) $notes[] = "Vendor Contact Title: " . $row['vendor_contact_title'];
            if (!empty($row['additional_info']))   $notes[] = "Additional Info: " . $row['additional_info'];
            if (!empty($row['msa']))               $notes[] = "MSA: " . $row['msa'];
            $procNotes = empty($notes) ? null : implode("\n", $notes);

            // Contract name: use description, fall back to vendor + type
            $contractName = trim((string)($row['description'] ?? ''));
            if ($contractName === '') {
                $contractName = trim($vendorName . (!empty($row['contract_type']) ? ' — ' . $row['contract_type'] : ''));
            }
            if ($contractName === '') {
                $contractName = 'Imported Contract (Row ' . $rowNum . ')';
            }
            // Truncate to 255 chars
            $contractName = mb_substr($contractName, 0, 255);

            // contract_number uniqueness: skip if already exists
            $contractNumber = trim((string)($row['instance_id'] ?? ''));
            if ($contractNumber !== '') {
                $ck = $pdo->prepare("SELECT 1 FROM contracts WHERE contract_number = ? LIMIT 1");
                $ck->execute([$contractNumber]);
                if ($ck->fetchColumn() !== false) {
                    $skipped[] = "Row $rowNum: Instance ID \"$contractNumber\" already exists — skipped.";
                    continue;
                }
            }

            try {
                $stmt->execute([
                    ':contract_number'            => $contractNumber ?: null,
                    ':name'                       => $contractName,
                    ':description'                => trim((string)($row['description'] ?? '')) ?: null,
                    ':contract_status_id'         => $row['contract_status_id'] ?: null,
                    ':department_id'              => $row['department_id'] ?: null,
                    ':contract_type_id'           => $row['contract_type_id'] ?: null,
                    ':counterparty_company_id'    => $vendorId,
                    ':owner_primary_contact_id'   => $row['owner_contact_id'] ?: null,
                    ':total_contract_value'       => $row['total_cost'] ?: null,
                    ':start_date'                 => $row['start_date'] ?: null,
                    ':bid_rfp_number'             => trim((string)($row['purchase_req'] ?? '')) ?: null,
                    ':auto_renew'                 => (int)($row['auto_renew'] ?? 0),
                    ':procurement_notes'          => $procNotes,
                ]);
                $inserted++;
            } catch (PDOException $e) {
                $skipped[] = "Row $rowNum: DB error — " . $e->getMessage();
            }
        }

        $importResult = ['inserted' => $inserted, 'skipped' => $skipped];
    }
}

// ── Phase: PARSE UPLOADED CSV → build preview rows ───────────────────────────
$parseError  = null;
$previewRows = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'preview'
    && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))
) {
    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $parseError = 'No file uploaded or upload error (code ' . ($_FILES['csv_file']['error'] ?? '?') . ').';
    } else {
        $mime = $_FILES['csv_file']['type'] ?? '';
        $origName = $_FILES['csv_file']['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $parseError = 'Please upload a CSV file (.csv). In Excel: File → Save As → CSV UTF-8.';
        } else {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $raw     = file_get_contents($tmpPath);
            if ($raw === false) {
                $parseError = 'Could not read uploaded file.';
            } else {
                $raw = strip_bom($raw);
                // Normalise line endings
                $raw = str_replace(["\r\n", "\r"], "\n", $raw);

                // Parse CSV from string
                $lines = explode("\n", $raw);
                $parsed = [];
                foreach ($lines as $line) {
                    if (trim($line) === '') continue;
                    $parsed[] = str_getcsv($line, ',', '"', '\\');
                }

                if (count($parsed) < 2) {
                    $parseError = 'CSV has no data rows.';
                } else {
                    // Build header map
                    $headers = array_map('norm', $parsed[0]);
                    $col = array_flip($headers); // norm_header → index

                    $get = function (array $row, string $key) use ($col): string {
                        $idx = $col[$key] ?? null;
                        if ($idx === null) return '';
                        return trim((string)($row[$idx] ?? ''));
                    };

                    for ($i = 1; $i < count($parsed); $i++) {
                        $r = $parsed[$i];
                        if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) continue;

                        // Lookup helpers
                        $deptRaw  = $get($r, 'dept');
                        $deptId   = $deptByCode[norm($deptRaw)] ?? $deptByName[norm($deptRaw)] ?? null;

                        $typeRaw  = $get($r, 'contract type');
                        $typeId   = null;
                        foreach ($typeByName as $typeName => $tid) {
                            if (str_contains($typeName, norm($typeRaw)) || str_contains(norm($typeRaw), $typeName)) {
                                $typeId = $tid;
                                break;
                            }
                        }
                        if ($typeId === null && $typeRaw !== '') {
                            $typeId = $typeByName[norm($typeRaw)] ?? null;
                        }

                        $stepRaw      = $get($r, 'current step');
                        $statusId     = $statusByName[norm($stepRaw)] ?? null;
                        // partial match fallback
                        if ($statusId === null && $stepRaw !== '') {
                            foreach ($statusByName as $sName => $sid) {
                                if (str_contains($sName, norm($stepRaw)) || str_contains(norm($stepRaw), $sName)) {
                                    $statusId = $sid;
                                    break;
                                }
                            }
                        }

                        $mgr     = $get($r, 'contract manager');
                        $assignedTo = $get($r, 'assigned to');
                        $personKey = norm($mgr ?: $assignedTo);
                        $personId  = $personByName[$personKey] ?? null;

                        $renewal = strtolower($get($r, 'renewal'));
                        $autoRenew = in_array($renewal, ['yes', 'y', '1', 'true'], true) ? 1 : 0;

                        $totalCost = parse_amount($get($r, 'total cost'));
                        $startDate = parse_date($get($r, 'current step start date'));

                        $previewRows[] = [
                            '_row'                => $i + 1,
                            'instance_id'         => $get($r, 'instance id'),
                            'description'         => $get($r, 'description of purchase or service'),
                            'vendor_name'         => $get($r, 'vendor legal name'),
                            'vendor_id'           => $get($r, 'vendor id'),
                            'vendor_contact_name' => $get($r, 'vendor contact name'),
                            'vendor_contact_title'=> $get($r, 'vendor contact title'),
                            'vendor_contact_phone'=> $get($r, 'vendor contact phone'),
                            'vendor_contact_email'=> $get($r, 'vendor contact email'),
                            'dept_raw'            => $deptRaw,
                            'department_id'       => $deptId,
                            'contract_type'       => $typeRaw,
                            'contract_type_id'    => $typeId,
                            'current_step'        => $stepRaw,
                            'contract_status_id'  => $statusId,
                            'owner_contact_id'    => $personId,
                            'contract_manager'    => $mgr,
                            'total_cost'          => $totalCost,
                            'start_date'          => $startDate,
                            'auto_renew'          => $autoRenew,
                            'purchase_req'        => $get($r, 'purchase requisition'),
                            'request_date'        => $get($r, 'request date'),
                            'requester'           => $get($r, 'requester'),
                            'contract_term'       => $get($r, 'contract term'),
                            'lf_status'           => $get($r, 'lf forms status'),
                            'contract_req_status' => $get($r, 'contract request status'),
                            'msa'                 => $get($r, 'msa'),
                            'additional_info'     => $get($r, 'additional information'),
                        ];
                    }

                    if (count($previewRows) === 0) {
                        $parseError = 'No data rows found in the CSV after parsing.';
                    }
                }
            }
        }
    }
}

// ── Count existing imported records ──────────────────────────────────────────
$importedCount = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE is_imported = 1")->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Contract Import — <?= h(defined('APP_NAME') ? APP_NAME : 'PACT') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f0f2f5; font-size:.9rem; }
    .page-header { background:linear-gradient(90deg,#1e3a5f,#2c5d8a); color:#fff; padding:1rem 0; margin-bottom:1.5rem; }
    .page-header h1 { font-size:1.3rem; font-weight:600; margin:0; }
    .section-label { font-size:.7rem; text-transform:uppercase; font-weight:600; letter-spacing:.05em;
                     color:#6c757d; border-bottom:1px solid #dee2e6; padding-bottom:.35rem; margin-bottom:1rem; }
    .badge-warn { background:#ffc107; color:#000; }
    table.preview th, table.preview td { vertical-align:middle; white-space:nowrap; }
    table.preview td.wrap { white-space:normal; max-width:260px; }
    .miss { color:#dc3545; font-style:italic; }
    .danger-zone { border:2px solid #dc3545; border-radius:.5rem; padding:1.25rem; background:#fff5f5; }
  </style>
</head>
<body>
<div class="page-header">
  <div class="container">
    <h1>Contract Bulk Import</h1>
    <p class="mb-0" style="opacity:.8;font-size:.85rem;">
      Import contracts from the LF Forms CSV export &mdash; ADMIN only
    </p>
  </div>
</div>
<div class="container pb-5">

<?php /* ── DELETE RESULT ──────────────────────────────────────────────── */ ?>
<?php if ($deleteResult !== null): ?>
  <div class="alert alert-<?= $deleteResult > 0 ? 'success' : 'secondary' ?>">
    <?= $deleteResult > 0
        ? "Deleted <strong>$deleteResult</strong> imported contract(s) successfully."
        : "No imported records found to delete." ?>
  </div>
<?php endif; ?>

<?php /* ── IMPORT RESULT ──────────────────────────────────────────────── */ ?>
<?php if ($importResult !== null): ?>
  <?php if (!empty($importResult['error'])): ?>
    <div class="alert alert-danger"><?= h($importResult['error']) ?></div>
  <?php else: ?>
    <div class="alert alert-success">
      <strong><?= (int)$importResult['inserted'] ?></strong> contract(s) imported successfully.
      <?php if (!empty($importResult['skipped'])): ?>
        <hr>
        <p class="mb-1">The following rows were skipped:</p>
        <ul class="mb-0">
          <?php foreach ($importResult['skipped'] as $s): ?>
            <li><?= h($s) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <p><a href="index.php" class="btn btn-primary btn-sm">View Contracts</a>
       <a href="contract_import.php" class="btn btn-outline-secondary btn-sm ms-2">Import Another File</a></p>
  <?php endif; ?>
<?php endif; ?>

<?php /* ── PREVIEW TABLE → confirm import ─────────────────────────────── */ ?>
<?php if (!empty($previewRows) && $importResult === null): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Step 2 — Review &amp; Confirm</p>
      <p>Found <strong><?= count($previewRows) ?></strong> row(s). Review the mapping below, then click <strong>Import All</strong>.</p>
      <p class="text-muted mb-3" style="font-size:.8rem;">
        <span class="badge badge-warn">?</span> = value could not be matched to a database record and will be left blank.
      </p>
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm preview">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Instance ID</th>
              <th>Description / Name</th>
              <th>Vendor</th>
              <th>Dept</th>
              <th>Contract Type</th>
              <th>Status (Step)</th>
              <th>Contract Manager</th>
              <th>Total Cost</th>
              <th>Start Date</th>
              <th>PO/PR #</th>
              <th>Auto-Renew</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($previewRows as $pr): ?>
            <tr>
              <td><?= (int)$pr['_row'] ?></td>
              <td><?= h($pr['instance_id']) ?></td>
              <td class="wrap"><?= h(mb_substr($pr['description'], 0, 120)) ?></td>
              <td class="wrap"><?= h($pr['vendor_name']) ?></td>
              <td>
                <?php if ($pr['department_id']): ?>
                  <?= h($pr['dept_raw']) ?>
                <?php else: ?>
                  <?php if ($pr['dept_raw'] !== ''): ?>
                    <span class="miss" title="No match"><?= h($pr['dept_raw']) ?> <span class="badge badge-warn">?</span></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pr['contract_type_id']): ?>
                  <?= h($pr['contract_type']) ?>
                <?php else: ?>
                  <?php if ($pr['contract_type'] !== ''): ?>
                    <span class="miss"><?= h($pr['contract_type']) ?> <span class="badge badge-warn">?</span></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pr['contract_status_id']): ?>
                  <?= h($pr['current_step']) ?>
                <?php else: ?>
                  <?php if ($pr['current_step'] !== ''): ?>
                    <span class="miss"><?= h($pr['current_step']) ?> <span class="badge badge-warn">?</span></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pr['owner_contact_id']): ?>
                  <?= h($pr['contract_manager']) ?>
                <?php else: ?>
                  <?php if ($pr['contract_manager'] !== ''): ?>
                    <span class="miss"><?= h($pr['contract_manager']) ?> <span class="badge badge-warn">?</span></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><?= $pr['total_cost'] !== null ? '$' . number_format((float)$pr['total_cost'], 2) : '<span class="text-muted">—</span>' ?></td>
              <td><?= h($pr['start_date'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
              <td><?= h($pr['purchase_req']) ?></td>
              <td><?= $pr['auto_renew'] ? '<span class="badge bg-info">Yes</span>' : 'No' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <form method="post" action="">
        <input type="hidden" name="action" value="do_import">
        <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
        <input type="hidden" name="rows_json" value="<?= h(json_encode($previewRows)) ?>">
        <button type="submit" class="btn btn-success">
          Import <?= count($previewRows) ?> Record(s)
        </button>
        <a href="contract_import.php" class="btn btn-outline-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php /* ── PARSE ERROR ──────────────────────────────────────────────────── */ ?>
<?php if ($parseError !== null): ?>
  <div class="alert alert-danger"><?= h($parseError) ?></div>
<?php endif; ?>

<?php /* ── UPLOAD FORM (shown when not in preview mode) ────────────────── */ ?>
<?php if (empty($previewRows) && $importResult === null): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p class="section-label">Step 1 — Upload CSV</p>
      <p>
        Export the SharePoint spreadsheet as <strong>CSV UTF-8</strong>:
        <em>File → Save As → CSV UTF-8 (comma delimited)</em>
      </p>
      <form method="post" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="preview">
        <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
        <div class="mb-3" style="max-width:480px;">
          <label class="form-label fw-semibold" for="csv_file">CSV File</label>
          <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required>
        </div>
        <button type="submit" class="btn btn-primary">Parse &amp; Preview</button>
      </form>

      <hr class="my-4">
      <p class="section-label">Column Mapping Reference</p>
      <table class="table table-sm table-bordered" style="max-width:700px;">
        <thead class="table-light"><tr><th>Spreadsheet Column</th><th>Maps To</th></tr></thead>
        <tbody>
          <tr><td>Instance ID</td><td>Contract Number (unique)</td></tr>
          <tr><td>Description of Purchase or Service</td><td>Contract Name &amp; Description</td></tr>
          <tr><td>Vendor Legal Name</td><td>Counterparty Company (create if new)</td></tr>
          <tr><td>Vendor ID / Contact Name / Phone / Email</td><td>Company fields (populated on create or update if blank)</td></tr>
          <tr><td>Dept</td><td>Department (matched by code or name)</td></tr>
          <tr><td>Contract Type</td><td>Contract Type (fuzzy name match)</td></tr>
          <tr><td>Current Step</td><td>Contract Status (matched by name)</td></tr>
          <tr><td>Current Step Start Date</td><td>Start Date</td></tr>
          <tr><td>Contract Manager / Assigned To</td><td>Owner Primary Contact (matched by name)</td></tr>
          <tr><td>Total Cost</td><td>Total Contract Value</td></tr>
          <tr><td>Purchase Requisition</td><td>Bid/RFP Number</td></tr>
          <tr><td>Renewal</td><td>Auto-Renew (Yes = checked)</td></tr>
          <tr><td>LF Forms Status, Contract Term, Request Date, Requester, Contract Request Status, Vendor Contact Title, Additional Information, MSA</td><td>Procurement Notes (combined)</td></tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php /* ── DANGER ZONE ──────────────────────────────────────────────────── */ ?>
<?php if ($importedCount > 0 && $importResult === null): ?>
  <div class="danger-zone mb-4">
    <p class="section-label text-danger">Danger Zone</p>
    <p>
      There are currently <strong><?= $importedCount ?></strong> imported contract(s) in the database
      (flagged <code>is_imported = 1</code>).
      You can delete all of them here — this cannot be undone.
    </p>
    <form method="post" action=""
          onsubmit="return confirm('This will permanently delete all <?= $importedCount ?> imported contracts. Are you sure?');">
      <input type="hidden" name="action" value="delete_imported">
      <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
      <div class="input-group" style="max-width:400px;">
        <input type="text" class="form-control" name="confirm_delete"
               placeholder='Type DELETE to confirm' required pattern="DELETE" autocomplete="off">
        <button type="submit" class="btn btn-danger">Delete All Imported Records</button>
      </div>
      <div class="form-text text-danger mt-1">Type the word DELETE in the box to enable deletion.</div>
    </form>
  </div>
<?php endif; ?>

</div><!-- /container -->
</body>
</html>
