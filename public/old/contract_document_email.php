<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$success = false;
$errors = [];

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    exit('Missing document id');
}

$pdo = pdo();
$stmt = $pdo->prepare('SELECT * FROM contract_documents WHERE contract_document_id = ? LIMIT 1');
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) {
    http_response_code(404);
    exit('Document not found');
}

// Fetch contract and related emails
$contractId = isset($doc['contract_id']) ? (int)$doc['contract_id'] : 0;
$emails = [];
if ($contractId > 0) {
    $stmt = $pdo->prepare('SELECT 
        c.*, 
        op.email AS owner_primary_contact_email, op.display_name AS owner_primary_contact_name,
        cp.email AS counterparty_primary_contact_email, cp.display_name AS counterparty_primary_contact_name,
        d.department_name
    FROM contracts c
    LEFT JOIN people op ON op.person_id = c.owner_primary_contact_id
    LEFT JOIN people cp ON cp.person_id = c.counterparty_primary_contact_id
    LEFT JOIN departments d ON d.department_id = c.department_id
    WHERE c.contract_id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) {
        if (!empty($c['owner_primary_contact_email'])) {
            $emails[] = [
                'email' => $c['owner_primary_contact_email'],
                'label' => 'Town Contact: ' . ($c['owner_primary_contact_name'] ?? $c['owner_primary_contact_email'])
            ];
        }
        if (!empty($c['counterparty_primary_contact_email'])) {
            $emails[] = [
                'email' => $c['counterparty_primary_contact_email'],
                'label' => 'Counterparty: ' . ($c['counterparty_primary_contact_name'] ?? $c['counterparty_primary_contact_email'])
            ];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? $doc['file_name']);
    $message = trim($_POST['message'] ?? '');
    $filePath = APP_ROOT . '/' . $doc['file_path'];

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid recipient email address.';
    } elseif (!file_exists($filePath)) {
        $errors[] = 'File not found on server.';
    } else {
        // Send email with attachment
        $boundary = md5(uniqid(time()));
        $headers = "From: contracts@yourdomain.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $body .= $message . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"" . basename($filePath) . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($filePath))) . "\r\n";
        $body .= "--$boundary--";

        if (mail($to, $subject, $body, $headers)) {
            $success = true;
        } else {
            $errors[] = 'Failed to send email.';
        }
    }
}

// Only include header once, and use the app layout

?>
<div class="container py-4">
  <h1 class="h4 mb-3">Email Document</h1>
  <?php if ($success): ?>
    <div class="alert alert-success">Email sent successfully!</div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label for="to" class="form-label">To (email address)</label>
      <select class="form-select mb-2" id="to_select" onchange="document.getElementById('to').value=this.value;">
        <option value="">-- Select recipient --</option>
        <?php foreach ($emails as $e): ?>
          <option value="<?= htmlspecialchars($e['email']) ?>"><?= htmlspecialchars($e['label']) ?> (<?= htmlspecialchars($e['email']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <input type="email" class="form-control" id="to" name="to" required value="<?= htmlspecialchars($_POST['to'] ?? '') ?>">
      <div class="form-text">Choose from the list or enter an email address.</div>
    </div>
    <div class="mb-3">
      <label for="subject" class="form-label">Subject</label>
      <input type="text" class="form-control" id="subject" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? $doc['file_name']) ?>">
    </div>
    <div class="mb-3">
      <label for="message" class="form-label">Message</label>
      <textarea class="form-control" id="message" name="message" rows="4"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Attachment</label>
      <div><?= htmlspecialchars($doc['file_name']) ?></div>
    </div>
    <button type="submit" class="btn btn-primary">Send Email</button>
    <a href="/index.php?page=contracts_show&contract_id=<?= (int)($doc['contract_id'] ?? 0) ?>" class="btn btn-outline-secondary ms-2">Back</a>
  </form>
</div>
<?php include dirname(__DIR__) . '/app/views/layouts/footer.php'; ?>
