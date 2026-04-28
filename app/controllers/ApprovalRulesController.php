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

        // ── Last bulk re-apply info (for undo button) ─────────────────────
        // 1. Prefer history records from a logged re-apply
        $historyRow = $this->db->query(
            "SELECT MAX(changed_at) as last_at, COUNT(*) as stamp_count
               FROM contract_status_history
              WHERE notes LIKE '%[bulk re-apply from Approval Rules admin page]%'"
        )->fetch(PDO::FETCH_ASSOC);

        $lastReapplyAt    = null;
        $lastReapplyCount = 0;
        $revertByDate     = null;

        if ($historyRow && (int)$historyRow['stamp_count'] > 0) {
            $lastReapplyAt    = $historyRow['last_at'];
            $lastReapplyCount = (int)$historyRow['stamp_count'];
            $revertByDate     = date('Y-m-d', strtotime($lastReapplyAt));
        } else {
            // 2. Fallback: find the most recent single date shared by many approval stamps
            //    (a bulk operation fingerprint)
            $bulkRow = $this->db->query("
                SELECT d, SUM(n) as total_stamps FROM (
                    SELECT manager_approval_date      AS d, COUNT(*) AS n FROM contracts WHERE manager_approval_date      IS NOT NULL GROUP BY manager_approval_date
                    UNION ALL
                    SELECT purchasing_approval_date   AS d, COUNT(*) AS n FROM contracts WHERE purchasing_approval_date   IS NOT NULL GROUP BY purchasing_approval_date
                    UNION ALL
                    SELECT legal_approval_date        AS d, COUNT(*) AS n FROM contracts WHERE legal_approval_date        IS NOT NULL GROUP BY legal_approval_date
                    UNION ALL
                    SELECT risk_manager_approval_date AS d, COUNT(*) AS n FROM contracts WHERE risk_manager_approval_date IS NOT NULL GROUP BY risk_manager_approval_date
                    UNION ALL
                    SELECT council_approval_date      AS d, COUNT(*) AS n FROM contracts WHERE council_approval_date      IS NOT NULL GROUP BY council_approval_date
                ) sub
                GROUP BY d HAVING total_stamps >= 5
                ORDER BY d DESC LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            if ($bulkRow) {
                $revertByDate     = $bulkRow['d'];
                $lastReapplyAt    = $bulkRow['d'];
                $lastReapplyCount = (int)$bulkRow['total_stamps'];
            }
        }

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
            INSERT INTO approval_rules (rule_name, contract_field, operator, threshold_value, contract_field_2, operator_2, threshold_value_2, required_approval, is_active, sort_order, waived_by_standard_contract, waived_by_min_insurance)
            VALUES (:rule_name, :contract_field, :operator, :threshold_value, :contract_field_2, :operator_2, :threshold_value_2, :required_approval, :is_active, :sort_order, :waived_by_standard_contract, :waived_by_min_insurance)
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
                threshold_value=:threshold_value,
                contract_field_2=:contract_field_2, operator_2=:operator_2, threshold_value_2=:threshold_value_2,
                required_approval=:required_approval,
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

    // ── Add a manual per-contract approval override (AJAX, admin-only) ────
    public function addApprovalOverride(): void
    {
        ob_clean(); // discard any HTML buffered before this AJAX endpoint was reached
        header('Content-Type: application/json; charset=utf-8');

        require_login();
        if (!is_system_admin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $contractId   = (int)($_POST['contract_id'] ?? 0);
        $approvalType = trim($_POST['approval_type'] ?? '');

        if ($contractId <= 0 || !isset(self::APPROVAL_LABELS[$approvalType])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $person   = current_person();
        $personId = !empty($person['person_id']) ? (int)$person['person_id'] : null;

        // INSERT IGNORE so duplicate clicks are idempotent
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO contract_approval_overrides (contract_id, approval_type, added_by_person_id)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$contractId, $approvalType, $personId]);

        echo json_encode(['success' => true]);
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

            // Evaluate optional second condition (AND logic)
            if ($match && !empty($rule['contract_field_2']) && !empty($rule['operator_2']) && isset($rule['threshold_value_2'])) {
                $field2      = $rule['contract_field_2'];
                $contractVal2 = (float)($contract[$field2] ?? 0);
                $threshold2  = (float)$rule['threshold_value_2'];
                $match = match ($rule['operator_2']) {
                    '>'  => $contractVal2 >  $threshold2,
                    '>=' => $contractVal2 >= $threshold2,
                    '<'  => $contractVal2 <  $threshold2,
                    '<=' => $contractVal2 <= $threshold2,
                    '='  => $contractVal2 == $threshold2,
                    '!=' => $contractVal2 != $threshold2,
                    default => false,
                };
            }

            if ($match) {
                $required[] = $rule['required_approval'];
            }
        }

        // Merge in any per-contract manual overrides
        $contractId = (int)($contract['contract_id'] ?? 0);
        if ($contractId > 0) {
            $ovStmt = $db->prepare("SELECT approval_type FROM contract_approval_overrides WHERE contract_id = ?");
            $ovStmt->execute([$contractId]);
            foreach ($ovStmt->fetchAll(PDO::FETCH_COLUMN) as $ov) {
                $required[] = $ov;
            }
        }

        return array_unique($required);
    }

    // ── Private helpers ───────────────────────────────────────────────────────
    private function collect(array $input): array
    {
        $field2 = trim((string)($input['contract_field_2'] ?? ''));
        $op2    = trim((string)($input['operator_2'] ?? ''));
        $val2   = trim((string)($input['threshold_value_2'] ?? ''));
        // Only save second condition if all three parts are provided
        $hasSecond = ($field2 !== '' && $op2 !== '' && $val2 !== '');
        return [
            'rule_name'                   => trim((string)($input['rule_name'] ?? '')),
            'contract_field'              => trim((string)($input['contract_field'] ?? '')),
            'operator'                    => trim((string)($input['operator'] ?? '>')),
            'threshold_value'             => trim((string)($input['threshold_value'] ?? '')),
            'contract_field_2'            => $hasSecond ? $field2 : null,
            'operator_2'                  => $hasSecond ? $op2    : null,
            'threshold_value_2'           => $hasSecond ? $val2   : null,
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
        // Validate second condition only if partially filled
        $has2 = $data['contract_field_2'] !== null;
        if ($has2) {
            if (!array_key_exists($data['contract_field_2'], self::FIELD_OPTIONS)) $errors[] = 'Invalid second condition field.';
            if (!array_key_exists($data['operator_2'], self::OPERATORS)) $errors[] = 'Invalid second condition operator.';
            if (!is_numeric($data['threshold_value_2'])) $errors[] = 'Second condition threshold must be a number.';
        }
        return $errors;
    }

    // ── Email Risk Manager for approval ──────────────────────────────────────
    public function emailRiskManager(): void
    {
        $this->dispatchRiskManagerEmail(false);
    }

    public function emailRiskManagerReduced(): void
    {
        $this->dispatchRiskManagerEmail(true);
    }

    private function dispatchRiskManagerEmail(bool $reducedInsurance): void
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

        $recipients = $this->findRiskManagerRecipients();

        if (empty($recipients)) {
            $_SESSION['flash_errors'] = ['No recipients found. Please assign the RISK_MANAGER role to at least one user (Admin → People → edit user → check "Risk Manager").'];
            header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
            exit;
        }

        $person     = current_person();
        $personName = trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        if (empty(trim($personName))) $personName = $person['display_name'] ?? $person['email'] ?? 'Unknown';
        $personId   = !empty($person['person_id']) ? (int)$person['person_id'] : null;

        $scheme        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host          = $_SERVER['HTTP_HOST'];
        $contractUrl   = $scheme . '://' . $host . '/index.php?page=contracts_show&contract_id=' . $contractId;
        $contractLabel = $contract['name'] ?? ('Contract #' . $contract['contract_number']);
        $contractNumber = $contract['contract_number'] ?? ('ID ' . $contractId);

        $sent   = 0;
        $errors = [];
        foreach ($recipients as $recipient) {
            try {
                $this->sendRiskManagerEmail(
                    $recipient['email'],
                    $recipient['full_name'],
                    $contractLabel,
                    $contractNumber,
                    $contractUrl,
                    $personName,
                    $reducedInsurance
                );
                $sent++;
            } catch (\Throwable $e) {
                error_log('Risk manager email failed to ' . $recipient['email'] . ': ' . $e->getMessage());
                $errors[] = 'Failed to send to ' . $recipient['email'];
            }
        }

        $sentTo = implode(', ', array_column($recipients, 'email'));
        $noteType = $reducedInsurance ? 'reduced insurance request' : 'approval';
        $note = "Risk Manager $noteType email sent by $personName to: $sentTo";

        $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, 'approval_email', NULL, NULL, ?, NOW(), ?)"
        )->execute([$contractId, $personId, $note]);

        if ($sent > 0) {
            $label = $reducedInsurance ? 'Reduced insurance request' : 'Risk Manager approval email';
            $_SESSION['flash_messages'] = [$label . ' sent to ' . $sent . ' recipient(s).'];
        }
        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
        }
        header('Location: /index.php?page=contracts_show&contract_id=' . $contractId);
        exit;
    }

    private function findRiskManagerRecipients(): array
    {
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

        return $recipients;
    }

    private function sendRiskManagerEmail(
        string $toEmail,
        string $toName,
        string $contractLabel,
        string $contractNumber,
        string $contractUrl,
        string $senderName,
        bool   $reducedInsurance = false
    ): void {
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

        $safeUrl    = htmlspecialchars($contractUrl, ENT_QUOTES, 'UTF-8');
        $safeNumber = htmlspecialchars($contractNumber, ENT_QUOTES, 'UTF-8');
        $safeSender = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safeLabel  = htmlspecialchars($contractLabel, ENT_QUOTES, 'UTF-8');

        if ($reducedInsurance) {
            $mail->Subject = 'Request: Please Consider Reduced Insurance Requirements — Contract ' . $contractNumber;
            $mail->Body = "
                <p>Hello {$toName},</p>
                <p>Please consider reduced insurance requirements for the following contract:</p>
                <p><strong>{$safeNumber} &mdash; {$safeLabel}</strong></p>
                <p><a href=\"{$safeUrl}\">Click here to view the contract</a></p>
                <p>Contact <strong>{$safeSender}</strong> for additional details.</p>
            ";
            $mail->AltBody =
                "Hello {$toName},\n\n" .
                "Please consider reduced insurance requirements for the following contract:\n" .
                "{$contractNumber} -- " . strip_tags($contractLabel) . "\n\n" .
                "View the contract here:\n{$contractUrl}\n\n" .
                "Contact {$senderName} for additional details.\n";
        } else {
            $mail->Subject = 'Action Required: Risk Manager Approval Needed — Contract ' . $contractNumber;
            $mail->Body = "
                <p>Hello {$toName},</p>
                <p>Your Risk Manager approval is needed for the following contract:</p>
                <p><strong>{$safeNumber} &mdash; {$safeLabel}</strong></p>
                <p><a href=\"{$safeUrl}\">Click here to review and approve the contract</a></p>
                <p>Contact <strong>{$safeSender}</strong> for additional details.</p>
            ";
            $mail->AltBody =
                "Hello {$toName},\n\n" .
                "Your Risk Manager approval is needed for:\n" .
                "{$contractNumber} -- " . strip_tags($contractLabel) . "\n\n" .
                "Review the contract here:\n{$contractUrl}\n\n" .
                "Contact {$senderName} for additional details.\n";
        }

        $mail->send();
    }

    // ── Undo the most recent bulk re-apply ────────────────────────────────────
    public function revertReapply(): void
    {
        require_login();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit;
        }

        // --- Strategy 1: history-based undo ---
        $latestAt = $this->db->query(
            "SELECT MAX(changed_at) FROM contract_status_history
              WHERE notes LIKE '%[bulk re-apply from Approval Rules admin page]%'"
        )->fetchColumn();

        if ($latestAt) {
            $batchStmt = $this->db->prepare(
                "SELECT history_id, contract_id, notes FROM contract_status_history
                  WHERE notes LIKE '%[bulk re-apply from Approval Rules admin page]%'
                    AND changed_at >= DATE_SUB(?, INTERVAL 60 SECOND)"
            );
            $batchStmt->execute([$latestAt]);
            $batchRecords = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

            $colMap = [
                'manager'      => 'manager_approval_date',
                'purchasing'   => 'purchasing_approval_date',
                'legal'        => 'legal_approval_date',
                'risk_manager' => 'risk_manager_approval_date',
                'council'      => 'council_approval_date',
            ];
            $labelToKey = array_flip(self::APPROVAL_LABELS);
            $reverted   = 0;
            $historyIds = [];

            foreach ($batchRecords as $record) {
                $approvalKey = null;
                foreach ($labelToKey as $label => $key) {
                    if (str_starts_with($record['notes'], $label . ' approval stamped')) {
                        $approvalKey = $key;
                        break;
                    }
                }
                if ($approvalKey === null || !isset($colMap[$approvalKey])) continue;
                $col = $colMap[$approvalKey];
                $this->db->prepare("UPDATE contracts SET `$col` = NULL WHERE contract_id = ?")->execute([$record['contract_id']]);
                $historyIds[] = (int)$record['history_id'];
                $reverted++;
            }

            if (!empty($historyIds)) {
                $placeholders = implode(',', array_fill(0, count($historyIds), '?'));
                $this->db->prepare("DELETE FROM contract_status_history WHERE history_id IN ($placeholders)")->execute($historyIds);
            }

            if ($reverted > 0) {
                $_SESSION['flash_messages'] = ["Undo complete. Cleared $reverted approval stamp(s) from the last bulk re-apply. Pending approvals will reappear on the dashboard."];
                $this->redirect();
                return;
            }
        }

        // --- Strategy 2: date-based undo (no history records) ---
        $revertDate = trim($_POST['revert_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $revertDate)) {
            $_SESSION['flash_errors'] = ['No bulk re-apply records found to undo.'];
            $this->redirect();
            return;
        }

        $cols = [
            'manager_approval_date',
            'purchasing_approval_date',
            'legal_approval_date',
            'risk_manager_approval_date',
            'council_approval_date',
        ];
        $cleared = 0;
        foreach ($cols as $col) {
            $stmt = $this->db->prepare("UPDATE contracts SET `$col` = NULL WHERE `$col` = ?");
            $stmt->execute([$revertDate]);
            $cleared += $stmt->rowCount();
        }

        if ($cleared === 0) {
            $_SESSION['flash_messages'] = ['Nothing to undo — no approval stamps matched that date.'];
        } else {
            $_SESSION['flash_messages'] = ["Undo complete. Cleared $cleared approval stamp(s) dated $revertDate. Pending approvals will reappear on the dashboard."];
        }

        $this->redirect();
    }

    // ── Bulk-stamp missing required approvals on all existing contracts ───────
    public function reapplyRules(): void
    {
        require_login();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit;
        }

        $contracts = $this->db->query("
            SELECT contract_id, total_contract_value, renewal_term_months, contract_type_id,
                   use_standard_contract, minimum_insurance_coi,
                   manager_approval_date, purchasing_approval_date, legal_approval_date,
                   risk_manager_approval_date, council_approval_date
            FROM contracts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $colMap = [
            'manager'      => 'manager_approval_date',
            'purchasing'   => 'purchasing_approval_date',
            'legal'        => 'legal_approval_date',
            'risk_manager' => 'risk_manager_approval_date',
            'council'      => 'council_approval_date',
        ];

        $person     = current_person();
        $personName = trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        if (empty(trim($personName))) $personName = $person['display_name'] ?? $person['email'] ?? 'Unknown';
        $personId   = !empty($person['person_id']) ? (int)$person['person_id'] : null;
        $dateVal    = date('Y-m-d');

        $logStmt = $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, 'approval', NULL, NULL, ?, NOW(), ?)"
        );

        $stamped  = 0;
        $affected = 0;

        foreach ($contracts as $contract) {
            $required        = self::requiredApprovalsFor($this->db, $contract);
            $contractStamped = 0;

            foreach ($required as $approvalKey) {
                $col = $colMap[$approvalKey] ?? null;
                if ($col === null || !empty($contract[$col])) continue; // skip if unknown or already stamped

                $label = self::APPROVAL_LABELS[$approvalKey] ?? $approvalKey;
                $this->db->prepare("UPDATE contracts SET `$col` = ? WHERE contract_id = ?")->execute([$dateVal, $contract['contract_id']]);
                $logStmt->execute([
                    $contract['contract_id'],
                    $personId,
                    "$label approval stamped by $personName on $dateVal [bulk re-apply from Approval Rules admin page]",
                ]);
                $stamped++;
                $contractStamped++;
            }

            if ($contractStamped > 0) $affected++;
        }

        if ($stamped === 0) {
            $_SESSION['flash_messages'] = ['Re-apply complete. All contracts already have the required approvals stamped — no changes made.'];
        } else {
            $_SESSION['flash_messages'] = ["Re-apply complete. Stamped $stamped missing approval(s) across $affected contract(s)."];
        }

        $this->redirect();
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
