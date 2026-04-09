<?php
declare(strict_types=1);

class Contract
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get all raw contract records
     */
    public function all(): array
    {
        $sql = "
            SELECT *
            FROM contracts
            ORDER BY contract_id DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get contract list with lookup/display fields
     */
    public function listContracts(): array
    {
        $sql = "
            SELECT
                c.contract_id,
                c.contract_number,
                c.name,
                c.status,
                c.start_date,
                c.end_date,
                c.total_contract_value,
                ct.contract_type AS contract_type_name,
                pt.name AS payment_terms_name,
                co.name AS counterparty_company_name,
                d.department_name
            FROM contracts c
            LEFT JOIN contract_types ct
                ON c.contract_type_id = ct.contract_type_id
            LEFT JOIN payment_terms pt
                ON c.payment_terms_id = pt.payment_terms_id
            LEFT JOIN companies co
                ON c.counterparty_company_id = co.company_id
            LEFT JOIN departments d
                ON c.department_id = d.department_id
            ORDER BY c.contract_id DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Search/filter contract list
     */
  public function search(array $filters = []): array
{
    $sql = "
        SELECT
            c.contract_id,
            c.contract_number,
            c.name,
            c.contract_status_id,
            cs.contract_status_name AS status_name,
            c.start_date,
            c.end_date,
            c.total_contract_value,
            c.owner_primary_contact_id,
            d.department_name,
            COALESCE(
                NULLIF(op.full_name, ''),
                TRIM(CONCAT(COALESCE(op.first_name, ''), ' ', COALESCE(op.last_name, '')))
            ) AS owner_primary_contact_name
        FROM contracts c
        LEFT JOIN departments d
            ON c.department_id = d.department_id
        LEFT JOIN people op
            ON c.owner_primary_contact_id = op.person_id
        LEFT JOIN contract_statuses cs
            ON c.contract_status_id = cs.contract_status_id
        WHERE 1=1
    ";

    $params = [];


    if (!empty($filters['company_id'])) {
        $sql .= " AND (c.owner_company_id = :company_id OR c.counterparty_company_id = :company_id)";
        $params['company_id'] = (int)$filters['company_id'];
    }

    if (!empty($filters['q'])) {
        $sql .= " AND (c.contract_number LIKE :q OR c.name LIKE :q OR c.description LIKE :q)";
        $params['q'] = '%' . trim((string)$filters['q']) . '%';
    }

    if (!empty($filters['contract_status_id'])) {
        $sql .= " AND c.contract_status_id = :contract_status_id";
        $params['contract_status_id'] = (int)$filters['contract_status_id'];
    }

    if (!empty($filters['department_id'])) {
        $sql .= " AND c.department_id = :department_id";
        $params['department_id'] = (int)$filters['department_id'];
    }

    if (!empty($filters['owner_primary_contact_id'])) {
        $sql .= " AND c.owner_primary_contact_id = :owner_primary_contact_id";
        $params['owner_primary_contact_id'] = (int)$filters['owner_primary_contact_id'];
    }

    if (!empty($filters['end_date_from'])) {
        $sql .= " AND c.end_date >= :end_date_from";
        $params['end_date_from'] = $filters['end_date_from'];
    }

    if (!empty($filters['end_date_to'])) {
        $sql .= " AND c.end_date <= :end_date_to";
        $params['end_date_to'] = $filters['end_date_to'];
    }

    $sql .= " ORDER BY c.contract_id DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

    /**
     * Find one contract by ID
     */
    public function find(int $contractId): ?array
    {
        $sql = "
            SELECT c.*, ct.contract_type AS contract_type_name, pt.name AS payment_terms_name,
                   pt.description AS payment_terms_description,
                   d.department_name, d.department_code,
                   co.name AS counterparty_company_name,
                   op.email AS owner_primary_contact_email,
                   COALESCE(op.full_name, op.display_name) AS owner_primary_contact_name,
                   cp.email AS counterparty_primary_contact_email,
                   COALESCE(cp.full_name, cp.display_name) AS counterparty_primary_contact_name,
                   cs.contract_status_name AS status_name
            FROM contracts c
            LEFT JOIN contract_types ct ON c.contract_type_id = ct.contract_type_id
            LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.payment_terms_id
            LEFT JOIN companies co ON c.counterparty_company_id = co.company_id
            LEFT JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN people op ON c.owner_primary_contact_id = op.person_id
            LEFT JOIN people cp ON c.counterparty_primary_contact_id = cp.person_id
            LEFT JOIN contract_statuses cs ON c.contract_status_id = cs.contract_status_id
            WHERE c.contract_id = :contract_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'contract_id' => $contractId
        ]);

        $contract = $stmt->fetch();

        return $contract ?: null;
    }

    /**
     * Create contract
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO contracts (
                contract_number,
                name,
                description,
                contract_body_html,
                contract_type_id,
                counterparty_company_id,
                counterparty_primary_contact_id,
                owner_company_id,
                owner_primary_contact_id,
                department_id,
                governing_law,
                currency,
                total_contract_value,
                po_number,
                po_amount,
                payment_terms_id,
                contract_status_id,
                start_date,
                end_date,
                renewal_term_months,
                auto_renew,
                documents_path,
                procurement_method,
                bid_rfp_number,
                bid_documents_path,
                procurement_notes,
                date_approved_by_procurement,
                date_approved_by_manager,
                date_approved_by_council
            ) VALUES (
                :contract_number,
                :name,
                :description,
                :contract_body_html,
                :contract_type_id,
                :counterparty_company_id,
                :counterparty_primary_contact_id,
                :owner_company_id,
                :owner_primary_contact_id,
                :department_id,
                :governing_law,
                :currency,
                :total_contract_value,
                :po_number,
                :po_amount,
                :payment_terms_id,
                :contract_status_id,
                :start_date,
                :end_date,
                :renewal_term_months,
                :auto_renew,
                :documents_path,
                :procurement_method,
                :bid_rfp_number,
                :bid_documents_path,
                :procurement_notes,
                :date_approved_by_procurement,
                :date_approved_by_manager,
                :date_approved_by_council
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->normalize($data));

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update contract
     */
    public function update(int $contractId, array $data): bool
    {
        $sql = "
            UPDATE contracts
            SET
                contract_number = :contract_number,
                name = :name,
                description = :description,
                contract_body_html = :contract_body_html,
                contract_type_id = :contract_type_id,
                counterparty_company_id = :counterparty_company_id,
                counterparty_primary_contact_id = :counterparty_primary_contact_id,
                owner_company_id = :owner_company_id,
                owner_primary_contact_id = :owner_primary_contact_id,
                department_id = :department_id,
                governing_law = :governing_law,
                currency = :currency,
                total_contract_value = :total_contract_value,
                po_number = :po_number,
                po_amount = :po_amount,
                payment_terms_id = :payment_terms_id,
                contract_status_id = :contract_status_id,
                start_date = :start_date,
                end_date = :end_date,
                renewal_term_months = :renewal_term_months,
                auto_renew = :auto_renew,
                documents_path = :documents_path,
                procurement_method = :procurement_method,
                bid_rfp_number = :bid_rfp_number,
                bid_documents_path = :bid_documents_path,
                procurement_notes = :procurement_notes,
                date_approved_by_procurement = :date_approved_by_procurement,
                date_approved_by_manager = :date_approved_by_manager,
                date_approved_by_council = :date_approved_by_council
            WHERE contract_id = :contract_id
        ";

        $params = $this->normalize($data);
        $params['contract_id'] = $contractId;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete contract
     */
    public function delete(int $contractId): bool
    {
        $sql = "
            DELETE FROM contracts
            WHERE contract_id = :contract_id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'contract_id' => $contractId
        ]);
    }

    /**
     * Normalize form input for create/update
     */
    private function normalize(array $data): array
    {
        return [
            'contract_number' => $this->nullIfEmpty($data['contract_number'] ?? null),
            'name' => trim((string)($data['name'] ?? '')),
            'description' => $this->nullIfEmpty($data['description'] ?? null),
            'contract_body_html' => $this->nullIfEmpty($data['contract_body_html'] ?? null),
            'contract_type_id' => $this->nullIfEmpty($data['contract_type_id'] ?? null),
            'counterparty_company_id' => $this->requiredInt($data['counterparty_company_id'] ?? null),
            'counterparty_primary_contact_id' => $this->nullIfEmpty($data['counterparty_primary_contact_id'] ?? null),
            'owner_company_id' => $this->nullIfEmpty($data['owner_company_id'] ?? 3),
            'owner_primary_contact_id' => $this->nullIfEmpty($data['owner_primary_contact_id'] ?? null),
            'department_id' => $this->nullIfEmpty($data['department_id'] ?? null),
            'governing_law' => $this->nullIfEmpty($data['governing_law'] ?? 'North Carolina'),
            'currency' => $this->nullIfEmpty($data['currency'] ?? 'USD'),
            'total_contract_value' => $this->nullIfEmpty($data['total_contract_value'] ?? null),
            'po_number' => $this->nullIfEmpty($data['po_number'] ?? null),
            'po_amount' => $this->nullIfEmpty($data['po_amount'] ?? null),
            'payment_terms_id' => $this->nullIfEmpty($data['payment_terms_id'] ?? 1),
            'contract_status_id' => $this->nullIfEmpty($data['contract_status_id'] ?? null),
            'start_date' => $this->nullIfEmpty($data['start_date'] ?? null),
            'end_date' => $this->nullIfEmpty($data['end_date'] ?? null),
            'renewal_term_months' => $this->nullIfEmpty($data['renewal_term_months'] ?? null),
            'auto_renew' => !empty($data['auto_renew']) ? 1 : 0,
            'documents_path' => $this->nullIfEmpty($data['documents_path'] ?? null),
            'procurement_method' => $this->nullIfEmpty($data['procurement_method'] ?? null),
            'bid_rfp_number' => $this->nullIfEmpty($data['bid_rfp_number'] ?? null),
            'bid_documents_path' => $this->nullIfEmpty($data['bid_documents_path'] ?? null),
            'procurement_notes' => $this->nullIfEmpty($data['procurement_notes'] ?? null),
            'date_approved_by_procurement' => $this->nullIfEmpty($data['date_approved_by_procurement'] ?? null),
            'date_approved_by_manager' => $this->nullIfEmpty($data['date_approved_by_manager'] ?? null),
            'date_approved_by_council' => $this->nullIfEmpty($data['date_approved_by_council'] ?? null),
        ];
    }

    private function nullIfEmpty(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    private function requiredInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('counterparty_company_id is required.');
        }

        return (int)$value;
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = trim((string)$value);
        // Accept any non-empty status (from contract_statuses table)
        return $status !== '' ? $status : 'draft';
    }
}