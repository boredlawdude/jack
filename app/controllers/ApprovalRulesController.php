<?php
declare(strict_types=1);

class ApprovalRulesController
{
    private PDO $db;

    // Human-readable labels for the approvable contract fields
    public const FIELD_OPTIONS = [
        'total_contract_value' => 'Contract Value ($)',
        'renewal_term_months'  => 'Renewal Term (months)',
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

    public function __construct()
    {
        $this->db = db();
    }

    public function index(): void
    {
        require_login();
        $this->requireAdmin();

        $rules = $this->db->query("SELECT * FROM approval_rules ORDER BY sort_order, rule_id")->fetchAll(PDO::FETCH_ASSOC);
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
            INSERT INTO approval_rules (rule_name, contract_field, operator, threshold_value, required_approval, is_active, sort_order)
            VALUES (:rule_name, :contract_field, :operator, :threshold_value, :required_approval, :is_active, :sort_order)
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
                is_active=:is_active, sort_order=:sort_order
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

        $dateVal = date('Y-m-d'); // always stamp today

        $col = $allowedCols[$approvalType];
        $this->db->prepare("UPDATE contracts SET `$col` = ? WHERE contract_id = ?")->execute([$dateVal, $contractId]);

        // Log to contract history
        $person    = current_person();
        $personName = trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        if (empty($personName)) $personName = $person['display_name'] ?? $person['email'] ?? 'Unknown';
        $label     = self::APPROVAL_LABELS[$approvalType] ?? $approvalType;
        $personId  = !empty($person['person_id']) ? (int)$person['person_id'] : null;
        $this->db->prepare(
            "INSERT INTO contract_status_history (contract_id, event_type, old_status, new_status, changed_by, changed_at, notes)
             VALUES (?, 'approval', NULL, NULL, ?, NOW(), ?)"
        )->execute([$contractId, $personId, "$label approved by $personName on $dateVal"]);

        $_SESSION['flash_messages'] = ["$label approval stamped for $dateVal."];
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

        foreach ($rules as $rule) {
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
            'rule_name'         => trim((string)($input['rule_name'] ?? '')),
            'contract_field'    => trim((string)($input['contract_field'] ?? '')),
            'operator'          => trim((string)($input['operator'] ?? '>')),
            'threshold_value'   => trim((string)($input['threshold_value'] ?? '')),
            'required_approval' => trim((string)($input['required_approval'] ?? '')),
            'is_active'         => isset($input['is_active']) ? 1 : 0,
            'sort_order'        => (int)($input['sort_order'] ?? 0),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['rule_name'] === '') $errors[] = 'Rule name is required.';
        if (!array_key_exists($data['contract_field'], self::FIELD_OPTIONS)) $errors[] = 'Invalid contract field.';
        if (!array_key_exists($data['operator'], self::OPERATORS)) $errors[] = 'Invalid operator.';
        if ($data['threshold_value'] === '' || !is_numeric($data['threshold_value'])) $errors[] = 'Threshold must be a number.';
        if (!array_key_exists($data['required_approval'], self::APPROVAL_LABELS)) $errors[] = 'Invalid approval type.';
        return $errors;
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
}
