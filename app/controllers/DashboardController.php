<?php
declare(strict_types=1);

require_once APP_ROOT . '/app/models/Contract.php';
require_once APP_ROOT . '/app/models/ContractStatus.php';

class DashboardController
{
    private PDO $db;
    private Contract $contractModel;
    private ContractStatus $statusModel;

    public function __construct()
    {
        $this->db = db();
        $this->contractModel = new Contract($this->db);
        $this->statusModel = new ContractStatus($this->db);
    }

    public function index(): void
    {
        // Current user info
        $person = current_person();

        // Look up all roles (with descriptions) for this user
        $userRoles = [];
        if (!empty($person['roles'])) {
            $placeholders = implode(',', array_fill(0, count($person['roles']), '?'));
            $stmt = $this->db->prepare(
                "SELECT role_key, role_name, description FROM roles
                  WHERE role_key IN ($placeholders) AND is_active = 1
                  ORDER BY role_name ASC"
            );
            $stmt->execute(array_values($person['roles']));
            $userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── Pending-execution count ───────────────────────────────────────
        // "Pending execution" = status name is NOT one of the terminal/late-stage
        // statuses AND end_date IS NULL
        $excludedStatuses = [
            'town council', 'town council review',
            'out for signature',
            'executed', 'contract executed',
            'work started',
        ];
        $exPlaceholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
        $pendingStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM contracts c
              LEFT JOIN contract_statuses cs ON cs.contract_status_id = c.contract_status_id
              WHERE (c.end_date IS NULL OR c.end_date = '')
                AND LOWER(COALESCE(cs.contract_status_name,'')) NOT IN ($exPlaceholders)"
        );
        $pendingStmt->execute($excludedStatuses);
        $pendingCount = (int)$pendingStmt->fetchColumn();

        // ── Stale-draft count + IDs ───────────────────────────────────────
        // Status name LIKE 'draft%' or 'negotiat%', created_at > 5 days ago
        $staleStmt = $this->db->prepare(
            "SELECT c.contract_id FROM contracts c
              LEFT JOIN contract_statuses cs ON cs.contract_status_id = c.contract_status_id
              WHERE (
                  LOWER(cs.contract_status_name) LIKE 'draft%'
               OR LOWER(cs.contract_status_name) LIKE 'negotiat%'
              )
              AND c.created_at <= DATE_SUB(NOW(), INTERVAL 5 DAY)"
        );
        $staleStmt->execute();
        $staleIds = array_flip($staleStmt->fetchAll(PDO::FETCH_COLUMN));
        $staleCount = count($staleIds);

        // All statuses for radio filter
        $statuses = $this->statusModel->all();

        // All contracts (unfiltered); JS handles client-side filtering
        $contracts = $this->contractModel->search([]);

        require APP_ROOT . '/app/views/dashboard/index.php';
    }
}
