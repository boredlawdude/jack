<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = pdo();
$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $contract_number = trim($_POST['contract_number'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $owner_company_id = (int)($_POST['owner_company_id'] ?? 0);
  $counterparty_company_id = (int)($_POST['counterparty_company_id'] ?? 0);

  if (!$contract_number) $errors[] = "Contract # is required.";
  if (!$name) $errors[] = "Name is required.";
  if (!$owner_company_id) $errors[] = "Owner Company is required.";
  if (!$counterparty_company_id) $errors[] = "Counterparty Company is required.";

  if (!$errors) {
    $stmt = $pdo->prepare("
  INSERT INTO contracts
    (contract_number, name, owner_company_id, counterparty_company_id, status)
  VALUES (?, ?, ?, ?, 'in_review')
        ");

        $stmt->execute([
        $contract_number,
        $name,
        $owner_company_id,
        $counterparty_company_id
        ]);
  }
}

$newContractId = (int)$pdo->lastInsertId();

header("Location: /contract_edit.php?id=" . $newContractId);
exit;

    
  



$companies = $pdo->query("SELECT company_id, name FROM companies WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>
<?php include __DIR__ . '/header.php'; ?>

<h1 class="h4">New Contract</h1>

<?php if ($ok): ?>
  <div class="alert alert-success">Created! <a href="/contracts_list.php">Back to list</a></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="card shadow-sm p-3">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Contract #</label>
      <input name="contract_number" class="form-control" required>
    </div>
    <div class="col-md-8">
      <label class="form-label">Short Description of Contract</label>
      <input name="name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Owner Company</label>
      <select name="owner_company_id" class="form-select" required>
        <option value="">Select...</option>
        <?php foreach ($companies as $c): ?>
          <option value="<?= (int)$c['company_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Counterparty Company</label>
      <select name="counterparty_company_id" class="form-select" required>
        <option value="">Select...</option>
        <?php foreach ($companies as $c): ?>
          <option value="<?= (int)$c['company_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Create</button>
      <a href="/contracts_list.php" class="btn btn-link">Cancel</a>
    </div>
  </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>

