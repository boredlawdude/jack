<?php
// import_contracts.php — run from command line or protected page

require_once __DIR__ . '/../includes/init.php';; // your PDO setup
// Hard-code connection just for testing — replace with your real creds
$host = '127.0.0.1';
$db   = 'contract_manager';
$user = 'schifano';
$pass = '! ';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Direct PDO connection OK\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function import_csv($pdo, $table, $csvFile, $columns, $startFromRow = 1) {
    $handle = fopen($csvFile, 'r');
    if (!$handle) die("Cannot open $csvFile");

    $rowNum = 0;
    $inserted = 0;

    while (($data = fgetcsv($handle)) !== false) {
        $rowNum++;
        if ($rowNum < $startFromRow) continue; // skip header

        $values = [];
        foreach ($columns as $idx => $colName) {
            $values[] = $data[$idx] ?? null;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $inserted++;
        } catch (PDOException $e) {
            echo "Error row $rowNum: " . $e->getMessage() . "\n";
            // continue or break depending on how strict you want
        }
    }

    fclose($handle);
    echo "Imported $inserted rows into $table\n";
}

// === USAGE ===

// 1. Companies first
import_csv($pdo, 'companies', __DIR__ . '/data/companies.csv', [
    'name', 'vendor_id', 'address', 'phone', 'email', 'contact_name', 'is_active'
]);

// 2. Then people (you may need to match company_id by name/email)
import_csv($pdo, 'people', __DIR__ . '/data/people.csv', [
    'first_name', 'last_name', 'email', 'officephone', 'cellphone', 'title',
    'company_id', 'department_id', 'is_active'
]);

// 3. Contracts last
import_csv($pdo, 'contracts', __DIR__ . '/data/contracts.csv', [
    'name', 'contract_number', 'status', 'department_id',
    'owner_company_id', 'counterparty_company_id',
    'governing_law', 'start_date', 'end_date', 'description'
]);

echo "Import finished.\n";