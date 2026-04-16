<?php
declare(strict_types=1);
require_once APP_ROOT . '/app/models/DevelopmentAgreement.php';
require_once APP_ROOT . '/app/models/DevelopmentAgreementTract.php';
require_once APP_ROOT . '/app/models/Contract.php';

class DevelopmentAgreementsController
{
    private PDO $db;
    private DevelopmentAgreement $model;
    private DevelopmentAgreementTract $tracts;
    private Contract $contracts;

    public function __construct()
    {
        $this->db        = db();
        $this->model     = new DevelopmentAgreement($this->db);
        $this->tracts    = new DevelopmentAgreementTract($this->db);
        $this->contracts = new Contract($this->db);
    }

    // ------------------------------------------------------------------ index
    public function index(): void
    {
        // Show contracts of type "Development Agreement"
        $stmt = $this->db->query("
            SELECT c.contract_id, c.name, c.contract_number, c.contract_status_id,
                   cs.contract_status_name AS status_name,
                   da.dev_agreement_id, da.property_address, da.property_pin,
                   da.anticipated_start_date, da.anticipated_end_date,
                   CONCAT_WS(' ', a.first_name, a.last_name)  AS applicant_name,
                   CONCAT_WS(' ', po.first_name, po.last_name) AS property_owner_name,
                   c.created_at
            FROM contracts c
            JOIN contract_types ct ON ct.contract_type_id = c.contract_type_id
                 AND ct.contract_type = 'Development Agreement'
            LEFT JOIN contract_statuses cs ON cs.contract_status_id = c.contract_status_id
            LEFT JOIN development_agreements da ON da.contract_id = c.contract_id
            LEFT JOIN people a  ON a.person_id  = da.applicant_id
            LEFT JOIN people po ON po.person_id = da.property_owner_id
            ORDER BY c.contract_id DESC
        ");
        $agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        require APP_ROOT . '/app/views/development_agreements/index.php';
    }

    // ------------------------------------------------------------------ create (GET)
    public function create(): void
    {
        $mode      = 'create';
        $agreement = $_SESSION['old_devagr_form'] ?? [];
        $errors    = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['old_devagr_form'], $_SESSION['flash_errors']);
        // Provide empty tracts array for the sub-form template
        if (!isset($agreement['tracts'])) {
            $agreement['tracts'] = [];
        }
        $people = $this->getPeopleList();
        require APP_ROOT . '/app/views/development_agreements/edit.php';
    }

    // ------------------------------------------------------------------ store (POST)
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo 'Method not allowed.'; return;
        }

        $data   = $this->extractPost();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['flash_errors']    = $errors;
            $_SESSION['old_devagr_form'] = $data;
            header('Location: /index.php?page=development_agreements_create');
            exit;
        }

        // --- Auto-create linked Contract record ---
        $contractTypeId = $this->getDevAgrContractTypeId();

        $contractData = [
            'name'              => trim((string)($data['project_name'] ?? '')),
            'contract_type_id'  => $contractTypeId,
            'contract_status_id' => 1,
            'governing_law'     => 'North Carolina',
            'currency'          => 'USD',
            'contract_number'   => '',   // generated below
        ];
        $contractData['contract_number'] = $this->generateContractNumber($contractData);

        try {
            $contractId = $this->contracts->create($contractData);
        } catch (Throwable $e) {
            $_SESSION['flash_errors']    = ['Unable to create linked contract: ' . $e->getMessage()];
            $_SESSION['old_devagr_form'] = $data;
            header('Location: /index.php?page=development_agreements_create');
            exit;
        }

        $this->logContractHistory($contractId, 'contract_created', null, 'Draft', 'Development agreement created');

        // --- Create dev agreement linked to the new contract ---
        $data['contract_id'] = $contractId;
        $newId = $this->model->create($data);

        // --- Save tracts ---
        $this->saveTracts($newId, $_POST['tracts'] ?? []);

        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    // ------------------------------------------------------------------ show (GET)
    public function show(): void
    {
        $id        = (int)($_GET['dev_agreement_id'] ?? 0);
        $agreement = $this->model->find($id);
        if (!$agreement) { http_response_code(404); echo 'Not found.'; return; }

        // Redirect to the linked contract show page
        if (!empty($agreement['contract_id'])) {
            header('Location: /index.php?page=contracts_show&contract_id=' . (int)$agreement['contract_id']);
            exit;
        }

        // Legacy fallback: no linked contract
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);
        require APP_ROOT . '/app/views/development_agreements/show.php';
    }

    // ------------------------------------------------------------------ edit (GET)
    public function edit(): void
    {
        $id        = (int)($_GET['dev_agreement_id'] ?? 0);
        $agreement = $this->model->find($id);
        if (!$agreement) { http_response_code(404); echo 'Not found.'; return; }

        $mode   = 'edit';
        $errors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);

        if (!empty($_SESSION['old_devagr_form'])) {
            $agreement = array_merge($agreement, $_SESSION['old_devagr_form']);
            unset($_SESSION['old_devagr_form']);
        }

        // Load existing tracts for this agreement
        $agreement['tracts'] = $this->tracts->allForAgreement($id);

        $people = $this->getPeopleList();
        require APP_ROOT . '/app/views/development_agreements/edit.php';
    }

    // ------------------------------------------------------------------ update (POST)
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo 'Method not allowed.'; return;
        }

        $id     = (int)($_POST['dev_agreement_id'] ?? 0);
        $data   = $this->extractPost();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['flash_errors']    = $errors;
            $_SESSION['old_devagr_form'] = $data;
            header('Location: /index.php?page=development_agreements_edit&dev_agreement_id=' . $id);
            exit;
        }

        $this->model->update($id, $data);

        // Sync tracts (replace all)
        try {
            $this->saveTracts($id, $_POST['tracts'] ?? []);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Tract save error: ' . $e->getMessage()];
            header('Location: /index.php?page=development_agreements_edit&dev_agreement_id=' . $id);
            exit;
        }

        // Keep linked contract name in sync with project_name
        $agreement = $this->model->find($id);
        if ($agreement && !empty($agreement['contract_id'])) {
            $contractId = (int)$agreement['contract_id'];
            $contract   = $this->contracts->find($contractId);
            if ($contract) {
                $this->contracts->update($contractId, array_merge($contract, [
                    'name' => trim((string)($data['project_name'] ?? $contract['name'])),
                ]));
                $this->logContractHistory($contractId, 'contract_updated', null, null, 'Development agreement details updated');
            }
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $_SESSION['flash_success'] = 'Development agreement updated.';
        header('Location: /index.php?page=development_agreements_show&dev_agreement_id=' . $id);
        exit;
    }

    // ------------------------------------------------------------------ delete (POST)
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo 'Method not allowed.'; return;
        }
        if (!is_system_admin()) {
            http_response_code(403); echo 'Access denied.'; return;
        }

        $id = (int)($_POST['dev_agreement_id'] ?? 0);

        if ($id > 0) {
            $agreement = $this->model->find($id);
            $linkedContractId = !empty($agreement['contract_id']) ? (int)$agreement['contract_id'] : null;

            $this->model->delete($id);

            // Also delete the auto-created linked contract
            if ($linkedContractId) {
                $this->contracts->delete($linkedContractId);
            }
        }

        $_SESSION['flash_success'] = 'Development agreement deleted.';
        header('Location: /index.php?page=development_agreements');
        exit;
    }

    // ------------------------------------------------------------------ private helpers

    private function getDevAgrContractTypeId(): int
    {
        $stmt = $this->db->prepare(
            "SELECT contract_type_id FROM contract_types WHERE contract_type = 'Development Agreement' LIMIT 1"
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if (!$id) {
            // Auto-insert if the migration hasn't been run yet
            $ins = $this->db->prepare(
                "INSERT INTO contract_types (contract_type, is_active) VALUES ('Development Agreement', 1)"
            );
            $ins->execute();
            $id = (int)$this->db->lastInsertId();
        }
        return (int)$id;
    }

    private function generateContractNumber(array $data): string
    {
        $year  = date('y');
        $name  = trim((string)($data['name'] ?? ''));
        $words = preg_split('/\s+/', $name);
        $first  = isset($words[0]) ? strtoupper(substr($words[0], 0, 3)) : 'DA';
        $second = isset($words[1]) ? strtoupper(substr($words[1], 0, 3)) : '';

        $stmt = $this->db->query("SELECT MAX(contract_id) FROM contracts");
        $seq  = (int)$stmt->fetchColumn() + 1;

        $parts = [$year, 'DA', $first . ($second ? '_' . $second : ''), $seq];
        return implode('-', $parts);
    }

    private function logContractHistory(int $contractId, string $eventType, ?string $oldStatus = null, ?string $newStatus = null, ?string $notes = null): void
    {
        $changedBy = isset($_SESSION['person']['person_id']) ? (int)$_SESSION['person']['person_id'] : null;
        $stmt = $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->execute([$contractId, $eventType, $oldStatus, $newStatus, $changedBy, $notes]);
    }

    private function getPeopleList(): array
    {
        $stmt = $this->db->query(
            "SELECT person_id, CONCAT_WS(' ', first_name, last_name) AS full_name
             FROM people
             ORDER BY last_name, first_name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function extractPost(): array
    {
        return [
            'applicant_id'               => $_POST['applicant_id']               ?? '',
            'property_owner_id'          => $_POST['property_owner_id']          ?? '',
            'attorney_id'                => $_POST['attorney_id']                ?? '',
            'property_address'           => $_POST['property_address']           ?? '',
            'property_pin'               => $_POST['property_pin']               ?? '',
            'property_realestateid'      => $_POST['property_realestateid']      ?? '',
            'project_name'               => $_POST['project_name']               ?? '',
            'project_description'        => $_POST['project_description']        ?? '',
            'property_acerage'           => $_POST['property_acerage']           ?? '',
            'current_zoning'             => $_POST['current_zoning']             ?? '',
            'proposed_zoning'            => $_POST['proposed_zoning']            ?? '',
            'comp_plan_designation'      => $_POST['comp_plan_designation']      ?? '',
            'anticipated_start_date'     => $_POST['anticipated_start_date']     ?? '',
            'anticipated_end_date'       => $_POST['anticipated_end_date']       ?? '',
            'proposed_improvements'      => $_POST['proposed_improvements']      ?? '',
            'agreement_termination_date' => $_POST['agreement_termination_date'] ?? '',
            'planning_board_date'        => $_POST['planning_board_date']        ?? '',
            'town_council_hearing_date'  => $_POST['town_council_hearing_date']  ?? '',
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string)($data['project_name'] ?? '')) === '') {
            $errors[] = 'Project name is required.';
        }
        return $errors;
    }

    /**
     * Replace all tracts for a dev agreement with the posted rows.
     * Rows that have no PIN, address, or real estate ID are skipped (blank rows).
     */
    private function saveTracts(int $devAgreementId, array $postedTracts): void
    {
        // Delete all existing and re-insert (simplest for a small set)
        $this->tracts->deleteAllForAgreement($devAgreementId);

        foreach ($postedTracts as $row) {
            $pin     = trim((string)($row['property_pin']          ?? ''));
            $address = trim((string)($row['property_address']       ?? ''));
            $reid    = trim((string)($row['property_realestateid']  ?? ''));
            // Skip completely blank rows
            if ($pin === '' && $address === '' && $reid === '') {
                continue;
            }
            $this->tracts->create($devAgreementId, [
                'property_pin'          => $pin,
                'property_address'      => $address,
                'property_realestateid' => $reid,
                'property_acerage'      => $row['property_acerage']    ?? '',
                'owner_name'            => $row['owner_name']           ?? '',
                'sort_order'            => $row['sort_order']           ?? 0,
            ]);
        }
    }
}
