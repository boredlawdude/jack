<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
$createdBy  = function_exists('current_person_id') ? current_person_id() : null;

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Prevent path traversal + normalize */
function safe_rel_path(string $rel): string {
  $rel = str_replace('\\', '/', trim($rel));
  $rel = ltrim($rel, '/');
  // disallow traversal
  if ($rel === '' || str_contains($rel, '..')) return '';
  return $rel;
}

/** Resolve a template path that may be stored as relative like "templates/html/x.html" */
function resolve_template_path(string $relOrAbs, string $fallbackAbs): string {
  $p = trim($relOrAbs);
  if ($p === '') return $fallbackAbs;

  // If absolute
  if ($p[0] === '/' && is_file($p)) return $p;

  // If relative
  $rel = safe_rel_path($p);
  if ($rel === '') return $fallbackAbs;

  $abs = __DIR__ . '/' . $rel;
  if (is_file($abs)) return $abs;

  return $fallbackAbs;
}

/** Simple merge: replaces {{field}} */
function merge_html(string $html, array $data): string {
  // Replace {{ key }} with value
  return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function($m) use ($data) {
    $k = $m[1];
    $v = $data[$k] ?? '';
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }, $html);
}

/** Date formatter */
function fmt_date($d): string {
  if (!$d) return '';
  try { return (new DateTime((string)$d))->format('F j, Y'); }
  catch (Throwable $e) { return (string)$d; }
}

/* ---------- INPUT ---------- */
$contract_id = (int)($_GET['id'] ?? 0);
if ($contract_id <= 0) { http_response_code(400); exit('Missing contract id'); }

if (function_exists('can_manage_contract') && !can_manage_contract($contract_id)) {
  http_response_code(403);
  exit('Forbidden');
}

/* ---------- LOAD CONTRACT + TEMPLATE ---------- */
$q = $pdo->prepare("
  SELECT
    c.contract_id,
    c.contract_number,
    c.name AS contract_name,
    c.status,
    c.type,
    c.governing_law,
    c.start_date,
    c.end_date,
    c.auto_renew,
    c.renewal_term_months,
    c.total_contract_value,
    c.currency,
    c.description,
    c.department_id,
    d.department_name,

    c.contract_type_id,
    ct.contract_type,
    ct.template_file_html,
    ct.description AS contract_type_description,
    ct.formal_bidding_required,
   

    owner.company_id AS owner_company_id,
    owner.name       AS owner_company,
    owner.address    AS owner_address,
    owner.phone      AS owner_phone,
    owner.email      AS owner_email,
    owner.vendor_id  AS owner_vendor_id,
    owner.contact_name AS owner_contact_name,

    counterparty.company_id AS counterparty_company_id,
    counterparty.name       AS counterparty_company,
    counterparty.address    AS counterparty_address,
    counterparty.phone      AS counterparty_phone,
    counterparty.email      AS counterparty_email,
    counterparty.vendor_id  AS counterparty_vendor_id,
    counterparty.contact_name AS counterparty_contact_name,

    CONCAT_WS(' ', opc.first_name, opc.last_name) AS owner_primary_contact,
    opc.email       AS owner_primary_contact_email,
    opc.officephone AS owner_primary_contact_officephone,
    opc.cellphone   AS owner_primary_contact_cellphone,

    CONCAT_WS(' ', cpc.first_name, cpc.last_name) AS counterparty_primary_contact,
    cpc.email       AS counterparty_primary_contact_email,
    cpc.officephone AS counterparty_primary_contact_officephone,
    cpc.cellphone   AS counterparty_primary_contact_cellphone

  FROM contracts c
  LEFT JOIN departments d ON d.department_id = c.department_id
  LEFT JOIN contract_types ct ON ct.contract_type_id = c.contract_type_id
  JOIN companies owner ON owner.company_id = c.owner_company_id
  JOIN companies counterparty ON counterparty.company_id = c.counterparty_company_id
  LEFT JOIN people opc ON opc.person_id = c.owner_primary_contact_id
  LEFT JOIN people cpc ON cpc.person_id = c.counterparty_primary_contact_id
  WHERE c.contract_id = ?
  LIMIT 1
");
$q->execute([$contract_id]);
$c = $q->fetch(PDO::FETCH_ASSOC);

if (!$c) { http_response_code(404); exit('Contract not found'); }

/* ---------- TEMPLATE SELECTION ---------- */
$templateBase = dirname(__DIR__) . '/templates/html';
$fallback = $templateBase . '/default_contract.html';

$templateRel = (string)($c['template_file_html'] ?? '');
$templatePath = resolve_template_path($templateRel, $fallback);

if (!is_file($templatePath)) {
  http_response_code(500);
  exit("No HTML template found. Expected template_file_html or " . h($fallback));
}

/* ---------- LOAD + MERGE ---------- */
$html = file_get_contents($templatePath);
if ($html === false) { http_response_code(500); exit("Could not read template."); }

$merge = [
  // contract
  'contract_id' => $c['contract_id'] ?? '',
  'contract_number' => $c['contract_number'] ?? '',
  'contract_name' => $c['contract_name'] ?? '',
  'status' => $c['status'] ?? '',
  'type' => $c['type'] ?? '',
  'governing_law' => $c['governing_law'] ?? '',
  'start_date' => fmt_date($c['start_date'] ?? null),
  'end_date' => fmt_date($c['end_date'] ?? null),
  'auto_renew' => ((int)($c['auto_renew'] ?? 0) === 1) ? 'Yes' : 'No',
  'renewal_term_months' => $c['renewal_term_months'] ?? '',
  'total_contract_value' => $c['total_contract_value'] ?? '',
  'currency' => $c['currency'] ?? 'USD',
  'description' => $c['description'] ?? '',

  // dept/type
  'department_name' => $c['department_name'] ?? '',
  'contract_type' => $c['contract_type'] ?? '',
  'contract_type_description' => $c['contract_type_description'] ?? '',
  'formal_bidding_required' => ((int)($c['formal_bidding_required'] ?? 0) === 1) ? 'Yes' : 'No',

  // owner
  'owner_company' => $c['owner_company'] ?? '',
  'owner_address' => $c['owner_address'] ?? '',
  'owner_phone' => $c['owner_phone'] ?? '',
  'owner_email' => $c['owner_email'] ?? '',
  'owner_vendor_id' => $c['owner_vendor_id'] ?? '',
  'owner_contact_name' => $c['owner_contact_name'] ?? '',

  // counterparty
  'counterparty_company' => $c['counterparty_company'] ?? '',
  'counterparty_address' => $c['counterparty_address'] ?? '',
  'counterparty_phone' => $c['counterparty_phone'] ?? '',
  'counterparty_email' => $c['counterparty_email'] ?? '',
  'counterparty_vendor_id' => $c['counterparty_vendor_id'] ?? '',
  'counterparty_contact_name' => $c['counterparty_contact_name'] ?? '',

  // contacts
  'owner_primary_contact' => $c['owner_primary_contact'] ?? '',
  'owner_primary_contact_email' => $c['owner_primary_contact_email'] ?? '',
  'owner_primary_contact_officephone' => $c['owner_primary_contact_officephone'] ?? '',
  'owner_primary_contact_cellphone' => $c['owner_primary_contact_cellphone'] ?? '',

  'counterparty_primary_contact' => $c['counterparty_primary_contact'] ?? '',
  'counterparty_primary_contact_email' => $c['counterparty_primary_contact_email'] ?? '',
  'counterparty_primary_contact_officephone' => $c['counterparty_primary_contact_officephone'] ?? '',
  'counterparty_primary_contact_cellphone' => $c['counterparty_primary_contact_cellphone'] ?? '',
];

$outHtml = merge_html($html, $merge);

/* ---------- SAVE OUTPUT ---------- */

// before updating contract_body_html (or overwriting the file)
$oldHtml = (string)($c['contract_body_html'] ?? '');
$newHtml = (string)($_POST['contract_body_html'] ?? '');

if ($oldHtml !== $newHtml) {
  $ins = $pdo->prepare("
    INSERT INTO contract_html_revisions (contract_id, document_id, created_by_person_id, old_html, new_html)
    VALUES (?, ?, ?, ?, ?)
  ");
  $ins->execute([
    $contract_id,
    $doc_id ?? null,
    function_exists('current_person_id') ? current_person_id() : null,
    $oldHtml,
    $newHtml
  ]);
}

// now do your normal update/write
require_once __DIR__ . '/includes/docs_helpers.php';

$contractId = (int)$contract_id; // whatever your variable is
$createdBy  = function_exists('current_person_id') ? current_person_id() : null;

// $outHtml is your merged HTML string
$filename = ($c['contract_number'] ?? ('contract_' . $contractId)) . '_generated_' . date('Ymd_His') . '.html';
$filename = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $filename);

save_generated_doc(
  $pdo,
  $contractId,
  $filename,
  'generated_html',
  'text/html',
  $createdBy,
  [
    'content' => $outHtml,
    // If needed, override where storage lives:
    // 'storage_base_abs' => __DIR__ . '/storage',
    // 'storage_base_rel' => 'storage',
  ]
);

// Check if we should print automatically
if (isset($_GET['print']) && $_GET['print'] === '1') {
    // Output the HTML directly for printing
    header('Content-Type: text/html; charset=utf-8');
    echo $outHtml;
    echo '<script>window.onload = function() { window.print(); };</script>';
    exit;
}

header("Location: /contract_edit.php?id=" . $contractId);
exit;

