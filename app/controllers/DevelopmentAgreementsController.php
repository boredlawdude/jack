<?php
declare(strict_types=1);
require_once APP_ROOT . '/app/models/DevelopmentAgreement.php';

class DevelopmentAgreementsController
{
    private PDO $db;
    private DevelopmentAgreement $model;

    public function __construct()
    {
        $this->db    = db();
        $this->model = new DevelopmentAgreement($this->db);
    }

    // ------------------------------------------------------------------ index
    public function index(): void
    {
        $agreements = $this->model->all();
        require APP_ROOT . '/app/views/development_agreements/index.php';
    }

    // ------------------------------------------------------------------ create (GET)
    public function create(): void
    {
        $mode       = 'create';
        $agreement  = $_SESSION['old_devagr_form'] ?? [];
        $errors     = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['old_devagr_form'], $_SESSION['flash_errors']);

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

        $newId = $this->model->create($data);
        $_SESSION['flash_success'] = 'Development agreement created.';
        header('Location: /index.php?page=development_agreements_show&dev_agreement_id=' . $newId);
        exit;
    }

    // ------------------------------------------------------------------ show (GET)
    public function show(): void
    {
        $id        = (int)($_GET['dev_agreement_id'] ?? 0);
        $agreement = $this->model->find($id);
        if (!$agreement) { http_response_code(404); echo 'Not found.'; return; }

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

        // Overlay any repopulated old form data
        if (!empty($_SESSION['old_devagr_form'])) {
            $agreement = array_merge($agreement, $_SESSION['old_devagr_form']);
            unset($_SESSION['old_devagr_form']);
        }

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
            $this->model->delete($id);
        }
        $_SESSION['flash_success'] = 'Development agreement deleted.';
        header('Location: /index.php?page=development_agreements');
        exit;
    }

    // ------------------------------------------------------------------ helpers
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
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (trim($data['project_name']) === '') {
            $errors[] = 'Project name is required.';
        }
        return $errors;
    }
}
