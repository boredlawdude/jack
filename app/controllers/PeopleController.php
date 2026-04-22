<?php
declare(strict_types=1);

require_once APP_ROOT . '/app/models/Person.php';

final class PeopleController
{
    private PDO $pdo;
    private Person $people;

    public function __construct()
    {
        $this->pdo = db();
        $this->people = new Person($this->pdo);
    }

    public function index(): void
    {
        $stmt = $this->pdo->query("
            SELECT p.*, d.department_name
            FROM people p
            LEFT JOIN departments d ON d.department_id = p.department_id
            ORDER BY p.last_name, p.first_name
        ");

        $people = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        require APP_ROOT . '/app/views/people/index.php';
        
    }
    public function create(): void
    {
        $person = $this->emptyPerson();
        // Pre-fill company_id if provided in query string
        if (!empty($_GET['company_id'])) {
            $person['company_id'] = (int)$_GET['company_id'];
        }
        $departments = $this->people->allDepartments();
        $errors = [];
        require APP_ROOT . '/app/views/people/create.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /index.php?page=people_create');
            exit;
        }

        $person = $this->collectFormData();
        $errors = $this->validate($person);

        if ($errors) {
            $departments = $this->people->allDepartments();
            
            require APP_ROOT . '/app/views/people/create.php';
            
            return;
        }

        $id = $this->people->create($person);
        header('Location: /index.php?page=people_edit&id=' . $id . '&saved=1');
        exit;
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $person = $this->people->findById($id);
        if (!$person) {
            http_response_code(404);
            echo 'Person not found';
            return;
        }
        $departments = $this->people->allDepartments();
        $errors = [];
        $can_edit_roles = (function_exists('person_has_role_key') && (person_has_role_key('SUPERUSER') || person_has_role_key('ADMIN')));
        $roles = [];
        $assigned_role_ids = [];
        if ($can_edit_roles) {
            $pdo = $this->pdo;
            $roles = $pdo->query("SELECT * FROM roles WHERE is_active = 1 ORDER BY COALESCE(role_name, role_key), role_key")->fetchAll(\PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT role_id FROM person_roles WHERE person_id = ?");
            $stmt->execute([$id]);
            $assigned_role_ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        }
        require APP_ROOT . '/app/views/people/edit.php';
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /index.php?page=people');
            exit;
        }
        $id = (int)($_POST['person_id'] ?? 0);
        $existing = $this->people->findById($id);
        if (!$existing) {
            http_response_code(404);
            echo 'Person not found';
            return;
        }
        $person = $this->collectFormData();
        $person['person_id'] = $id;
        $errors = $this->validate($person);
        $can_edit_roles = (function_exists('person_has_role_key') && (person_has_role_key('SUPERUSER') || person_has_role_key('ADMIN')));
        if ($errors) {
            $departments = $this->people->allDepartments();
            $roles = [];
            $assigned_role_ids = [];
            if ($can_edit_roles) {
                $pdo = $this->pdo;
                $roles = $pdo->query("SELECT * FROM roles WHERE is_active = 1 ORDER BY COALESCE(role_name, role_key), role_key")->fetchAll(\PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("SELECT role_id FROM person_roles WHERE person_id = ?");
                $stmt->execute([$id]);
                $assigned_role_ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            }
            require APP_ROOT . '/app/views/people/edit.php';
            return;
        }
        $this->people->update($id, $person);
        // Handle roles if allowed
        if ($can_edit_roles) {
            $pdo = $this->pdo;
            $posted_roles = $_POST['role_ids'] ?? [];
            if (!is_array($posted_roles)) $posted_roles = [];
            $posted_roles = array_values(array_filter($posted_roles, fn($x) => ctype_digit((string)$x)));
            $posted_roles = array_map('intval', $posted_roles);
            // Remove all, then add back
            $pdo->prepare("DELETE FROM person_roles WHERE person_id = ?")->execute([$id]);
            if ($posted_roles) {
                $ins = $pdo->prepare("INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)");
                foreach ($posted_roles as $rid) {
                    $ins->execute([$id, $rid]);
                }
            }
        }
        header('Location: /index.php?page=people_edit&id=' . $id . '&saved=1');
        exit;
    }

    private function collectFormData(): array
    {
        return [
            'first_name'    => trim((string)($_POST['first_name'] ?? '')),
            'last_name'     => trim((string)($_POST['last_name'] ?? '')),
            'display_name'  => trim((string)($_POST['display_name'] ?? '')),
            'email'         => trim((string)($_POST['email'] ?? '')),
            'officephone'  => trim((string)($_POST['office_phone'] ?? '')),
            'cellphone'    => trim((string)($_POST['cell_phone'] ?? '')),
            'title'         => trim((string)($_POST['title'] ?? '')),
            'department_id' => trim((string)($_POST['department_id'] ?? '')),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            'is_town_employee' => isset($_POST['is_town_employee']) ? 1 : 0,
            'company_id'    => isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['first_name'] === '') {
            $errors[] = 'First name is required.';
        }

        if ($data['last_name'] === '') {
            $errors[] = 'Last name is required.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        return $errors;
    }

    public function setPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        require_login();
        if (!function_exists('person_has_role_key') || !person_has_role_key('SUPERUSER')) {
            http_response_code(403);
            exit('Access denied. Superuser required.');
        }

        $id       = (int)($_POST['person_id'] ?? 0);
        $password = (string)($_POST['new_password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid person ID.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors) {
            $_SESSION['people_set_password_errors'] = $errors;
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE people SET password_hash = ?, can_login = 1 WHERE person_id = ?");
            $stmt->execute([$hash, $id]);
            $_SESSION['people_set_password_success'] = true;
        }

        header('Location: /index.php?page=people_edit&id=' . $id . '#password-section');
        exit;
    }

    private function emptyPerson(): array
    {
        return [
            'person_id'     => null,
            'first_name'    => '',
            'last_name'     => '',
            'display_name'  => '',
            'email'         => '',
            'office_phone'  => '',
            'cell_phone'    => '',
            'title'         => '',
            'department_id' => '',
            'is_active'     => 1,
        ];
    }
}