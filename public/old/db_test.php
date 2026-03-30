<?php
require_once __DIR__ . '/../includes/init.php';

try {
  $pdo = pdo();
  echo "DB OK\n";
  echo $pdo->query("SELECT DATABASE()")->fetchColumn();
} catch (Throwable $e) {
  echo "DB FAIL: " . $e->getMessage();
}
