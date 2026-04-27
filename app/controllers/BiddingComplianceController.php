<?php
declare(strict_types=1);

class BiddingComplianceController
{
    private PDO $db;

    public const EVENT_TYPES = [
        'No Bidding Required',
        'RFQ/RFP Published',
        'RFQ/RFP Received',
        'Selection Committee Decision',
        '3 Informal Quotes Received',
        'Documents Saved Here',
        'Documents Saved with Project Manager',
    ];

    public function __construct()
    {
        $this->db = db();
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $contractId = (int)($_POST['contract_id'] ?? 0);
        if ($contractId <= 0) {
            http_response_code(400);
            exit('Missing contract ID.');
        }

        $eventDate  = trim($_POST['event_date'] ?? '');
        $eventType  = trim($_POST['event_type'] ?? '');
        $comment    = trim($_POST['comment'] ?? '');
        $isConsortium       = isset($_POST['is_consortium']) ? 1 : 0;
        $consortiumName     = trim($_POST['consortium_name'] ?? '');
        $consortiumContractNumber = trim($_POST['consortium_contract_number'] ?? '');
        $createdBy  = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;

        // Validate
        if ($eventDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $_SESSION['flash_errors'] = ['Please enter a valid event date.'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
            exit;
        }
        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            $_SESSION['flash_errors'] = ['Please select a valid event type.'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
            exit;
        }

        $contractDocumentId = null;

        // Handle optional file upload
        if (!empty($_FILES['compliance_file']['tmp_name']) && $_FILES['compliance_file']['error'] === UPLOAD_ERR_OK) {
            $file        = $_FILES['compliance_file'];
            $origName    = basename($file['name']);
            $ext         = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed     = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'png', 'jpg', 'jpeg'];

            if (!in_array($ext, $allowed, true)) {
                $_SESSION['flash_errors'] = ['Unsupported file type: ' . $ext . '. Allowed: ' . implode(', ', $allowed)];
                header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
                exit;
            }

            $storageDir  = APP_ROOT . '/storage/contracts/' . $contractId . '/procurement';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            // Insert a contract_documents row first to get the ID
            $docStmt = $this->db->prepare(
                "INSERT INTO contract_documents (contract_id, doc_type, description, file_name, file_path, mime_type, created_by_person_id, created_at)
                 VALUES (?, 'procurement_docs', ?, '', '', ?, ?, NOW())"
            );
            $description = $eventType . ($comment ? ': ' . mb_substr($comment, 0, 80) : '');
            $docStmt->execute([$contractId, $description, $file['type'] ?: 'application/octet-stream', $createdBy]);
            $docId = (int)$this->db->lastInsertId();

            $safeEvent      = preg_replace('/[^A-Za-z0-9_-]/', '_', $eventType);
            $fileName       = $contractId . '_procurement_' . $safeEvent . '_' . $docId . '.' . $ext;
            $relPath        = 'storage/contracts/' . $contractId . '/procurement/' . $fileName;
            $fullPath       = $storageDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                $this->db->prepare("DELETE FROM contract_documents WHERE contract_document_id = ?")->execute([$docId]);
                $_SESSION['flash_errors'] = ['File upload failed. Please try again.'];
                header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
                exit;
            }

            // Update contract_documents with actual file info
            $this->db->prepare("UPDATE contract_documents SET file_name = ?, file_path = ? WHERE contract_document_id = ?")
                ->execute([$fileName, $relPath, $docId]);

            $contractDocumentId = $docId;
        }

        // Insert bidding_compliance record
        $stmt = $this->db->prepare(
            "INSERT INTO bidding_compliance
                (contract_id, event_date, event_type, comment,
                 is_consortium, consortium_name, consortium_contract_number,
                 contract_document_id, created_by_person_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $contractId,
            $eventDate,
            $eventType,
            $comment ?: null,
            $isConsortium,
            ($isConsortium && $consortiumName !== '') ? $consortiumName : null,
            ($isConsortium && $consortiumContractNumber !== '') ? $consortiumContractNumber : null,
            $contractDocumentId,
            $createdBy,
        ]);

        $_SESSION['flash_success'] = 'Bidding compliance record added.';
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
        exit;
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $complianceId = (int)($_POST['compliance_id'] ?? 0);
        $contractId   = (int)($_POST['contract_id'] ?? 0);

        if ($complianceId > 0 && $contractId > 0) {
            $this->db->prepare("DELETE FROM bidding_compliance WHERE compliance_id = ? AND contract_id = ?")
                ->execute([$complianceId, $contractId]);
        }

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId . '#bidding-compliance');
        exit;
    }
}
