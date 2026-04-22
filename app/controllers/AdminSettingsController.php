<?php
// app/controllers/AdminSettingsController.php
class AdminSettingsController {
    public function index() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $pdo = db();
        // Load settings
        $settings = [];
        $stmt = $pdo->query("SELECT setting_key, setting_value, description FROM system_settings WHERE setting_key IN ('storage_base_dir', 'contracts_generated_subdir', 'docx_template_dir', 'html_template_dir', 'default_docx_template', 'default_html_template', 'default_email_message', 'compliance_info_link', 'risk_manager_email') ORDER BY setting_key");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row;
        }
        // Generate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $errors = $_SESSION['admin_settings_errors'] ?? [];
        $messages = $_SESSION['admin_settings_messages'] ?? [];
        $success = $_SESSION['admin_settings_success'] ?? false;
        unset($_SESSION['admin_settings_errors'], $_SESSION['admin_settings_messages'], $_SESSION['admin_settings_success']);
        require APP_ROOT . '/app/views/admin_settings/form.php';
    }

    public function update() {
        require_login();
        if (!function_exists('is_system_admin') || !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. System admin required.');
        }
        $pdo = db();
        $errors = [];
        $messages = [];
        $success = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                $errors[] = 'Invalid request. Please try again.';
            } else {
                unset($_SESSION['csrf_token']);
                $settingsToUpdate = [
                    'storage_base_dir',
                    'contracts_generated_subdir',
                    'docx_template_dir',
                    'html_template_dir',
                    'default_docx_template',
                    'default_html_template',
                    'default_email_message',
                    'compliance_info_link',
                ];
                foreach ($settingsToUpdate as $key) {
                    $value = trim((string)($_POST[$key] ?? ''));
                    if ($value === '' && !in_array($key, ['default_email_message', 'compliance_info_link'])) {
                        $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' cannot be empty.';
                        continue;
                    }
                    if (in_array($key, ['storage_base_dir', 'docx_template_dir', 'html_template_dir'])) {
                        if (!str_starts_with($value, '/') || !is_dir($value)) {
                            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' must be a valid absolute directory path.';
                            continue;
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                    $stmt->execute([$value, current_person_id() ?: null, $key]);
                }
                if (empty($errors)) {
                    $success = true;
                    $messages[] = 'Settings updated successfully.';
                }
            }
        }
        $_SESSION['admin_settings_errors'] = $errors;
        $_SESSION['admin_settings_messages'] = $messages;
        $_SESSION['admin_settings_success'] = $success;
        $this->index();
        return;
    }
}
