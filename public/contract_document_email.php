<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = db();
$docId = (int)($_GET['id'] ?? 0);

if ($docId <= 0) {
    http_response_code(400);
    exit('Missing document ID.');
}

// Fetch document + contract + counterparty email
$stmt = $db->prepare(
    "SELECT cd.*, c.name AS contract_name, c.contract_number,
            cp.email AS counterparty_email, cp.first_name AS cp_first, cp.last_name AS cp_last,
            op.email AS owner_contact_email
     FROM contract_documents cd
     JOIN contracts c ON cd.contract_id = c.contract_id
     LEFT JOIN people cp ON c.counterparty_primary_contact_id = cp.person_id
     LEFT JOIN people op ON c.owner_primary_contact_id = op.person_id
     WHERE cd.contract_document_id = ? LIMIT 1"
);
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

$contractId = (int)$doc['contract_id'];
$filePath   = APP_ROOT . '/' . ltrim((string)$doc['file_path'], '/');
$fileName   = $doc['file_name'] ?? basename($filePath);

$senderName = '';
if (!empty($_SESSION['person']['first_name'])) {
    $senderName = $_SESSION['person']['first_name'] . ' ' . ($_SESSION['person']['last_name'] ?? '');
}

$defaultSubject = 'Document: ' . ($doc['contract_name'] ?? 'Contract') . ' - ' . $fileName;

// Load email template from system settings
$tplStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_email_message' LIMIT 1");
$tplStmt->execute();
$emailTemplate = $tplStmt->fetchColumn();
if ($emailTemplate) {
    $defaultMessage = str_replace(
        ['{contract_number}', '{contract_name}', '{sender_name}'],
        [$doc['contract_number'] ?? '', $doc['contract_name'] ?? '', $senderName],
        $emailTemplate
    );
} else {
    $defaultMessage = "Please find the attached document for contract "
        . ($doc['contract_number'] ?? '') . " - " . ($doc['contract_name'] ?? '') . ".\n\n"
        . ($senderName ? "Regards,\n" . $senderName : '');
}

// Build default CC: current user email + owner primary contact (if different)
$ccEmails = [];
$userEmail = $_SESSION['person']['email'] ?? '';
if ($userEmail === '' && !empty($_SESSION['person']['person_id'])) {
    $stmt = $db->prepare("SELECT email FROM people WHERE person_id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['person']['person_id']]);
    $userEmail = $stmt->fetchColumn() ?: '';
}
if ($userEmail !== '') {
    $ccEmails[] = $userEmail;
}
$ownerEmail = $doc['owner_contact_email'] ?? '';
if ($ownerEmail !== '' && $ownerEmail !== $userEmail) {
    $ccEmails[] = $ownerEmail;
}
$defaultCc = implode(', ', $ccEmails);

$flashSuccess = '';
$flashError = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $ccEmail = trim($_POST['cc_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $flashError = 'Please enter a valid recipient email address.';
    } elseif ($ccEmail !== '') {
        $ccList = array_map('trim', explode(',', $ccEmail));
        $badCc = false;
        foreach ($ccList as $cc) {
            if ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $badCc = true;
                break;
            }
        }
        if ($badCc) {
            $flashError = 'One or more CC email addresses are invalid.';
        }
    }
    if (!$flashError && $subject === '') {
        $flashError = 'Subject is required.';
    } elseif (!$flashError && !is_file($filePath)) {
        $flashError = 'Document file not found on disk.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = (SMTP_SECURE === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->Timeout    = 15;

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($toEmail);
            if ($ccEmail !== '') {
                foreach (array_map('trim', explode(',', $ccEmail)) as $cc) {
                    if ($cc !== '') {
                        $mail->addCC($cc);
                    }
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $mail->AltBody = $message;

            $mail->addAttachment($filePath, $fileName);

            $mail->send();

            // Log to history
            $changedBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;
            $db->prepare(
                "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
                 VALUES (?, 'document_emailed', NULL, NULL, ?, NOW(), ?)"
            )->execute([$contractId, $changedBy, 'Emailed ' . $fileName . ' to ' . $toEmail]);

            $flashSuccess = 'Document emailed successfully to ' . htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
        } catch (Exception $e) {
            $flashError = 'Failed to send email. Please check SMTP settings.';
            error_log('PHPMailer error: ' . $e->getMessage());
        }
    }
}
?>

<div class="container py-4" style="max-width: 700px;">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/index.php?page=contracts">Contracts</a></li>
      <li class="breadcrumb-item"><a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>"><?= h($doc['contract_number'] ?? (string)$contractId) ?></a></li>
      <li class="breadcrumb-item active">Email Document</li>
    </ol>
  </nav>

  <h1 class="h4 mb-4">Email Document</h1>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $flashSuccess ?>
      <div class="mt-2">
        <a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>" class="btn btn-sm btn-outline-success">Back to Contract</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($flashError): ?>
    <div class="alert alert-danger"><?= h($flashError) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="mb-3 p-2 bg-light rounded">
        <small class="text-muted">Attachment:</small>
        <strong><?= h($fileName) ?></strong>
        <?php if (!empty($doc['doc_type'])): ?>
          <span class="badge bg-secondary ms-2"><?= h($doc['doc_type']) ?></span>
        <?php endif; ?>
      </div>

      <form method="post">
        <div class="mb-3">
          <label for="to_email" class="form-label">To <span class="text-danger">*</span></label>
          <input type="email" class="form-control" id="to_email" name="to_email" required
                 value="<?= h($_POST['to_email'] ?? $doc['counterparty_email'] ?? '') ?>"
                 placeholder="recipient@example.com">
        </div>

        <div class="mb-3">
          <label for="cc_email" class="form-label">CC</label>
          <input type="email" class="form-control" id="cc_email" name="cc_email"
                 value="<?= h($_POST['cc_email'] ?? $defaultCc) ?>"
                 placeholder="cc@example.com (optional)">
        </div>

        <div class="mb-3">
          <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="subject" name="subject" required
                 value="<?= h($_POST['subject'] ?? $defaultSubject) ?>">
        </div>

        <div class="mb-3">
          <label for="message" class="form-label">Message</label>
          <textarea class="form-control" id="message" name="message" rows="6"><?= h($_POST['message'] ?? $defaultMessage) ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Send Email</button>
          <a href="/index.php?page=contracts_show&contract_id=<?= $contractId ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
