<?php
declare(strict_types=1);


require_once __DIR__ . '/../includes/init.php';



/** Basic escaping helper (optional; many of your pages already have h()) */
// function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Session helpers */

//Current person function
function current_person(): array {
    if (empty($_SESSION['person']['person_id'])) {
        return [];
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT 
        person_id, 
        email, 
        COALESCE(
          NULLIF(TRIM(full_name), ''),
          NULLIF(TRIM(display_name), ''),
          NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''),
          email,
          'Unknown'
        ) AS name,
        department_id, 
        is_active
      FROM people 
      WHERE person_id = ? 
      LIMIT 1
    ");
    $stmt->execute([$_SESSION['person']['person_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Fetch roles for this user
    $roles = [];
    $role = null;
    if (!empty($user['person_id'])) {
      $roleStmt = $pdo->prepare("
        SELECT r.role_key
        FROM person_roles pr
        JOIN roles r ON r.role_id = pr.role_id AND r.is_active = 1
        WHERE pr.person_id = ?
      ");
      $roleStmt->execute([$user['person_id']]);
      $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
      if (!empty($roles)) {
        $role = strtoupper($roles[0]);
      }
    }

    $user['roles'] = $roles;
    $user['role'] = $role;
    $user['name'] = $user['name'] ?? $user['email'] ?? 'Unknown User';

    $_SESSION['person'] = array_merge($_SESSION['person'] ?? [], $user);
    $cached = $user;

    return $cached;
}
//End Current person function
//old current person function
// function current_person(): ?array {
//  return $_SESSION['person'] ?? null;
// }

function current_person_id(): int {
  $p = current_person();
  return (int)($p['person_id'] ?? 0);
}

function require_login(): void {
  if (!current_person()) {
    $next = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: /login.php?next=' . urlencode($next));
    exit;
  }
  // Prevent browsers from caching authenticated pages
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

function logout_person(): void {
  
  $_SESSION = [];

  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'] ?? '/',
      $params['domain'] ?? '',
      (bool)($params['secure'] ?? false),
      (bool)($params['httponly'] ?? true)
    );
  }

  session_destroy();
}

/**
 * People-based login
 * Requires people.email, people.password_hash, people.can_login=1, people.is_active=1
 * Tries to construct a display name from full_name OR first/last.
 */
function login_person(string $email, string $password): bool {
 

  $email = trim(strtolower($email));
  if ($email === '' || $password === '') return false;

  $pdo = db();

  $stmt = $pdo->prepare("
    SELECT
      person_id,
      email,
      password_hash,
      can_login,
      is_active,
      full_name,
      first_name,
      last_name
    FROM people
    WHERE email = ?
    LIMIT 1
  ");
  $stmt->execute([$email]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$p) return false;
  if ((int)$p['is_active'] !== 1) return false;
  if ((int)$p['can_login'] !== 1) return false;
  if (empty($p['password_hash'])) return false;

  if (!password_verify($password, (string)$p['password_hash'])) return false;

  // Upgrade password hash if algorithm changed
  if (password_needs_rehash((string)$p['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $up = $pdo->prepare("UPDATE people SET password_hash = ? WHERE person_id = ?");
    $up->execute([$newHash, (int)$p['person_id']]);
  }

  // Track last login
  $pdo->prepare("UPDATE people SET last_login_at = CURRENT_TIMESTAMP WHERE person_id = ?")
      ->execute([(int)$p['person_id']]);

  // Prevent session fixation
  session_regenerate_id(true);

  $name = trim((string)($p['full_name'] ?? ''));
  if ($name === '') {
    $name = trim((string)($p['first_name'] ?? '') . ' ' . (string)($p['last_name'] ?? ''));
  }
  if ($name === '') $name = (string)$p['email'];

  $_SESSION['person'] = [
    'person_id' => (int)$p['person_id'],
    'email'     => (string)$p['email'],
    'name'      => $name,
  ];

  return true;
}

/* ============================================================
   Roles (people-based)
   Requires: roles, person_roles, person_department_roles
   ============================================================ */

function person_has_role_key(string $role_key): bool {
  require_login();
  $pid = current_person_id();
  if ($pid <= 0) return false;

  $stmt = db()->prepare("
    SELECT 1
    FROM person_roles pr
    JOIN roles r ON r.role_id = pr.role_id
    WHERE pr.person_id = ?
      AND r.role_key = ?
      AND r.is_active = 1
    LIMIT 1
  ");
  $stmt->execute([$pid, $role_key]);
  return (bool)$stmt->fetchColumn();
}
// for onlyoffice JWT signing (if your DS is started with JWT enabled)
function oo_jwt_sign(array $payload, string $secret): string {
  $header = ['alg' => 'HS256', 'typ' => 'JWT'];
  $b64 = fn($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
  $h = $b64($header);
  $p = $b64($payload);
  $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", $secret, true)), '+/', '-_'), '=');
  return "$h.$p.$sig";
}

function person_has_department_role_key(int $department_id, string $role_key): bool {
  require_login();
  $pid = current_person_id();
  if ($pid <= 0 || $department_id <= 0) return false;

  $stmt = db()->prepare("
    SELECT 1
    FROM person_department_roles pdr
    JOIN roles r ON r.role_id = pdr.role_id
    WHERE pdr.person_id = ?
      AND pdr.department_id = ?
      AND r.role_key = ?
      AND r.is_active = 1
    LIMIT 1
  ");
  $stmt->execute([$pid, $department_id, $role_key]);
  return (bool)$stmt->fetchColumn();
}
function is_superuser(): bool {
  return person_has_role_key('SUPERUSER');
}

function require_superuser(): void {
  require_login();
  if (!is_superuser()) {
    http_response_code(403);
    echo "Forbidden (superuser only).";
    exit;
  }
}

function is_system_admin(): bool {
  // Global roles that should have full access
  return person_has_role_key('SUPERUSER') || person_has_role_key('ADMIN');
}

function require_system_admin(): void {
  require_login();
  if (!is_system_admin()) {
    http_response_code(403);
    echo "Forbidden (admin only).";
    exit;
  }
}

/* ============================================================
   Contract permissions (dept-scoped)
   ============================================================ */
function can_edit_company(): bool {
  // simplest: system admin OR dept contract admin
  if (is_system_admin()) return true;
  // if you have a "global" contract admin role, check it here too
  return person_has_role_key('DEPT_CONTRACT_ADMIN');
}

function can_manage_contract_department(?int $department_id): bool {
  if (is_system_admin()) return true;
  if (!$department_id || $department_id <= 0) return false;

  return person_has_department_role_key((int)$department_id, 'DEPT_CONTRACT_ADMIN');
}

function can_manage_contract(int $contract_id): bool {
  if (is_system_admin()) return true;
  if ($contract_id <= 0) return false;

  $stmt = db()->prepare("SELECT department_id FROM contracts WHERE contract_id = ? LIMIT 1");
  $stmt->execute([$contract_id]);
  $dept_id = (int)($stmt->fetchColumn() ?? 0);

  return $dept_id > 0 && person_has_department_role_key($dept_id, 'DEPT_CONTRACT_ADMIN');
}

/** Safe redirect helper (prevents open redirects) */
function safe_next(string $next, string $fallback = '/'): string {
  $next = trim($next);
  if ($next === '') return $fallback;
  // Only allow relative paths
  if (str_starts_with($next, '/') && !str_starts_with($next, '//')) return $next;
  return $fallback;
}
