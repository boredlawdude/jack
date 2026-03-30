  <?php
  declare(strict_types=1);

  require_once __DIR__ . '/../includes/init.php';
  require_login();

  $pdo = db();

  $rows = $pdo->query("
    SELECT company_id, name, vendor_id, address, phone, email, contact_name, verified_by, is_active
    FROM companies
    ORDER BY name
  ")->fetchAll();

  include __DIR__ . '/header.php';
  ?>

  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 me-auto">Companies</h1>
    <a class="btn btn-primary btn-sm" href="/company_create.php">New Company</a>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Vendor ID</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Verified by</th>
            <th>Active</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['company_id'] ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['vendor_id'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['contact_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['verified_by'] ?? '') ?></td>
              <td><?= ((int)$r['is_active'] === 1) ? 'Yes' : 'No' ?></td>
              <?php if (can_edit_company()): ?>
                  <td><a class="btn btn-sm btn-outline-primary" href="/company_edit.php?id=<?= (int)$r['company_id'] ?>">Edit</a></td>
  <?php endif; ?>

            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-muted p-3">No companies yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php include __DIR__ . '/footer.php'; ?>
