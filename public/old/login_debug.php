<?php
require_once __DIR__ . '/../includes/init.php';

$email = 'john@schifano.com';
$pw    = 'Password1234!';

$pdo = db();
$stmt = $pdo->prepare("SELECT person_id, email, can_login, is_active, password_hash FROM people WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
var_dump($p ? [
  'person_id' => $p['person_id'],
  'email' => $p['email'],
  'can_login' => $p['can_login'],
  'is_active' => $p['is_active'],
  'hash_len' => strlen((string)$p['password_hash']),
  'hash_prefix' => substr((string)$p['password_hash'], 0, 4),
] : null);

if ($p) {
  var_dump(password_verify($pw, (string)$p['password_hash']));
}
echo "</pre>";
