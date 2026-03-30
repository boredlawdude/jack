<?php
// app/controllers/ContractStatusController.php
class ContractStatusController {
    private $model;
    public function __construct() {
        require_once APP_ROOT . '/app/models/ContractStatus.php';
        $this->model = new ContractStatus(db());
    }
    public function index() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $statuses = $this->model->all();
        $errors = $_SESSION['contract_status_errors'] ?? [];
        $messages = $_SESSION['contract_status_messages'] ?? [];
        $success = $_SESSION['contract_status_success'] ?? false;
        unset($_SESSION['contract_status_errors'], $_SESSION['contract_status_messages'], $_SESSION['contract_status_success']);
        require APP_ROOT . '/app/views/admin_settings/contract_statuses.php';
    }
    public function create() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $name = trim($_POST['contract_status_name'] ?? '');
        $desc = trim($_POST['contract_status_desc'] ?? '');
        $errors = [];
        if ($name === '') {
            $errors[] = 'Status name is required.';
        }
        if (empty($errors)) {
            $this->model->create($name, $desc);
        }
        $_SESSION['contract_status_errors'] = $errors;
        $_SESSION['contract_status_success'] = empty($errors);
        header('Location: /index.php?page=admin_statuses');
        exit;
    }
    public function update() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $id = (int)($_POST['contract_status_id'] ?? 0);
        $name = trim($_POST['contract_status_name'] ?? '');
        $desc = trim($_POST['contract_status_desc'] ?? '');
        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid status ID.';
        }
        if ($name === '') {
            $errors[] = 'Status name is required.';
        }
        if (empty($errors)) {
            $this->model->update($id, $name, $desc);
        }
        $_SESSION['contract_status_errors'] = $errors;
        $_SESSION['contract_status_success'] = empty($errors);
        header('Location: /index.php?page=admin_statuses');
        exit;
    }
    public function delete() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $id = (int)($_POST['contract_status_id'] ?? 0);
        if ($id > 0) {
            $this->model->delete($id);
        }
        $_SESSION['contract_status_success'] = true;
        header('Location: /index.php?page=admin_statuses');
        exit;
    }
}
