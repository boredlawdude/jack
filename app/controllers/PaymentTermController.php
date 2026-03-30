<?php
// app/controllers/PaymentTermController.php
class PaymentTermController {
    private $model;
    public function __construct() {
        require_once APP_ROOT . '/app/models/PaymentTerm.php';
        $this->model = new PaymentTerm(db());
    }
    public function index() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $terms = $this->model->all();
        $errors = $_SESSION['payment_term_errors'] ?? [];
        $messages = $_SESSION['payment_term_messages'] ?? [];
        $success = $_SESSION['payment_term_success'] ?? false;
        unset($_SESSION['payment_term_errors'], $_SESSION['payment_term_messages'], $_SESSION['payment_term_success']);
        require APP_ROOT . '/app/views/admin_settings/payment_terms.php';
    }
    public function create() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $errors = [];
        if ($name === '') {
            $errors[] = 'Payment term name is required.';
        }
        if (empty($errors)) {
            $this->model->create($name, $desc);
        }
        $_SESSION['payment_term_errors'] = $errors;
        $_SESSION['payment_term_success'] = empty($errors);
        header('Location: /index.php?page=admin_payment_terms');
        exit;
    }
    public function update() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $id = (int)($_POST['payment_terms_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid payment term ID.';
        }
        if ($name === '') {
            $errors[] = 'Payment term name is required.';
        }
        if (empty($errors)) {
            $this->model->update($id, $name, $desc);
        }
        $_SESSION['payment_term_errors'] = $errors;
        $_SESSION['payment_term_success'] = empty($errors);
        header('Location: /index.php?page=admin_payment_terms');
        exit;
    }
    public function delete() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $id = (int)($_POST['payment_terms_id'] ?? 0);
        if ($id > 0) {
            $this->model->delete($id);
        }
        $_SESSION['payment_term_success'] = true;
        header('Location: /index.php?page=admin_payment_terms');
        exit;
    }
}
