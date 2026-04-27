<?php
declare(strict_types=1);

class ContractIntakeSubmission
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO contract_intake_submissions
                (submitter_name, submitter_email, submitter_phone, submitter_department,
                 contract_name, contract_description, contract_type_id,
                 counterparty_company, counterparty_contact, counterparty_email, counterparty_phone,
                 estimated_value, start_date, end_date,
                 po_number, account_number, notes,
                 counterparty_signer1_name, counterparty_signer1_title, counterparty_signer1_email,
                 counterparty_signer2_name, counterparty_signer2_title, counterparty_signer2_email,
                 counterparty_signer3_name, counterparty_signer3_title, counterparty_signer3_email,
                 esign_consent)
            VALUES
                (:submitter_name, :submitter_email, :submitter_phone, :submitter_department,
                 :contract_name, :contract_description, :contract_type_id,
                 :counterparty_company, :counterparty_contact, :counterparty_email, :counterparty_phone,
                 :estimated_value, :start_date, :end_date,
                 :po_number, :account_number, :notes,
                 :counterparty_signer1_name, :counterparty_signer1_title, :counterparty_signer1_email,
                 :counterparty_signer2_name, :counterparty_signer2_title, :counterparty_signer2_email,
                 :counterparty_signer3_name, :counterparty_signer3_title, :counterparty_signer3_email,
                 :esign_consent)
        ");
        $stmt->execute([
            ':submitter_name'       => $data['submitter_name'],
            ':submitter_email'      => $data['submitter_email'],
            ':submitter_phone'      => $this->n($data['submitter_phone']      ?? null),
            ':submitter_department' => $this->n($data['submitter_department'] ?? null),
            ':contract_name'        => $data['contract_name'],
            ':contract_description' => $this->n($data['contract_description'] ?? null),
            ':contract_type_id'     => $this->n($data['contract_type_id']     ?? null),
            ':counterparty_company' => $this->n($data['counterparty_company'] ?? null),
            ':counterparty_contact' => $this->n($data['counterparty_contact'] ?? null),
            ':counterparty_email'   => $this->n($data['counterparty_email']   ?? null),
            ':counterparty_phone'   => $this->n($data['counterparty_phone']   ?? null),
            ':estimated_value'      => $this->n($data['estimated_value']      ?? null),
            ':start_date'           => $this->n($data['start_date']           ?? null),
            ':end_date'             => $this->n($data['end_date']             ?? null),
            ':po_number'            => $this->n($data['po_number']            ?? null),
            ':account_number'       => $this->n($data['account_number']       ?? null),
            ':notes'                => $this->n($data['notes']                ?? null),
            ':counterparty_signer1_name'  => $this->n($data['counterparty_signer1_name']  ?? null),
            ':counterparty_signer1_title' => $this->n($data['counterparty_signer1_title'] ?? null),
            ':counterparty_signer1_email' => $this->n($data['counterparty_signer1_email'] ?? null),
            ':counterparty_signer2_name'  => $this->n($data['counterparty_signer2_name']  ?? null),
            ':counterparty_signer2_title' => $this->n($data['counterparty_signer2_title'] ?? null),
            ':counterparty_signer2_email' => $this->n($data['counterparty_signer2_email'] ?? null),
            ':counterparty_signer3_name'  => $this->n($data['counterparty_signer3_name']  ?? null),
            ':counterparty_signer3_title' => $this->n($data['counterparty_signer3_title'] ?? null),
            ':counterparty_signer3_email' => $this->n($data['counterparty_signer3_email'] ?? null),
            ':esign_consent'        => (int)($data['esign_consent'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findAll(string $status = 'pending'): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, ct.contract_type
            FROM   contract_intake_submissions s
            LEFT JOIN contract_types ct ON ct.contract_type_id = s.contract_type_id
            WHERE  s.status = ?
            ORDER  BY s.created_at DESC
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT s.*, ct.contract_type
            FROM   contract_intake_submissions s
            LEFT JOIN contract_types ct ON ct.contract_type_id = s.contract_type_id
            WHERE  s.submission_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markImported(int $id, int $contractId, int $personId): void
    {
        $this->db->prepare("
            UPDATE contract_intake_submissions
            SET    status = 'imported', imported_contract_id = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE  submission_id = ?
        ")->execute([$contractId, $personId, $id]);
    }

    public function markRejected(int $id, int $personId): void
    {
        $this->db->prepare("
            UPDATE contract_intake_submissions
            SET    status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
            WHERE  submission_id = ?
        ")->execute([$personId, $id]);
    }

    public function countPending(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM contract_intake_submissions WHERE status = 'pending'")->fetchColumn();
    }

    private function n(mixed $v): mixed
    {
        if ($v === null || $v === '') return null;
        return $v;
    }
}
