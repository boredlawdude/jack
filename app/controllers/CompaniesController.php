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
            SELECT company_id, name, vendor_id, address, phone, email, contact_name, verified_by, is_active, sosid
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
                        coi_exp_date, coi_carrier, coi_verified_by_person_id,
                        sosid,
                        signer1_name, signer1_title, signer1_email,
                        signer2_name, signer2_title, signer2_email,
                        signer3_name, signer3_title, signer3_email
                    )
                VALUES
                    (
                        :name, :type, :tax_id,
                        :address_line1, :address_line2, :city, :state_region, :postal_code, :country,
                        :address, :phone, :email, :vendor_id, :contact_name, :verified_by,
                        :company_type_id, :state_of_incorporation,
                        :is_active,
                        :coi_exp_date, :coi_carrier, :coi_verified_by_person_id,
                        :sosid,
                        :signer1_name, :signer1_title, :signer1_email,
                        :signer2_name, :signer2_title, :signer2_email,
                        :signer3_name, :signer3_title, :signer3_email
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

    public function show(int $companyId): void
    {
        if ($companyId <= 0) {
            http_response_code(404);
            echo 'Company not found.';
            return;
        }

        // Full company row + company type name + COI verifier name
        $stmt = $this->db->prepare("
            SELECT c.*,
                   ct.company_type    AS company_type_name,
                   COALESCE(NULLIF(p.full_name,''), CONCAT_WS(' ', p.first_name, p.last_name)) AS coi_verified_by_name
            FROM companies c
            LEFT JOIN company_types ct ON ct.company_type_id = c.company_type_id
            LEFT JOIN people p         ON p.person_id = c.coi_verified_by_person_id
            WHERE c.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            http_response_code(404);
            echo 'Company not found.';
            return;
        }

        $employees = $this->getCompanyPeople($companyId);
        $comments  = $this->getCompanyComments($companyId);

        // Contracts where this company is the counterparty
        $cStmt = $this->db->prepare("
            SELECT c.contract_id, c.name, c.contract_number, c.start_date, c.end_date,
                   cs.contract_status_name AS status_name
            FROM contracts c
            LEFT JOIN contract_statuses cs ON cs.contract_status_id = c.contract_status_id
            WHERE c.counterparty_company_id = ?
            ORDER BY c.start_date DESC
            LIMIT 50
        ");
        $cStmt->execute([$companyId]);
        $contracts = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        require APP_ROOT . '/app/views/companies/show.php';
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
                    coi_verified_by_person_id = :coi_verified_by_person_id,
                    sosid = :sosid,
                    signer1_name = :signer1_name,
                    signer1_title = :signer1_title,
                    signer1_email = :signer1_email,
                    signer2_name = :signer2_name,
                    signer2_title = :signer2_title,
                    signer2_email = :signer2_email,
                    signer3_name = :signer3_name,
                    signer3_title = :signer3_title,
                    signer3_email = :signer3_email
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

    // -------------------------------------------------------------------------
    // Vendor PDF Import — upload a Vendor/Supplier Info Form PDF and
    // pre-fill the company create form from its extracted fields.
    // -------------------------------------------------------------------------

    public function vendorPdfImport(): void
    {
        $errors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_errors']);
        require APP_ROOT . '/app/views/companies/vendor_pdf_import.php';
    }

    public function vendorPdfImportProcess(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        // Validate upload
        if (
            empty($_FILES['vendor_pdf']) ||
            $_FILES['vendor_pdf']['error'] !== UPLOAD_ERR_OK
        ) {
            $_SESSION['flash_errors'] = ['Please select a PDF file to upload.'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        $file = $_FILES['vendor_pdf'];

        // Validate file type: must be PDF
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            $_SESSION['flash_errors'] = ['Only PDF files are accepted.'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        // Find pdftotext binary
        $pdftotext = $this->findPdftotextBinary();
        if ($pdftotext === null) {
            $_SESSION['flash_errors'] = ['pdftotext is not installed on this server. Install poppler-utils (Linux) or poppler (Homebrew) and try again.'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        // Extract text from PDF into a temp file
        $tmpText = tempnam(sys_get_temp_dir(), 'vendor_pdf_') . '.txt';
        $cmd     = escapeshellarg($pdftotext) . ' ' . escapeshellarg($file['tmp_name']) . ' ' . escapeshellarg($tmpText) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($tmpText)) {
            @unlink($tmpText);
            $_SESSION['flash_errors'] = ['Could not extract text from the PDF. Make sure the file is not password-protected.'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        $text = (string)file_get_contents($tmpText);
        @unlink($tmpText);

        if ($text === '') {
            $_SESSION['flash_errors'] = ['The PDF appears to be empty or contains only images (no extractable text).'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        // Parse the extracted text into company fields
        $parsed = $this->parseVendorPdfText($text);

        if (empty($parsed['name'])) {
            $_SESSION['flash_errors'] = ['Could not find a Company Name in the PDF. Please check that this is the correct Vendor/Supplier Information Form.'];
            header('Location: /index.php?page=companies_vendor_pdf_import');
            exit;
        }

        // Store parsed values in session so the create form picks them up
        $_SESSION['old_company_form'] = array_merge(
            [
                'type'      => 'vendor',
                'is_active' => 1,
            ],
            $parsed
        );

        $_SESSION['flash_success'] = 'PDF imported successfully — please review the pre-filled fields and save.';
        header('Location: /index.php?page=companies_create');
        exit;
    }

    /**
     * Locate the pdftotext binary.
     */
    private function findPdftotextBinary(): ?string
    {
        $candidates = [
            '/opt/homebrew/bin/pdftotext',   // macOS Homebrew (ARM)
            '/usr/local/bin/pdftotext',      // macOS Homebrew (Intel) / Linux
            '/usr/bin/pdftotext',            // Linux system poppler-utils
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }
        // Fallback: check PATH
        $which = trim((string)shell_exec('which pdftotext 2>/dev/null'));
        if ($which !== '' && is_file($which)) {
            return $which;
        }
        return null;
    }

    /**
     * Parse the text extracted from a Vendor/Supplier Information Form PDF.
     *
     * pdftotext linearises the two-column layout as:
     *   [left label]  [right label]
     *   [left value]  [right value]
     *
     * The patterns below are calibrated against the Town of Holly Springs form.
     */
    private function parseVendorPdfText(string $text): array
    {
        // Normalise line endings; collapse runs of 3+ blank lines into 2
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        $grab = static function (string $pattern, string $subject, int $group = 1): string {
            return preg_match($pattern, $subject, $m) ? trim($m[$group]) : '';
        };

        // ── Company name ──────────────────────────────────────────────────────
        $name = $grab('/Company Name\s*\n\s*([^\n]+)/i', $text);
        // Strip known boilerplate that may appear on the same line
        $name = preg_replace('/\s*The Town of.*$/i', '', $name);

        // ── Type of business ownership → stored in the free-text `type` field
        $type = $grab('/Type of Business Ownership\s*\n\s*([^\n]+)/i', $text);
        if ($type === '') {
            $type = 'vendor';
        }

        // ── Address (Accounts Payable / Remit To) ────────────────────────────
        // Street Address label is followed by a blank line, then the value,
        // then immediately "Address Line 2" (if no value) or the value then "Address Line 2".
        $address_line1 = $grab('/Street Address\s*\n\n\s*([^\n]+)/i', $text);

        // Address Line 2: content between the label and the City label
        // If the line after "Address Line 2\n" is NOT "City", it's the value.
        $address_line2 = '';
        if (preg_match('/Address Line 2\s*\n\s*([^\n]+)/i', $text, $m)) {
            $candidate = trim($m[1]);
            if (!preg_match('/^City\b/i', $candidate)) {
                $address_line2 = $candidate;
            }
        }

        // City (left column) and State (right column) labels appear together;
        // their values appear together on the next non-blank pair.
        // Pattern: City\n\nState/...\n\n[city]\n\n[state]
        $city         = '';
        $state_region = '';
        if (preg_match('/City\s*\n\n\s*State\/Province\/Region\s*\n\n\s*([^\n]+)\n\n\s*([^\n]+)/i', $text, $m)) {
            $city         = trim($m[1]);
            $state_region = trim($m[2]);
        } elseif (preg_match('/City\s*\n\n\s*State\/Province\/Region\s*\n\s*([^\n]+)\s*\n\s*([^\n]+)/i', $text, $m)) {
            $city         = trim($m[1]);
            $state_region = trim($m[2]);
        }

        // Postal code (left) and Country (right)
        $postal_code = '';
        $country     = '';
        if (preg_match('/Postal\/Zip Code\s*\n\n\s*Country\s*\n\n\s*([^\n]+)\n\n\s*([^\n]+)/i', $text, $m)) {
            $postal_code = trim($m[1]);
            $country     = trim($m[2]);
        } elseif (preg_match('/Postal\/Zip Code\s*\n\n\s*Country\s*\n\s*([^\n]+)\s*\n\s*([^\n]+)/i', $text, $m)) {
            $postal_code = trim($m[1]);
            $country     = trim($m[2]);
        }

        // ── Phone and Email ───────────────────────────────────────────────────
        // Labels "Phone Number *" and "Email *" appear side by side; values follow.
        $phone = '';
        $email = '';
        if (preg_match('/Phone Number\s*\*\s*\n\n\s*Email\s*\*\s*\n\n\s*([^\n]+)\n\n\s*([^\n]+)/i', $text, $m)) {
            $phone = trim($m[1]);
            $email = trim($m[2]);
        } elseif (preg_match('/Phone Number\s*\*\s*\n\s*Email\s*\*\s*\n\s*([^\n]+)\s*\n\s*([^\n]+)/i', $text, $m)) {
            $phone = trim($m[1]);
            $email = trim($m[2]);
        } elseif (preg_match('/Phone Number\s*\*\s*\n\n\s*([^\n]+)/i', $text, $m)) {
            $phone = trim($m[1]);
        }

        // ── Contact Name ─────────────────────────────────────────────────────
        // "Contact Name: *" and "Contact Phone: *" appear side by side; name is first value.
        $contact_name = $grab('/Contact Name:\s*\*\s*\n\n\s*Contact Phone:\s*\*\s*\n\n\s*([^\n]+)/i', $text);
        if ($contact_name === '') {
            // Some forms have only one label
            $contact_name = $grab('/Contact Name:\s*\*?\s*\n\n\s*([^\n]+)/i', $text);
        }
        // Strip helper text like "Enter first and last name."
        if (stripos($contact_name, 'Enter') === 0) {
            $contact_name = '';
        }

        // ── Contact Title ─────────────────────────────────────────────────────
        // Two-column layout: "Contact Title:" label appears on the left,
        // "Contact Email..." label on the right.  pdftotext linearises this as:
        //   Contact Title:\n\n[helper text]\n\nContact Email...:\n\n[title value]
        // So the title value sits just after the "Contact Email" label.
        $contact_title = '';
        if (preg_match('/Contact Email[^\n]*:\s*\n\n\s*([^\n]+)/i', $text, $m)) {
            $candidate = trim($m[1]);
            // If it's not an email address and not boilerplate, it's the title
            if (
                !filter_var($candidate, FILTER_VALIDATE_EMAIL) &&
                !preg_match('/^(Individuals|Companies|Bank Account|Enter |Upload)/i', $candidate)
            ) {
                $contact_title = $candidate;
            }
        }

        // ── Contact email (may differ from main email) ────────────────────────
        // Already captured in $email above; if there's a dedicated "Contact Email" value:
        $contact_email = $grab('/Contact Email.*?:\s*\n\n\s*([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $text);
        if ($contact_email === '') {
            $contact_email = $email;
        }

        // ── Build result ──────────────────────────────────────────────────────
        $result = [
            'name'          => $name,
            'type'          => $type ?: 'vendor',
            'address_line1' => $address_line1,
            'address_line2' => $address_line2,
            'city'          => $city,
            'state_region'  => $state_region,
            'postal_code'   => $postal_code,
            'country'       => $country,
            'phone'         => $phone,
            'email'         => $email,
            'contact_name'  => $contact_name,
            'is_active'     => 1,
        ];

        // Pre-fill signer 1 with contact person if we have their info
        if ($contact_name !== '') {
            $result['signer1_name']  = $contact_name;
            $result['signer1_title'] = $contact_title;
            $result['signer1_email'] = $contact_email;
        }

        return array_filter($result, static fn($v) => $v !== '');
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
            'sosid' => $this->nullIfEmpty($input['sosid'] ?? null),
            'signer1_name'  => $this->nullIfEmpty($input['signer1_name']  ?? null),
            'signer1_title' => $this->nullIfEmpty($input['signer1_title'] ?? null),
            'signer1_email' => $this->nullIfEmpty($input['signer1_email'] ?? null),
            'signer2_name'  => $this->nullIfEmpty($input['signer2_name']  ?? null),
            'signer2_title' => $this->nullIfEmpty($input['signer2_title'] ?? null),
            'signer2_email' => $this->nullIfEmpty($input['signer2_email'] ?? null),
            'signer3_name'  => $this->nullIfEmpty($input['signer3_name']  ?? null),
            'signer3_title' => $this->nullIfEmpty($input['signer3_title'] ?? null),
            'signer3_email' => $this->nullIfEmpty($input['signer3_email'] ?? null),
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

    public function bulkDestroy(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /index.php?page=companies');
            exit;
        }

        $raw = $_POST['company_ids'] ?? [];
        if (!is_array($raw) || empty($raw)) {
            header('Location: /index.php?page=companies');
            exit;
        }

        $ids = array_values(array_filter(array_map('intval', $raw), fn(int $id) => $id > 0));
        if (empty($ids)) {
            header('Location: /index.php?page=companies');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $this->db->beginTransaction();
            // Nullify FK references that are RESTRICT (no cascade)
            $this->db->prepare("UPDATE contracts SET counterparty_company_id = NULL WHERE counterparty_company_id IN ($placeholders)")->execute($ids);
            $this->db->prepare("UPDATE contracts SET owner_company_id = NULL WHERE owner_company_id IN ($placeholders)")->execute($ids);
            // people.company_id is SET NULL on delete, but nullify explicitly to be safe
            $this->db->prepare("UPDATE people SET company_id = NULL WHERE company_id IN ($placeholders)")->execute($ids);
            $this->db->prepare("DELETE FROM companies WHERE company_id IN ($placeholders)")->execute($ids);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $_SESSION['flash_errors'] = ['Delete failed: ' . $e->getMessage()];
            header('Location: /index.php?page=companies');
            exit;
        }

        $count = count($ids);
        $_SESSION['flash_messages'] = [($count === 1 ? '1 company' : "$count companies") . ' deleted.'];
        header('Location: /index.php?page=companies');
        exit;
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