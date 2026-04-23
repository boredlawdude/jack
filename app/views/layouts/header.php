<?php
declare(strict_types=1);

//require_once __DIR__ . '/../../bootstrap.php';
?>
<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Contracts App', ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
 <style>
    .app-navbar {
      background: linear-gradient(90deg, #1e3a5f, #2c5d8a);
    }

  .app-navbar .navbar-brand {
    color: #fff;
    font-weight: 600;
  }

  .app-navbar .nav-link {
    color: rgba(255,255,255,0.85);
  }

  .app-navbar .nav-link:hover {
    color: #fff;


</style>
 


</head>
<body class="bg-light"></body>
<body class="bg-light">

<nav class="navbar navbar-expand-lg app-navbar shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="/index.php?page=dashboard">
      <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Contracts App', ENT_QUOTES, 'UTF-8') ?>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/index.php?page=dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/index.php?page=contracts">Contracts</a></li>
        <li class="nav-item"><a class="nav-link" href="/index.php?page=contracts_create">New Contract</a></li>
        <div class="d-flex align-items-center border border-light border-opacity-50 rounded px-1 mx-1" style="background:rgba(255,255,255,0.08);">
          <li class="nav-item"><a class="nav-link" href="/index.php?page=development_agreements">Dev Agreements</a></li>
          <li class="nav-item">
            <a class="nav-link" href="/index.php?page=dev_agreement_submissions">
              Intake Submissions
              <?php
              try {
                  require_once APP_ROOT . '/app/models/DevelopmentAgreementSubmission.php';
                  $pendingCount = (new DevelopmentAgreementSubmission(db()))->countPending();
                  if ($pendingCount > 0) echo '<span class="badge bg-warning text-dark ms-1">' . $pendingCount . '</span>';
              } catch (Throwable $e) { /* table may not exist yet */ }
              ?>
            </a>
          </li>
        </div>

        <li class="nav-item"><a class="nav-link" href="/index.php?page=companies">Companies</a></li>
        <li class="nav-item"><a class="nav-link" href="/index.php?page=companies_create">New Company</a></li>

        <li class="nav-item"><a class="nav-link" href="/index.php?page=people">People</a></li>
        <li class="nav-item"><a class="nav-link" href="/index.php?page=departments">Departments</a></li>

        <?php
        $isSuperOrAdmin = false;
        if (function_exists('current_person') && ($p = current_person())) {
          $roles = $p['roles'] ?? [];
          if (
            (is_array($roles) && (in_array('SUPERUSER', $roles, true) || in_array('ADMIN', $roles, true))) ||
            (isset($p['role']) && in_array(strtolower($p['role']), ['superuser', 'admin'], true))
          ) {
            $isSuperOrAdmin = true;
          }
        }
        ?>
        <?php if ($isSuperOrAdmin): ?>
          <li class="nav-item"><a class="nav-link" href="/index.php?page=people_create">New User</a></li>
          <li class="nav-item"><a class="nav-link" href="/index.php?page=admin_settings">System Settings</a></li>
          <li class="nav-item"><a class="nav-link" href="/index.php?page=approval_rules">Approval Rules</a></li>
        <?php endif; ?>

        <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <?php if (function_exists('current_person') && ($p = current_person())): ?>
          <?php $displayName = $p['display_name'] ?? $p['name'] ?? $p['email'] ?? ''; ?>
          <?php if ($displayName): ?>
            <span class="text-muted small">
              Hello, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </span>
          <?php endif; ?>
          </span>
        <?php endif; ?>

        <?php if (function_exists('current_person') && ($u = current_person()) && in_array(($u['role'] ?? ''), ['superuser', 'admin'], true)): ?>
          <a class="btn btn-outline-danger btn-sm" href="/admin_password_reset.php">Admin Reset</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="container">