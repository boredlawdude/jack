<?php
declare(strict_types=1);

class CompaniesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function index(): void
    {
        $stmt = $this->db->query("
            SELECT company_id, name, vendor_id, address, phone, email, contact_name, verified_by, is_active
            FROM companies
            ORDER BY name
        ");

        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require APP_ROOT . '/app/views/companies/index.php';
    }

    public function create(): void
    {
        $mode = 'create';
        $errors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);

        $company = $_SESSION['old_company_form'] ?? [
            'type' => 'vendor',
            'is_active' => 1,
        ];
        unset($_SESSION['old_company_form']);

        $companyTypes = $this->getCompanyTypes();
        $townEmployees = $this->getTownEmployees();
        $linkPeople = [];
        $employees = [];

        require APP_ROOT . '/app/views/companies/edit.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $data = $this->collectCompanyData($_POST);
        $peopleRows = $this->collectPeopleRows($_POST['people'] ?? []);
        $errors = $this->validateCompany($data, $peopleRows);

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_company_form'] = array_merge($data, ['people' => $peopleRows]);
            header('Location: /index.php?page=companies_create');
            exit;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO companies
                    (
                        name, type, tax_id,
                        address_line1, address_line2, city, state_region, postal_code, country,
                        address, phone, email, vendor_id, contact_name, verified_by,
                        company_type_id, state_of_incorporation,
                        is_active,
                        coi_exp_date, coi_carrier, coi_verified_by_person_id
                    )
                VALUES
                    (
                        :name, :type, :tax_id,
                        :address_line1, :address_line2, :city, :state_region, :postal_code, :country,
                        :address, :phone, :email, :vendor_id, :contact_name, :verified_by,
                        :company_type_id, :state_of_incorporation,
                        :is_active,
                        :coi_exp_date, :coi_carrier, :coi_verified_by_person_id
                    )
            ");

            $stmt->execute($data);

            $companyId = (int)$this->db->lastInsertId();

            if ($peopleRows) {
                $pStmt = $this->db->prepare("
                    INSERT INTO people
                        (company_id, first_name, last_name, full_name, email, officephone, cellphone, is_active)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1)
                ");

                foreach ($peopleRows as $p) {
                    $pStmt->execute([
                        $companyId,
                        $p['first_name'],
                        $p['last_name'],
                        $p['full_name'],
                        $p['email'],
                        $p['officephone'],
                        $p['cellphone'],
                    ]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $_SESSION['flash_errors'] = ['Database error: ' . $e->getMessage()];
            $_SESSION['old_company_form'] = array_merge($data, ['people' => $peopleRows]);
            header('Location: /index.php?page=companies_create');
            exit;
        }

        unset($_SESSION['old_company_form']);

        header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
        exit;
    }

    public function edit(int $companyId): void
    {
        if ($companyId <= 0) {
            echo 'Missing company id';
            return;
        }
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE company_id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            echo 'Company not found';
            return;
        }
        $mode = 'edit';
        $old = $_SESSION['old_company_form'] ?? null;
        unset($_SESSION['old_company_form']);
        if ($old) {
            $company = array_merge($company, $old);
        }
        $companyTypes = $this->getCompanyTypes();
        $employees = $this->getCompanyPeople($companyId);
        $linkPeople = [];
        $townEmployees = $this->getTownEmployees();
        // Load company comments
        $comments = $this->getCompanyComments($companyId);
        require APP_ROOT . '/app/views/companies/edit.php';
    }

    private function getCompanyComments(int $companyId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cc.company_comment_id,
                cc.comment_text,
                cc.created_at,
                p.person_id,
                COALESCE(NULLIF(p.full_name,''), CONCAT_WS(' ', p.first_name, p.last_name)) AS author_name
            FROM company_comments cc
            JOIN people p ON p.person_id = cc.person_id
            WHERE cc.company_id = ?
            ORDER BY cc.created_at DESC, cc.company_comment_id DESC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $companyId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $stmt = $this->db->prepare("SELECT company_id FROM companies WHERE company_id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            echo 'Company not found';
            return;
        }

        $data = $this->collectCompanyData($_POST);
        $errors = $this->validateCompany($data);

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['old_company_form'] = $data;
            header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
            exit;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE companies SET
                    name = :name,
                    type = :type,
                    tax_id = :tax_id,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state_region = :state_region,
                    postal_code = :postal_code,
                    country = :country,
                    address = :address,
                    phone = :phone,
                    email = :email,
                    vendor_id = :vendor_id,
                    contact_name = :contact_name,
                    verified_by = :verified_by,
                    company_type_id = :company_type_id,
                    state_of_incorporation = :state_of_incorporation,
                    is_active = :is_active,
                    coi_exp_date = :coi_exp_date,
                    coi_carrier = :coi_carrier,
                    coi_verified_by_person_id = :coi_verified_by_person_id
                WHERE company_id = :company_id
            ");

            $data['company_id'] = $companyId;
            $stmt->execute($data);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Database error: ' . $e->getMessage()];
            $_SESSION['old_company_form'] = $data;
            header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
            exit;
        }

        unset($_SESSION['old_company_form']);

        header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
        exit;
    }

    public function linkPerson(int $companyId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $personId = (int)($_POST['person_id'] ?? 0);
        if ($companyId <= 0 || $personId <= 0) {
            http_response_code(400);
            echo 'Invalid request';
            return;
        }

        $stmt = $this->db->prepare("UPDATE people SET company_id = ? WHERE person_id = ?");
        $stmt->execute([$companyId, $personId]);

        header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
        exit;
    }

    public function unlinkPerson(int $companyId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed.';
            return;
        }

        $personId = (int)($_POST['person_id'] ?? 0);
        if ($companyId <= 0 || $personId <= 0) {
            http_response_code(400);
            echo 'Invalid request';
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE people
            SET company_id = NULL
            WHERE person_id = ? AND company_id = ?
        ");
        $stmt->execute([$personId, $companyId]);

        header('Location: /index.php?page=companies_edit&company_id=' . $companyId);
        exit;
    }

    private function collectCompanyData(array $input): array
    {
        return [
            'name' => trim((string)($input['name'] ?? '')),
            'type' => trim((string)($input['type'] ?? 'vendor')),
            'tax_id' => $this->nullIfEmpty($input['tax_id'] ?? null),
            'address_line1' => $this->nullIfEmpty($input['address_line1'] ?? null),
            'address_line2' => $this->nullIfEmpty($input['address_line2'] ?? null),
            'city' => $this->nullIfEmpty($input['city'] ?? null),
            'state_region' => $this->nullIfEmpty($input['state_region'] ?? null),
            'postal_code' => $this->nullIfEmpty($input['postal_code'] ?? null),
            'country' => $this->nullIfEmpty($input['country'] ?? null),
            'address' => $this->nullIfEmpty($input['address'] ?? null),
            'phone' => $this->nullIfEmpty($input['phone'] ?? null),
            'email' => $this->nullIfEmpty($input['email'] ?? null),
            'vendor_id' => $this->nullIfEmpty($input['vendor_id'] ?? null),
            'contact_name' => $this->nullIfEmpty($input['contact_name'] ?? null),
            'verified_by' => $this->nullIfEmpty($input['verified_by'] ?? null),
            'company_type_id' => $this->nullableInt($input['company_type_id'] ?? null),
            'state_of_incorporation' => $this->nullIfEmpty($input['state_of_incorporation'] ?? null),
            'is_active' => isset($input['is_active']) ? 1 : 0,
            'coi_exp_date' => $this->nullIfEmpty($input['coi_exp_date'] ?? null),
            'coi_carrier' => $this->nullIfEmpty($input['coi_carrier'] ?? null),
            'coi_verified_by_person_id' => $this->nullableInt($input['coi_verified_by_person_id'] ?? null),
        ];
    }

    private function collectPeopleRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $person = [
                'first_name' => $this->nullIfEmpty($row['first_name'] ?? null),
                'last_name' => $this->nullIfEmpty($row['last_name'] ?? null),
                'full_name' => $this->nullIfEmpty($row['full_name'] ?? null),
                'email' => $this->nullIfEmpty($row['email'] ?? null),
                'officephone' => $this->nullIfEmpty($row['officephone'] ?? null),
                'cellphone' => $this->nullIfEmpty($row['cellphone'] ?? null),
            ];

            if (
                $person['first_name'] === null &&
                $person['last_name'] === null &&
                $person['full_name'] === null &&
                $person['email'] === null &&
                $person['officephone'] === null &&
                $person['cellphone'] === null
            ) {
                continue;
            }

            $clean[] = $person;
        }

        return $clean;
    }

    private function validateCompany(array $data, array $peopleRows = []): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Company Name is required.';
        }

        foreach ($peopleRows as $p) {
            $hasName = !empty($p['full_name']) || (!empty($p['first_name']) && !empty($p['last_name']));
            if (!$hasName) {
                $errors[] = 'Each person row needs either Full Name or First+Last name.';
                break;
            }
        }

        return $errors;
    }

    private function getCompanyTypes(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT company_type_id, company_type
                FROM company_types
                WHERE is_active = 1
                ORDER BY company_type
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function getAllActivePeople(): array
    {
        $stmt = $this->db->query("
            SELECT person_id, first_name, last_name, email, company_id
            FROM people
            WHERE is_active = 1
            ORDER BY last_name, first_name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCompanyPeople(int $companyId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.person_id,
                CONCAT_WS(' ', p.first_name, p.last_name) AS person_name,
                p.email,
                p.officephone,
                p.cellphone,
                p.is_town_employee,
                d.department_name,
                p.is_active
            FROM people p
            LEFT JOIN departments d ON d.department_id = p.department_id
            WHERE p.company_id = ?
            ORDER BY p.is_active DESC, p.last_name, p.first_name
        ");
        $stmt->execute([$companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTownEmployees(): array
    {
        $stmt = $this->db->query("
            SELECT
                person_id,
                COALESCE(NULLIF(full_name,''), CONCAT_WS(' ', first_name, last_name)) AS display_name,
                email
            FROM people
            WHERE is_active = 1
              AND is_town_employee = 1
            ORDER BY display_name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function nullIfEmpty(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string)$value);
    }
}