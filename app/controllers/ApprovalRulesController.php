<?php
declare(strict_types=1);

class ApprovalRulesController
{
    private PDO $db;

    // Human-readable labels for the approvable contract fields
    public const FIELD_OPTIONS = [
        'total_contract_value' => 'Contract Value ($)',
        'renewal_term_months'  => 'Renewal Term (months)',
        'contract_type_id'     => 'Contract Type',
    ];

    // Human-readable labels for approval types
    public const APPROVAL_LABELS = [
        'manager'      => 'Manager',
        'purchasing'   => 'Purchasing',
        'legal'        => 'Legal',
        'risk_manager' => 'Risk Manager',
        'council'      => 'Council',
    ];

    public const OPERATORS = ['>' => '>', '>=' => '>=', '<' => '<', '<=' => '<=', '=' => '=', '!=' => '!='];

    // Maps approval type → role_key required to stamp (null = no role check)
    public const APPROVAL_ROLE_MAP = [
        'manager'      => 'TOWN_MANAGER',
        'purchasing'   => 'PROCUREMENT',
        'legal'        => 'LEGAL_ADMIN',
        'risk_manager' => null,
        'council'      => 'TOWN_COUNCIL',
    ];

    /**
     * Returns the set of approval keys the current user holds the matching role for.
     * Approval types with null role mapping are always considered held.
     */
    public static function getApprovalRolesForCurrentUser(): array
    {
        $held = [];
        foreach (self::APPROVAL_ROLE_MAP as $approvalKey => $roleKey) {
            if ($roleKey === null || (function_exists('person_has_role_key') && person_has_role_key($roleKey))) {
                $held[] = $approvalKey;
            }
        }
        return $held;
    }

    public function __construct()
    {
        $this->db = db();
    }

    public function index(): void
    {
        require_login();
        $this->requireAdmin();

        $rules = $this->db->query("SELECT * FROM approval_rules ORDER BY sort_order, rule_id")->fetchAll(PDO::FETCH_ASSOC);
        $contractTypes = $this->db->query("SELECT contract_type_id, contract_type FROM contract_types WHERE is_active = 1 ORDER BY contract_type")->fetchAll(PDO::FETCH_ASSOC);
        $flashMessages = $_SESSION['flash_messages'] ?? [];
        $flashErrors   = $_SESSION['flash_errors']   ?? [];
        unset($_SESSION['flash_messages'], $_SESSION['flash_errors']);

        require APP_ROOT . '/app/views/admin_settings/approval_rules.php';
    }

    public function store(): void
    {
        require_login();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect(); }

        $data = $this->collect($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $this->redirect();
        }

        $this->db->prepare("
            INSERT INTO approval_rules (rule_name, contract_field, operator, threshold_value, required_approval, is_active, sort_order, waived_by_standard_contract, waived_by_min_insurance)
            VALUES (:rule_name, :contract_field, :operator, :threshold_value, :required_approval, :is_active, :sort_order, :waived_by_standard_contract, :waived_by_min_insurance)
        ")->execute($data);

        $_SESSION['flash_messages'] = ['Rule created.'];
        $this->redirect();
    }

    public function update(int $ruleId): void
    {
        require_login();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect(); }

        $data = $this->collect($_POST);
        $errors = $this->validate($data);

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $this->redirect();
        }

        $data['rule_id'] = $ruleId;
        $this->db->prepare("
            UPDATE approval_rules
            SET rule_name=:rule_name, contract_field=:contract_field, operator=:operator,
                threshold_value=:threshold_value, required_approval=:required_approval,
                is_active=:is_active, sort_order=:sort_order,
                waived_by_standard_contract=:waived_by_standard_contract,
                waived_by_min_insurance=:waived_by_min_insurance
            WHERE rule_id=:rule_id
        ")->execute($data);

        $_SESSION['flash_messages'] = ['Rule updated.'];
        $this->redirect();
    }

    public function destroy(int $ruleId): void
    {
        require_login();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect(); }

        $this->db->prepare("DELETE FROM approval_rules WHERE rule_id = ?")->execute([$ruleId]);

        $_SESSION['flash_messages'] = ['Rule deleted.'];
        $this->redirect();
    }

    // ── Stamp an approval date on a contract ────────────────────────────────
    public function stampApproval(): void
    {
        require_login();
        if (!is_system_admin()) {
            http_response_code(403); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit;
        }

        $contractId   = (int)($_POST['contract_id'] ?? 0);
        $approvalType = trim($_POST['approval_type'] ?? '');

        $allowedCols = [
            'manager'      => 'manager_approval_date',
            'purchasing'   => 'purchasing_approval_date',
            'legal'        => 'legal_approval_date',
            'risk_manager' => 'risk_manager_approval_date',
            'council'      => 'council_approval_date',
        ];

        if ($contractId <= 0 || !isset($allowedCols[$approvalType])) {
            $_SESSION['flash_errors'] = ['Invalid approval request.'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $dateVal    = date('Y-m-d'); // always stamp today
        $isBypass   = !empty($_POST['bypass_warning']);

        // Role check
        $requiredRole = self::APPROVAL_ROLE_MAP[$approvalType] ?? null;
        $userHasRole  = ($requiredRole === null) || (function_exists('person_has_role_key') && person_has_role_key($requiredRole));

        // Server-side guard: if no role and no explicit bypass, reject
        if (!$userHasRole && !$isBypass) {
            $_SESSION['flash_errors'] = ['You do not have the required role to stamp this approval. Check the bypass box to override.'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $col = $allowedCols[$approvalType];
        $this->db->prepare("UPDATE contracts SET `$col` = ? WHERE contract_id = ?")->execute([$dateVal, $contractId]);

        // Log to contract history
        $person     = current_person();
        $personName = trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        if (empty(trim($personName))) $personName = $person['display_name'] ?? $person['email'] ?? 'Unknown';
        $label    = self::APPROVAL_LABELS[$approvalType] ?? $approvalType;
        $personId = !empty($person['person_id']) ? (int)$person['person_id'] : null;

        if ($isBypass && !$userHasRole) {
            $requiredRoleName = $requiredRole ?? 'N/A';
            $note = "$label approved by $personName on $dateVal [BYPASS — user lacked required role: $requiredRoleName]";
        } else {
            $note = "$label approved by $personName on $dateVal";
        }

        $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, 'approval', NULL, NULL, ?, NOW(), ?)"
        )->execute([$contractId, $personId, $note]);

        $flashMsg = $isBypass && !$userHasRole
            ? "$label approval stamped (role bypass recorded in history)."
            : "$label approval stamped for $dateVal.";
        $_SESSION['flash_messages'] = [$flashMsg];
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    // ── Static helper: evaluate which approvals are required for a contract ─
    /**
     * Returns array of required approval keys for the given contract row.
     * e.g. ['manager', 'purchasing', 'legal']
     */
    public static function requiredApprovalsFor(PDO $db, array $contract): array
    {
        $rules = $db->query("SELECT * FROM approval_rules WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        $required = [];
        $isStandard    = !empty($contract['use_standard_contract']);
        $hasMinInsurance = !empty($contract['minimum_insurance_coi']);

        foreach ($rules as $rule) {
            // Skip rules waived by standard contract when applicable
            if ($isStandard && !empty($rule['waived_by_standard_contract'])) {
                continue;
            }
            // Skip rules waived by minimum insurance COI when applicable
            if ($hasMinInsurance && !empty($rule['waived_by_min_insurance'])) {
                continue;
            }

            $field = $rule['contract_field'];
            if (!array_key_exists($field, $contract)) continue;

            $contractVal = (float)($contract[$field] ?? 0);
            $threshold   = (float)$rule['threshold_value'];

            $match = match ($rule['operator']) {
                '>'  => $contractVal >  $threshold,
                '>=' => $contractVal >= $threshold,
                '<'  => $contractVal <  $threshold,
                '<=' => $contractVal <= $threshold,
                '='  => $contractVal == $threshold,
                '!=' => $contractVal != $threshold,
                default => false,
            };

            if ($match) {
                $required[] = $rule['required_approval'];
            }
        }

        return array_unique($required);
    }

    // ── Private helpers ───────────────────────────────────────────────────────
    private function collect(array $input): array
    {
        return [
            'rule_name'                   => trim((string)($input['rule_name'] ?? '')),
            'contract_field'              => trim((string)($input['contract_field'] ?? '')),
            'operator'                    => trim((string)($input['operator'] ?? '>')),
            'threshold_value'             => trim((string)($input['threshold_value'] ?? '')),
            'required_approval'           => trim((string)($input['required_approval'] ?? '')),
            'is_active'                   => isset($input['is_active']) ? 1 : 0,
            'sort_order'                  => (int)($input['sort_order'] ?? 0),
            'waived_by_standard_contract' => isset($input['waived_by_standard_contract']) ? 1 : 0,
            'waived_by_min_insurance'      => isset($input['waived_by_min_insurance']) ? 1 : 0,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['rule_name'] === '') $errors[] = 'Rule name is required.';
        if (!array_key_exists($data['contract_field'], self::FIELD_OPTIONS)) $errors[] = 'Invalid contract field.';
        if (!array_key_exists($data['operator'], self::OPERATORS)) $errors[] = 'Invalid operator.';
        if ($data['threshold_value'] === '' || !is_numeric($data['threshold_value'])) $errors[] = 'Threshold must be a number (or a contract type ID).';
        if (!array_key_exists($data['required_approval'], self::APPROVAL_LABELS)) $errors[] = 'Invalid approval type.';
        return $errors;
    }

    // ── Email Risk Manager for approval ──────────────────────────────────────
    public function emailRiskManager(): void
    {
        require_login();
        if (!is_system_admin()) {
            http_response_code(403); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit;
        }

        $contractId = (int)($_POST['contract_id'] ?? 0);
        if ($contractId <= 0) {
            $_SESSION['flash_errors'] = ['Invalid contract.'];
            header('Location: /index.php?page=contracts');
            exit;
        }

        // Fetch contract basic info
        $stmt = $this->db->prepare("SELECT contract_id, name, contract_number FROM contracts WHERE contract_id = ? LIMIT 1");
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contract) {
            $_SESSION['flash_errors'] = ['Contract not found.'];
            header('Location: /index.php?page=contracts');
            exit;
        }

        // Find Risk Manager recipients:
        // 1) People with TOWN_MANAGER role (mapped role for risk_manager)
        // 2) Fall back to system_settings.risk_manager_email if set
        $recipients = [];

        $stmt = $this->db->prepare("
            SELECT p.email, CONCAT(p.first_name, ' ', p.last_name) AS full_name
            FROM people p
            JOIN person_roles pr ON pr.person_id = p.person_id
            JOIN roles r ON r.role_id = pr.role_id
            WHERE r.role_key = 'RISK_MANAGER'
              AND r.is_active = 1
              AND p.email IS NOT NULL AND p.email != ''
        ");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no RISK_MANAGER role holders, try system_settings fallback
        if (empty($recipients)) {
            $fallbackEmail = $this->getSystemSetting('risk_manager_email');
            if ($fallbackEmail !== '') {
                $recipients[] = ['email' => $fallbackEmail, 'full_name' => 'Risk Manager'];
            }
        }

        if (empty($recipients)) {
            $_SESSION['flash_errors'] = ['No Risk Manager email address found. Please add a person with the RISK_MANAGER role or set risk_manager_email in System Settings.'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host        = $_SERVER['HTTP_HOST'];
        $contractUrl = $scheme . '://' . $host . '/index.php?page=contracts_show&contract_id=' . $contractId;
        $contractLabel = h($contract['name'] ?? 'Contract #' . $contract['contract_number']);

        // Send emails
        $sent = 0;
        $errors = [];
        foreach ($recipients as $recipient) {
            try {
                $this->sendRiskManagerEmail($recipient['email'], $recipient['full_name'], $contractLabel, $contractUrl, $contractId);
                $sent++;
            } catch (\Throwable $e) {
                error_log('Risk manager email failed to ' . $recipient['email'] . ': ' . $e->getMessage());
                $errors[] = 'Failed to send to ' . $recipient['email'];
            }
        }

        // Log to contract history
        $person     = current_person();
        $personName = trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        if (empty(trim($personName))) $personName = $person['display_name'] ?? $person['email'] ?? 'Unknown';
        $personId   = !empty($person['person_id']) ? (int)$person['person_id'] : null;
        $sentTo     = implode(', ', array_column($recipients, 'email'));
        $note       = "Risk Manager approval email sent by $personName to: $sentTo";

        $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, 'approval_email', NULL, NULL, ?, NOW(), ?)"
        )->execute([$contractId, $personId, $note]);

        if ($sent > 0) {
            $_SESSION['flash_messages'] = ['Risk Manager approval email sent to ' . $sent . ' recipient(s).'];
        }
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
        }
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    private function sendRiskManagerEmail(string $toEmail, string $toName, string $contractLabel, string $contractUrl, int $contractId): void
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug  = 0;
        $mail->Debugoutput = function ($str, $level) { error_log("SMTPDBG[$level] $str"); };

        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = (($_ENV['SMTP_SECURE'] ?? 'tls') === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];
        $mail->Timeout    = 15;

        $mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME'] ?? '');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Action Required: Risk Manager Approval Needed — ' . strip_tags($contractLabel);

        $safeUrl   = htmlspecialchars($contractUrl, ENT_QUOTES, 'UTF-8');
        $mail->Body = "
            <p>Hello {$toName},</p>
            <p>Your approval is needed as Risk Manager for the following contract:</p>
            <p><strong>{$contractLabel}</strong></p>
            <p><a href=\"{$safeUrl}\">Click here to review and approve the contract</a></p>
            <p>Please log in and stamp your Risk Manager approval at your earliest convenience.</p>
        ";
        $mail->AltBody =
            "Hello {$toName},\n\n" .
            "Your Risk Manager approval is needed for: " . strip_tags($contractLabel) . "\n\n" .
            "Review the contract here:\n{$contractUrl}\n\n" .
            "Please log in and stamp your Risk Manager approval.\n";

        $mail->send();
    }

    private function requireAdmin(): void
    {
        if (function_exists('is_system_admin') && !is_system_admin()) {
            http_response_code(403);
            exit('Access denied. Admin required.');
        }
    }

    private function redirect(): void
    {
        header('Location: /index.php?page=approval_rules');
        exit;
    }

    private function getSystemSetting(string $key): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}
