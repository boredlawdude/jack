<?php
// create_admin.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

// CHANGE THESE:
$email = 'john@schifano.com';
$password = 'Password1234!';
$full_name = 'Site Admin';
$role = 'admin';

$emailNorm = trim(strtolower($email));
$hash = password_hash($password, PASSWORD_DEFAULT);

// Ensure table exists (safe)
pdo()->exec("
  CREATE TABLE IF NOT EXISTS people (
    person_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin','person') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;
");

// Upsert person
$stmt = pdo()->prepare("SELECT person_ID FROM people WHERE email = ? LIMIT 1");
$stmt->execute([$emailNorm]);
$existing = $stmt->fetch();

if ($existing) {
  $up = pdo()->prepare("UPDATE people SET password_hash = ?, full_name = ?, role = ? WHERE person_id = ?");
  $up->execute([$hash, $full_name, $role, $existing['person_id']]);
  echo "Updated admin User: {$emailNorm}\n";
} else {
  $ins = pdo()->prepare("INSERT INTO people (email, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
  $ins->execute([$emailNorm, $hash, $full_name, $role]);
  echo "Created admin user: {$emailNorm}\n";
}

echo "Now delete create_admin.php or move it out of web root.\n";

