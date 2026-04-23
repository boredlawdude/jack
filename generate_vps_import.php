<?php
/**
 * generate_vps_import.php
 *
 * Generates a safe SQL file to append local contracts to the VPS without ID conflicts.
 * Foreign keys (company, person, contract_type) are resolved by name/email lookups
 * so they work regardless of ID differences between servers.
 *
 * Usage (run locally):
 *   php generate_vps_import.php > vps_contracts_import.sql
 *
 * Then on VPS:
 *   mysql -u contract_user -pPassword1234! contract_manager < vps_contracts_import.sql
 */

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=contract_manager;charset=utf8mb4',
    'contract_user',
    'Password1234!',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Fetch all contracts with resolved names for lookups
$contracts = $pdo->query("
    SELECT
        c.*,
        co_vendor.name            AS vendor_name,
        co_vendor.type            AS vendor_type,
        co_vendor.vendor_id       AS vendor_ext_id,
        co_vendor.contact_name    AS vendor_contact_name,
        co_vendor.phone           AS vendor_phone,
        co_vendor.email           AS vendor_email,
        p.email                   AS person_email,
        ct.contract_type          AS contract_type_name
    FROM contracts c
    LEFT JOIN companies     co_vendor ON co_vendor.company_id    = c.counterparty_company_id
    LEFT JOIN people        p         ON p.person_id              = c.owner_primary_contact_id
    LEFT JOIN contract_types ct       ON ct.contract_type_id      = c.contract_type_id
    ORDER BY c.contract_id
")->fetchAll(PDO::FETCH_ASSOC);

function q(PDO $pdo, mixed $val): string
{
    if ($val === null || $val === '') return 'NULL';
    return $pdo->quote((string)$val);
}

$out = [];
$out[] = "-- ============================================================";
$out[] = "-- VPS contracts import — generated " . date('Y-m-d H:i:s');
$out[] = "-- " . count($contracts) . " contracts from local DB";
$out[] = "-- Safe to re-run: duplicate contract_numbers are skipped.";
$out[] = "-- ============================================================";
$out[] = "SET NAMES utf8mb4;";
$out[] = "SET foreign_key_checks = 0;";
$out[] = "";

// ── Step 1: Insert missing vendor companies (by name) ─────────────────────────
$out[] = "-- ─── Step 1: Insert missing vendor companies ──────────────────────────────";
$seen = [];
foreach ($contracts as $c) {
    $vname = trim((string)($c['vendor_name'] ?? ''));
    if ($vname === '' || isset($seen[$vname])) continue;
    $seen[$vname] = true;

    $out[] = "INSERT INTO companies (name, type, vendor_id, contact_name, phone, email)";
    $out[] = "SELECT " . implode(', ', [
        $pdo->quote($vname),
        $pdo->quote($c['vendor_type'] ?: 'vendor'),
        q($pdo, $c['vendor_ext_id']),
        q($pdo, $c['vendor_contact_name']),
        q($pdo, $c['vendor_phone']),
        q($pdo, $c['vendor_email']),
    ]);
    $out[] = "WHERE NOT EXISTS (SELECT 1 FROM companies WHERE LOWER(name) = LOWER(" . $pdo->quote($vname) . "));";
    $out[] = "";
}

// ── Step 2: Insert contracts ──────────────────────────────────────────────────
$out[] = "";
$out[] = "-- ─── Step 2: Insert contracts ─────────────────────────────────────────────";
foreach ($contracts as $c) {

    // Lookup expressions — resolve by name/email on the target server
    $company_expr = ($c['vendor_name'] ?? '') !== ''
        ? "(SELECT company_id FROM companies WHERE LOWER(name) = LOWER(" . $pdo->quote($c['vendor_name']) . ") LIMIT 1)"
        : "NULL";

    $person_expr = ($c['person_email'] ?? '') !== ''
        ? "(SELECT person_id FROM people WHERE email = " . $pdo->quote($c['person_email']) . " LIMIT 1)"
        : "NULL";

    $type_expr = ($c['contract_type_name'] ?? '') !== ''
        ? "(SELECT contract_type_id FROM contract_types WHERE contract_type = " . $pdo->quote($c['contract_type_name']) . " LIMIT 1)"
        : "NULL";

    // department_id and contract_status_id are identical on both servers
    $values = implode(",\n    ", [
        q($pdo, $c['contract_number']),
        q($pdo, $c['name']),
        q($pdo, $c['description']),
        q($pdo, $c['contract_status_id']),
        q($pdo, $c['department_id']),
        $type_expr,
        $company_expr,
        $person_expr,
        q($pdo, $c['total_contract_value']),
        q($pdo, $c['start_date']),
        q($pdo, $c['end_date']),
        q($pdo, $c['bid_rfp_number']),
        q($pdo, $c['auto_renew']),
        q($pdo, $c['procurement_notes']),
        '1', // is_imported
        q($pdo, $c['manager_approval_date']),
        q($pdo, $c['purchasing_approval_date']),
        q($pdo, $c['legal_approval_date']),
        q($pdo, $c['risk_manager_approval_date']),
        q($pdo, $c['council_approval_date']),
        q($pdo, $c['date_approved_by_procurement']),
        q($pdo, $c['date_approved_by_manager']),
        q($pdo, $c['date_approved_by_council']),
        q($pdo, $c['po_number']),
        q($pdo, $c['po_amount']),
        q($pdo, $c['renewal_term_months']),
        q($pdo, $c['status_comment']),
        q($pdo, $c['use_standard_contract']),
        q($pdo, $c['minimum_insurance_coi']),
        q($pdo, $c['procurement_method']),
        q($pdo, $c['governing_law']),
        q($pdo, $c['currency']),
        q($pdo, $c['payment_terms_id']),
    ]);

    // Skip the duplicate check only if contract_number is NULL
    if ($c['contract_number'] !== null && $c['contract_number'] !== '') {
        $skip_check = "WHERE NOT EXISTS (SELECT 1 FROM contracts WHERE contract_number = " . q($pdo, $c['contract_number']) . ")";
    } else {
        $skip_check = "WHERE 1=1"; // no number to dedupe on — always insert
    }

    $out[] = "INSERT INTO contracts (";
    $out[] = "  contract_number, name, description, contract_status_id,";
    $out[] = "  department_id, contract_type_id, counterparty_company_id,";
    $out[] = "  owner_primary_contact_id, total_contract_value, start_date, end_date,";
    $out[] = "  bid_rfp_number, auto_renew, procurement_notes, is_imported,";
    $out[] = "  manager_approval_date, purchasing_approval_date, legal_approval_date,";
    $out[] = "  risk_manager_approval_date, council_approval_date,";
    $out[] = "  date_approved_by_procurement, date_approved_by_manager, date_approved_by_council,";
    $out[] = "  po_number, po_amount, renewal_term_months, status_comment,";
    $out[] = "  use_standard_contract, minimum_insurance_coi, procurement_method,";
    $out[] = "  governing_law, currency, payment_terms_id";
    $out[] = ")";
    $out[] = "SELECT";
    $out[] = "    " . $values;
    $out[] = $skip_check . ";";
    $out[] = "";
}

$out[] = "";
$out[] = "SET foreign_key_checks = 1;";
$out[] = "-- ─── Done ─────────────────────────────────────────────────────────────────";

echo implode("\n", $out) . "\n";
