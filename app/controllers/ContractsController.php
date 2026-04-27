<?php
declare(strict_types=1);
require_once APP_ROOT . '/app/models/Contract.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


class ContractsController
{
    /**
     * Delete a contract draft/document by contract_document_id (POST)
     */
    public function deleteDocument(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $docId = (int)($_POST['document_id'] ?? 0);
        if ($docId <= 0) {
            http_response_code(400);
            exit('Missing document id');
        }
        // Fetch doc + contract for permission check
        $stmt = $this->db->prepare("SELECT contract_document_id, contract_id, file_path, file_name FROM contract_documents WHERE contract_document_id = ? LIMIT 1");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            http_response_code(404);
            exit('Doc not found');
        }
        $contractId = (int)$doc['contract_id'];
        // Only people who can manage the contract can delete generated docs
        if (!function_exists('can_manage_contract')) {
            require_once APP_ROOT . '/includes/auth.php';
        }
        if (!can_manage_contract($contractId)) {
            http_response_code(403);
            exit('Forbidden');
        }
        // Delete DB row first
        $del = $this->db->prepare("DELETE FROM contract_documents WHERE contract_document_id = ?");
        $del->execute([$docId]);
        // Delete file on disk (best-effort)
        $absPath = APP_ROOT . '/' . ltrim((string)$doc['file_path'], '/');
        if (is_file($absPath)) {
            @unlink($absPath);
        }

        $this->logHistory($contractId, 'document_deleted', null, null, 'Deleted document: ' . ($doc['file_name'] ?? 'unknown'));

        // Redirect to contract show page
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }
    private function getContractStatuses(): array {
        require_once APP_ROOT . '/app/models/ContractStatus.php';
        $statusModel = new ContractStatus($this->db);
        return $statusModel->getForSelect();
    }
    private function getPaymentTerms(): array {
        $stmt = $this->db->query("SELECT payment_terms_id, name FROM payment_terms WHERE active = 1 ORDER BY sort_order, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate contract number: YY-DEPTINIT-XXX_YYY-SEQ
     */
    private function generateContractNumber(array $data): string {
        $year = date('y');
        $deptInitials = '';
        if (!empty($data['department_id'])) {
            $stmt = $this->db->prepare("SELECT dept_initials FROM departments WHERE department_id = ? LIMIT 1");
            $stmt->execute([(int)$data['department_id']]);
            $deptInitials = ($stmt->fetchColumn() ?: 'XXX');
        } else {
            $deptInitials = 'XXX';
        }
        $name = trim((string)($data['name'] ?? ''));
        $words = preg_split('/\s+/', $name);
        $first = isset($words[0]) ? strtoupper(substr($words[0], 0, 3)) : 'NON';
        $second = isset($words[1]) ? strtoupper(substr($words[1], 0, 3)) : '';
        $seq = $this->getNextContractSeq();
        $parts = [$year, $deptInitials, $first . ($second ? '_' . $second : ''), $seq];
        return implode('-', $parts);
    }

    /**
     * Get next contract sequence number (max contract_id + 1)
     */
    private function getNextContractSeq(): int {
        $stmt = $this->db->query("SELECT MAX(contract_id) FROM contracts");
        $max = (int)$stmt->fetchColumn();
        return $max + 1;
    }
    private function getCounterpartyPrimaryContacts(): array {
        $stmt = $this->db->query("SELECT person_id, first_name, last_name FROM people WHERE (is_town_employee IS NULL OR is_town_employee = 0)  ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function generateWordDocument(int $contractId): void
    {
        $this->generateDocument($contractId, 'docx');
    }

    public function generateHtmlDocument(int $contractId): void
    {
        $this->generateDocument($contractId, 'html');
    }

    private function generateDocument(int $contractId, string $format): void
    {
        // Set $createdBy from session
        $createdBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;

        // Fetch contract
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            exit;
        }

        // Resolve owner and counterparty company names
        if (!empty($contract['owner_company_id'])) {
            $stmt = $this->db->prepare("SELECT name FROM companies WHERE company_id = ? LIMIT 1");
            $stmt->execute([(int)$contract['owner_company_id']]);
            $contract['owner_company_name'] = $stmt->fetchColumn() ?: '';
        } else {
            $contract['owner_company_name'] = '';
        }
        if (!empty($contract['counterparty_company_id'])) {
            $stmt = $this->db->prepare("SELECT name FROM companies WHERE company_id = ? LIMIT 1");
            $stmt->execute([(int)$contract['counterparty_company_id']]);
            $contract['counterparty_company_name'] = $stmt->fetchColumn() ?: '';
        } else {
            $contract['counterparty_company_name'] = '';
        }

        // Merge Development Agreement fields if this is a DA contract
        $stmt = $this->db->prepare(
            "SELECT contract_type FROM contract_types WHERE contract_type_id = ? LIMIT 1"
        );
        $stmt->execute([$contract['contract_type_id'] ?? 0]);
        $ctName = strtolower((string)($stmt->fetchColumn() ?: ''));
        if (str_contains($ctName, 'development agreement')) {
            require_once APP_ROOT . '/app/models/DevelopmentAgreement.php';
            $daModel = new DevelopmentAgreement($this->db);
            $da = $daModel->findByContractId($contractId);
            if ($da) {
                // Prefix DA fields with da_ to avoid collisions, and also expose un-prefixed
                foreach ($da as $k => $v) {
                    $contract['da_' . $k] = $v;
                    if (!isset($contract[$k])) {
                        $contract[$k] = $v;
                    }
                }
                // Friendly formatted versions
                $contract['da_parkland_dedication_label'] = !empty($da['parkland_dedication']) ? 'Yes' : 'No';
                $contract['da_transportation_tier']       = $da['transportation_tier'] ?? '';
                $contract['da_number_of_units']           = $da['number_of_units'] ?? '';
                $contract['da_daily_flow_maximum']        = $da['daily_flow_maximum'] !== null
                    ? number_format((int)$da['daily_flow_maximum']) . ' gpd' : '';
            }
        }

        // Get contract type info (including template paths)
        $stmt = $this->db->prepare("SELECT * FROM contract_types WHERE contract_type_id = ? LIMIT 1");
        $stmt->execute([$contract['contract_type_id'] ?? 0]);
        $contractType = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contractType) {
            http_response_code(404);
            echo 'Contract type not found.';
            exit;
        }

        // Determine template file
        if ($format === 'docx') {
            $templateFile = $contractType['template_file_docx'] ?? null;
            $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            $downloadName = 'contract_' . $contractId . '.docx';
        } else {
            $templateFile = $contractType['template_file_html'] ?? null;
            $contentType = 'text/html';
            $downloadName = 'contract_' . $contractId . '.html';
        }

        if (!$templateFile) {
            http_response_code(404);
            echo 'No template file set for this contract type.';
            exit;
        }

        $templatePath = APP_ROOT . '/' . ltrim($templateFile, '/');
        if (!file_exists($templatePath)) {
            http_response_code(404);
            echo 'Template file not found: ' . htmlspecialchars($templatePath);
            exit;
        }

        if ($format === 'docx') {
            require_once APP_ROOT . '/vendor/autoload.php';
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            foreach ($contract as $key => $value) {
                // Pre-encode XML special characters — PHPWord fails to escape them
                // in certain code paths (e.g. split macros), causing corrupt DOCX.
                $safe = htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $templateProcessor->setValue($key, $safe);
            }

            $relativeDir = 'storage/contracts/';
            $outputDir = APP_ROOT . '/' . $relativeDir;
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }

            // Insert row with created_by_person_id
            $stmt = $this->db->prepare("INSERT INTO contract_documents (contract_id, file_path, file_name, created_at, created_by_person_id) VALUES (?, '', '', NOW(), ?)");
            $stmt->execute([$contractId, $createdBy]);
            $docId = $this->db->lastInsertId();

            $fileName = $contractId . '_DRAFT_v' . $docId . '.docx';
            $relativePath = $relativeDir . $fileName;
            $outputPath = $outputDir . $fileName;
            $templateProcessor->saveAs($outputPath);

            // Update with file path and name
            $stmt = $this->db->prepare("UPDATE contract_documents SET file_path = ?, file_name = ? WHERE contract_document_id = ?");
            $stmt->execute([$relativePath, $fileName, $docId]);

            $this->logHistory($contractId, 'document_generated', null, null, 'Generated DOCX draft: ' . $fileName);

            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        } else {
            // HTML logic
            $templateContent = file_get_contents($templatePath);
            if ($templateContent === false) {
                http_response_code(500);
                echo 'Failed to load template.';
                exit;
            }
            $output = $templateContent;
            foreach ($contract as $key => $value) {
                $output = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value), $output);
            }
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            echo $output;
            exit;
        }
    }

    private Person $people;
    private PDO $db;
    private Contract $contracts;

    public function __construct()
    {
        $this->db = db();
        $this->contracts = new Contract($this->db);
        require_once APP_ROOT . '/app/models/Person.php';
        $this->people = new Person($this->db);
    }

    private function getDepartments(): array
    {
        return $this->people->allDepartments();
    }

    private function getSystemSetting(string $key): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function getRiskManagerEmails(): string
    {
        $stmt = $this->db->query("
            SELECT p.email
            FROM people p
            JOIN person_roles pr ON pr.person_id = p.person_id
            JOIN roles r ON r.role_id = pr.role_id
            WHERE r.role_key = 'RISK_MANAGER'
              AND r.is_active = 1
              AND p.email IS NOT NULL AND p.email != ''
        ");
        $emails = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        return implode(',', $emails);
    }

    public function index(): void
    {
        $contracts = $this->contracts->search([]);

        // Handle ?pending_approval=manager|purchasing|legal|risk_manager|council
        $pendingApprovalFilter = null;
        $pendingApprovalLabel  = null;
        $pendingApprovalColMap = [
            'manager'      => 'manager_approval_date',
            'purchasing'   => 'purchasing_approval_date',
            'legal'        => 'legal_approval_date',
            'risk_manager' => 'risk_manager_approval_date',
            'council'      => 'council_approval_date',
        ];
        $pa = trim($_GET['pending_approval'] ?? '');
        if ($pa !== '' && isset($pendingApprovalColMap[$pa])) {
            require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
            $col = $pendingApprovalColMap[$pa];

            // Fetch only the fields needed to evaluate rules, filtered to unstamped contracts
            $candidateStmt = $this->db->prepare(
                "SELECT contract_id, total_contract_value, renewal_term_months, contract_type_id,
                        use_standard_contract, minimum_insurance_coi,
                        manager_approval_date, purchasing_approval_date, legal_approval_date,
                        risk_manager_approval_date, council_approval_date
                   FROM contracts WHERE `$col` IS NULL"
            );
            $candidateStmt->execute();
            $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);

            // Keep only contracts where current rules actually require this approval type
            $pendingIds = [];
            foreach ($candidates as $candidate) {
                $required = ApprovalRulesController::requiredApprovalsFor($this->db, $candidate);
                if (in_array($pa, $required, true)) {
                    $pendingIds[(int)$candidate['contract_id']] = true;
                }
            }

            $contracts = array_filter($contracts, fn($c) => isset($pendingIds[(int)$c['contract_id']]));
            $pendingApprovalFilter = $pa;
            $pendingApprovalLabel  = ApprovalRulesController::APPROVAL_LABELS[$pa] ?? $pa;
        }

        $departments = $this->getDepartments();
        $responsiblePeople = $this->getResponsiblePeople();
        $contractStatuses = $this->getContractStatuses();
        require APP_ROOT . '/app/views/contracts/index.php';
    }

    public function search(): void{
    

        $filters = [
            'q' => isset($_GET['q']) && trim($_GET['q']) !== '' ? $_GET['q'] : null,
            'contract_status_id' => (isset($_GET['contract_status_id']) && $_GET['contract_status_id'] !== '' && $_GET['contract_status_id'] !== '0') ? (int)$_GET['contract_status_id'] : null,
            'department_id' => (isset($_GET['department_id']) && $_GET['department_id'] !== '' && $_GET['department_id'] !== '0') ? $_GET['department_id'] : null,
            'owner_primary_contact_id' => (isset($_GET['owner_primary_contact_id']) && $_GET['owner_primary_contact_id'] !== '' && $_GET['owner_primary_contact_id'] !== '0') ? $_GET['owner_primary_contact_id'] : null,
            'end_date_from' => isset($_GET['end_date_from']) && $_GET['end_date_from'] !== '' ? $_GET['end_date_from'] : null,
            'end_date_to' => isset($_GET['end_date_to']) && $_GET['end_date_to'] !== '' ? $_GET['end_date_to'] : null,
            'company_id' => (isset($_GET['company_id']) && $_GET['company_id'] !== '' && $_GET['company_id'] !== '0') ? (int)$_GET['company_id'] : null,
        ];
        // Pass filters directly — the model uses !empty() guards so nulls are safely ignored
        $contracts = $this->contracts->search($filters);
        $departments = $this->getDepartments();
        $responsiblePeople = $this->getResponsiblePeople();
        $contractStatuses = $this->getContractStatuses();
        require APP_ROOT . '/app/views/contracts/index.php';
    }

    public function show(): void
    {
        $id = (int)($_GET['contract_id'] ?? 0);
        $contract = $this->contracts->find($id);
        $docsStmt = $this->db->prepare("SELECT cd.*, CONCAT(p.first_name, ' ', p.last_name) AS created_by_name FROM contract_documents cd LEFT JOIN people p ON cd.created_by_person_id = p.person_id WHERE cd.contract_id = :id ORDER BY cd.sort_order ASC, cd.created_at DESC");
        $docsStmt->execute(['id' => $id]);
        $documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

        $historyStmt = $this->db->prepare(
            "SELECT h.*, CONCAT(p.first_name, ' ', p.last_name) AS changed_by_name
             FROM contract_status_history h
             LEFT JOIN people p ON h.changed_by = p.person_id
             WHERE h.contract_id = ?
             ORDER BY h.changed_at DESC"
        );
        $historyStmt->execute([$id]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        $complianceStmt = $this->db->prepare(
            "SELECT bc.*, COALESCE(p.full_name, p.display_name) AS created_by_name,
                    cd.file_name AS doc_file_name, cd.file_path AS doc_file_path
             FROM bidding_compliance bc
             LEFT JOIN people p ON bc.created_by_person_id = p.person_id
             LEFT JOIN contract_documents cd ON bc.contract_document_id = cd.contract_document_id
             WHERE bc.contract_id = ?
             ORDER BY bc.event_date ASC, bc.created_at ASC"
        );
        $complianceStmt->execute([$id]);
        $complianceRecords = $complianceStmt->fetchAll(PDO::FETCH_ASSOC);

        // Load linked development agreement (if this contract is of type "Development Agreement")
        $devAgreement      = null;
        $devAgreementTracts = [];
        if (!empty($contract['contract_type_name']) && strtolower($contract['contract_type_name']) === 'development agreement') {
            require_once APP_ROOT . '/app/models/DevelopmentAgreement.php';
            require_once APP_ROOT . '/app/models/DevelopmentAgreementTract.php';
            $devModel = new DevelopmentAgreement($this->db);
            $devAgreement = $devModel->findByContractId($id);
            if ($devAgreement) {
                $tractModel        = new DevelopmentAgreementTract($this->db);
                $devAgreementTracts = $tractModel->allForAgreement((int)$devAgreement['dev_agreement_id']);
            }
        }

        // Load approval rules and evaluate which are required for this contract
        require_once APP_ROOT . '/app/controllers/ApprovalRulesController.php';
        $requiredApprovals  = ApprovalRulesController::requiredApprovalsFor($this->db, $contract);
        $userApprovalRoles  = ApprovalRulesController::getApprovalRolesForCurrentUser();

        require APP_ROOT . '/app/views/contracts/show.php';
    }

    public function create(): void
    {
        $mode = 'create';
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);

        $contract = $_SESSION['old_contract_form'] ?? [
            'contract_status_id' => 1, // Default to first status (e.g., Draft)
            'currency' => 'USD',
            'governing_law' => 'North Carolina',
            'owner_company_id' => 3,
            'auto_renew' => 0,
        ];
        unset($_SESSION['old_contract_form']);

        $departments = $this->getDepartments();
        $companies = $this->getCompanies();
        $types = $this->getContractTypes();
        $paymentTerms = $this->getPaymentTerms();
        $contractStatuses = $this->getContractStatuses();

        $ownerPeople = [];
        if (!empty($contract['owner_company_id'])) {
            $ownerPeople = $this->getPeopleByCompany((int)$contract['owner_company_id']);
        }

        $complianceInfoLink = $this->getSystemSetting('compliance_info_link');

        require APP_ROOT . '/app/views/contracts/edit.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $data = $this->collectFormData($_POST);
        // Auto-generate contract number if not provided
        if (empty($data['contract_number'])) {
            $data['contract_number'] = $this->generateContractNumber($data);
        }
        $errors = $this->validate($data);

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_contract_form'] = $data;
            header('Location: /index.php?page=contracts_create');
            exit;
        }

        try {
            $contractId = $this->contracts->create($data);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Unable to create contract: ' . $e->getMessage()];
            $_SESSION['old_contract_form'] = $data;
            header('Location: /index.php?page=contracts_create');
            exit;
        }

        $statusName = $this->getStatusName((int)($data['contract_status_id'] ?? 0));
        $this->logHistory($contractId, 'contract_created', null, $statusName, 'Contract created');

        // If created from an intake submission, mark it as imported
        if (!empty($_SESSION['intake_import_id'])) {
            $intakeId = (int)$_SESSION['intake_import_id'];
            unset($_SESSION['intake_import_id']);
            require_once APP_ROOT . '/app/models/ContractIntakeSubmission.php';
            $person = current_person();
            (new ContractIntakeSubmission($this->db))->markImported(
                $intakeId,
                $contractId,
                (int)($person['person_id'] ?? 0)
            );
        }

        unset($_SESSION['old_contract_form']);
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    public function edit(int $contractId): void
    {
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $mode = 'edit';
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);
        $old = $_SESSION['old_contract_form'] ?? null;
        unset($_SESSION['old_contract_form']);
        if (is_array($old) && $old) {
            $contract = array_merge($contract, $old);
        }
        $departments = $this->getDepartments();
        $companies = $this->getCompanies();
        $types = $this->getContractTypes();
        $paymentTerms = $this->getPaymentTerms();
        $contractStatuses = $this->getContractStatuses();
        $ownerPeople = [];
        if (!empty($contract['owner_company_id'])) {
            $ownerPeople = $this->getPeopleByCompany((int)$contract['owner_company_id']);
        }
        $complianceInfoLink = $this->getSystemSetting('compliance_info_link');
        $riskManagerEmails  = $this->getRiskManagerEmails();
        require APP_ROOT . '/app/views/contracts/edit.php';
    }

    public function update(int $contractId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        $data = $this->collectFormData($_POST);
        $errors = $this->validate($data);
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_contract_form'] = $data;
            header('Location: /index.php?page=contracts_edit&contract_id=' . $contractId);
            exit;
        }
        try {
            $this->contracts->update($contractId, $data);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Unable to update contract: ' . $e->getMessage()];
            $_SESSION['old_contract_form'] = $data;
            header('Location: /index.php?page=contracts_edit&contract_id=' . $contractId);
            exit;
        }

        // Log history
        $oldStatusId = (int)($contract['contract_status_id'] ?? 0);
        $newStatusId = (int)($data['contract_status_id'] ?? 0);
        $oldStatusName = $this->getStatusName($oldStatusId);
        $newStatusName = $this->getStatusName($newStatusId);

        if ($oldStatusId !== $newStatusId && $newStatusId > 0) {
            $this->logHistory($contractId, 'status_change', $oldStatusName, $newStatusName, 'Status changed from ' . ($oldStatusName ?? 'none') . ' to ' . ($newStatusName ?? 'none'));
        }
        $this->logHistory($contractId, 'contract_updated', null, null, 'Contract details updated');

        unset($_SESSION['old_contract_form']);
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    public function destroy(int $contractId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            http_response_code(404);
            echo 'Contract not found.';
            return;
        }
        try {
            $this->contracts->delete($contractId);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Unable to delete contract: ' . $e->getMessage();
            return;
        }
        header('Location: /index.php?page=contracts');
        exit;
    }

    public function bulkDestroy(): void
    {
        require_login();
        if (!is_system_admin()) {
            http_response_code(403); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit;
        }
        $rawIds = $_POST['contract_ids'] ?? [];
        if (!is_array($rawIds) || empty($rawIds)) {
            header('Location: /index.php?page=contracts');
            exit;
        }
        $ids = array_filter(array_map('intval', $rawIds), fn($id) => $id > 0);
        foreach ($ids as $id) {
            try {
                $this->contracts->delete($id);
            } catch (Throwable $e) {
                error_log('Bulk contract delete failed for ID ' . $id . ': ' . $e->getMessage());
            }
        }
        header('Location: /index.php?page=contracts');
        exit;
    }

    // --- Helper stubs (implement as needed or connect to models) ---
    private function getResponsiblePeople(): array {
        $stmt = $this->db->query("
            SELECT DISTINCT p.person_id,
                COALESCE(NULLIF(p.full_name,''), TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')))) AS display_name
            FROM people p
            INNER JOIN contracts c ON c.owner_primary_contact_id = p.person_id
            ORDER BY display_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCompanies(): array {
        $stmt = $this->db->query("SELECT company_id, name FROM companies WHERE is_active = 1 ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getContractTypes(): array {
        $stmt = $this->db->query("SELECT contract_type_id, contract_type FROM contract_types WHERE is_active = 1 ORDER BY contract_type ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPeopleByCompany(int $companyId): array {
        $stmt = $this->db->prepare("SELECT person_id, first_name, last_name FROM people WHERE company_id = ? AND is_active = 1 ORDER BY last_name, first_name");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function collectFormData(array $input): array { return $input; }
    private function validate(array $data): array { return []; }

    /**
     * Log an event to contract_status_history
     */
    private function logHistory(int $contractId, string $eventType, ?string $oldStatus = null, ?string $newStatus = null, ?string $notes = null): void
    {
        $changedBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;
        $stmt = $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->execute([$contractId, $eventType, $oldStatus, $newStatus, $changedBy, $notes]);
    }

    /**
     * Get status name by ID
     */
    private function getStatusName(?int $statusId): ?string
    {
        if (!$statusId) return null;
        $stmt = $this->db->prepare("SELECT contract_status_name FROM contract_statuses WHERE contract_status_id = ? LIMIT 1");
        $stmt->execute([$statusId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Add a manual note to contract history
     */
    public function addHistoryNote(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $contractId = (int)($_POST['contract_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($contractId <= 0 || $note === '') {
            http_response_code(400);
            echo 'Missing contract ID or note.';
            return;
        }

        $this->logHistory($contractId, 'manual_note', null, null, $note);

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    public function deleteHistoryNote(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        if (!is_system_admin()) {
            http_response_code(403);
            echo 'Access denied.';
            return;
        }

        $contractId  = (int)($_POST['contract_id'] ?? 0);
        $rawIds      = $_POST['history_ids'] ?? [];

        if ($contractId <= 0 || !is_array($rawIds) || empty($rawIds)) {
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $ids = array_filter(array_map('intval', $rawIds), fn($id) => $id > 0);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$contractId]);
            $stmt = $this->db->prepare(
                "DELETE FROM contract_status_history WHERE history_id IN ($placeholders) AND contract_id = ?"
            );
            $stmt->execute($params);
        }

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    /**
     * Save sort order for contract documents
     */
    public function saveDocumentOrder(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $contractId = (int)($_POST['contract_id'] ?? 0);
        $order = $_POST['order'] ?? [];
        $labels = $_POST['exhibit_label'] ?? [];

        if ($contractId > 0 && is_array($order)) {
            $orderStmt = $this->db->prepare("UPDATE contract_documents SET sort_order = ? WHERE contract_document_id = ? AND contract_id = ?");
            foreach ($order as $docId => $sortVal) {
                $orderStmt->execute([(int)$sortVal, (int)$docId, $contractId]);
            }
        }

        if ($contractId > 0 && is_array($labels)) {
            $labelStmt = $this->db->prepare("UPDATE contract_documents SET exhibit_label = ? WHERE contract_document_id = ? AND contract_id = ?");
            foreach ($labels as $docId => $labelVal) {
                $label = trim($labelVal) ?: null;
                $labelStmt->execute([$label, (int)$docId, $contractId]);
            }
        }

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    /**
     * Handle uploaded document (exhibit or revised contract)
     */
    public function storeDocument(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $contractId = (int)($_POST['contract_id'] ?? 0);
        if ($contractId <= 0) {
            http_response_code(400);
            echo 'Missing contract ID.';
            return;
        }

        $category = $_POST['doc_category'] ?? '';
        $allowedCategories = ['revised_vendor', 'revised_internal', 'exhibit'];
        if (!in_array($category, $allowedCategories, true)) {
            http_response_code(400);
            echo 'Invalid document category.';
            return;
        }

        // Build doc_type value
        if ($category === 'exhibit') {
            $letter = strtoupper(trim($_POST['exhibit_letter'] ?? ''));
            if (!preg_match('/^[A-Z]$/', $letter)) {
                http_response_code(400);
                echo 'Please select an exhibit letter (A-Z).';
                return;
            }
            $docType = 'Exhibit ' . $letter;
            $description = trim($_POST['description'] ?? '');
        } elseif ($category === 'revised_vendor') {
            $docType = 'Revised by Vendor';
            $description = '';
        } else {
            $docType = 'Revised Internally';
            $description = '';
        }

        // Handle file upload
        if (empty($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo 'File upload failed.';
            return;
        }

        $file = $_FILES['file_upload'];
        $originalName = basename($file['name']);
        $mimeType = $file['type'] ?: 'application/octet-stream';

        // Build safe file name
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeDocType = preg_replace('/[^A-Za-z0-9_-]/', '_', $docType);

        $relativeDir = 'storage/contracts/';
        $outputDir = APP_ROOT . '/' . $relativeDir;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $createdBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;

        $exhibitLabel = trim($_POST['exhibit_label'] ?? '') ?: null;

        // Insert row first to get the doc ID for file naming
        $stmt = $this->db->prepare(
            "INSERT INTO contract_documents (contract_id, doc_type, exhibit_label, description, file_name, file_path, mime_type, created_by_person_id, created_at)
             VALUES (?, ?, ?, ?, '', '', ?, ?, NOW())"
        );
        $stmt->execute([$contractId, $docType, $exhibitLabel, $description ?: null, $mimeType, $createdBy]);
        $docId = $this->db->lastInsertId();

        $fileName = $contractId . '_' . $safeDocType . '_v' . $docId . ($ext ? '.' . $ext : '');
        $outputPath = $outputDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $outputPath)) {
            // Clean up DB row on failure
            $this->db->prepare("DELETE FROM contract_documents WHERE contract_document_id = ?")->execute([$docId]);
            http_response_code(500);
            echo 'Failed to save uploaded file.';
            return;
        }

        $relativePath = $relativeDir . $fileName;

        // Update row with final file name and path
        $stmt = $this->db->prepare("UPDATE contract_documents SET file_name = ?, file_path = ? WHERE contract_document_id = ?");
        $stmt->execute([$fileName, $relativePath, $docId]);

        $this->logHistory($contractId, 'document_uploaded', null, null, 'Uploaded ' . $docType . ': ' . $fileName);

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }
}