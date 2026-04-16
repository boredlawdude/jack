<?php
declare(strict_types=1);

class DevelopmentAgreement
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT
                da.dev_agreement_id,
                da.project_name,
                da.property_address,
                da.property_pin,
                da.anticipated_start_date,
                da.anticipated_end_date,
                da.agreement_termination_date,
                da.created_at,
                CONCAT_WS(' ', a.first_name, a.last_name)  AS applicant_name,
                CONCAT_WS(' ', po.first_name, po.last_name) AS property_owner_name,
                CONCAT_WS(' ', at.first_name, at.last_name) AS attorney_name
            FROM development_agreements da
            LEFT JOIN people a  ON a.person_id  = da.applicant_id
            LEFT JOIN people po ON po.person_id = da.property_owner_id
            LEFT JOIN people at ON at.person_id  = da.attorney_id
            ORDER BY da.dev_agreement_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                da.*,
                CONCAT_WS(' ', a.first_name, a.last_name)  AS applicant_name,
                CONCAT_WS(' ', po.first_name, po.last_name) AS property_owner_name,
                CONCAT_WS(' ', at.first_name, at.last_name) AS attorney_name
            FROM development_agreements da
            LEFT JOIN people a  ON a.person_id  = da.applicant_id
            LEFT JOIN people po ON po.person_id = da.property_owner_id
            LEFT JOIN people at ON at.person_id  = da.attorney_id
            WHERE da.dev_agreement_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByContractId(int $contractId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                da.*,
                CONCAT_WS(' ', a.first_name, a.last_name)  AS applicant_name,
                CONCAT_WS(' ', po.first_name, po.last_name) AS property_owner_name,
                CONCAT_WS(' ', at.first_name, at.last_name) AS attorney_name
            FROM development_agreements da
            LEFT JOIN people a  ON a.person_id  = da.applicant_id
            LEFT JOIN people po ON po.person_id = da.property_owner_id
            LEFT JOIN people at ON at.person_id  = da.attorney_id
            WHERE da.contract_id = ?
            LIMIT 1
        ");
        $stmt->execute([$contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO development_agreements
                (contract_id, applicant_id, property_owner_id, attorney_id,
                 property_address, property_pin, property_realestateid,
                 project_name, project_description, property_acerage,
                 current_zoning, proposed_zoning, comp_plan_designation,
                 anticipated_start_date, anticipated_end_date,
                 proposed_improvements, agreement_termination_date,
                 planning_board_date, town_council_hearing_date)
            VALUES
                (:contract_id, :applicant_id, :property_owner_id, :attorney_id,
                 :property_address, :property_pin, :property_realestateid,
                 :project_name, :project_description, :property_acerage,
                 :current_zoning, :proposed_zoning, :comp_plan_designation,
                 :anticipated_start_date, :anticipated_end_date,
                 :proposed_improvements, :agreement_termination_date,
                 :planning_board_date, :town_council_hearing_date)
        ");
        $stmt->execute($this->bindParams($data));
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE development_agreements SET
                applicant_id              = :applicant_id,
                property_owner_id         = :property_owner_id,
                attorney_id               = :attorney_id,
                property_address          = :property_address,
                property_pin              = :property_pin,
                property_realestateid     = :property_realestateid,
                project_name              = :project_name,
                project_description       = :project_description,
                property_acerage          = :property_acerage,
                current_zoning            = :current_zoning,
                proposed_zoning           = :proposed_zoning,
                comp_plan_designation     = :comp_plan_designation,
                anticipated_start_date    = :anticipated_start_date,
                anticipated_end_date      = :anticipated_end_date,
                proposed_improvements     = :proposed_improvements,
                agreement_termination_date = :agreement_termination_date,
                planning_board_date        = :planning_board_date,
                town_council_hearing_date  = :town_council_hearing_date
            WHERE dev_agreement_id = :id
        ");
        // Note: contract_id is not updated after initial creation
        $params = $this->bindParams($data);
        unset($params[':contract_id']); // not in UPDATE SQL
        $params[':id'] = $id;
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM development_agreements WHERE dev_agreement_id = ?");
        $stmt->execute([$id]);
    }

    private function bindParams(array $data): array
    {
        $nullOrInt = fn($v) => ($v !== '' && $v !== null) ? (int)$v : null;
        $nullOrStr = fn($v) => (trim((string)$v) !== '') ? trim((string)$v) : null;
        $nullOrDate = fn($v) => ($v !== '' && $v !== null) ? $v : null;

        return [
            ':contract_id'               => $nullOrInt($data['contract_id'] ?? null),
            ':applicant_id'              => $nullOrInt($data['applicant_id'] ?? null),
            ':property_owner_id'         => $nullOrInt($data['property_owner_id'] ?? null),
            ':attorney_id'               => $nullOrInt($data['attorney_id'] ?? null),
            ':property_address'          => $nullOrStr($data['property_address'] ?? null),
            ':property_pin'              => $nullOrStr($data['property_pin'] ?? null),
            ':property_realestateid'     => $nullOrStr($data['property_realestateid'] ?? null),
            ':project_name'              => trim((string)($data['project_name'] ?? '')),
            ':project_description'       => $nullOrStr($data['project_description'] ?? null),
            ':property_acerage'          => ($data['property_acerage'] !== '' && $data['property_acerage'] !== null) ? $data['property_acerage'] : null,
            ':current_zoning'            => $nullOrStr($data['current_zoning'] ?? null),
            ':proposed_zoning'           => $nullOrStr($data['proposed_zoning'] ?? null),
            ':comp_plan_designation'     => $nullOrStr($data['comp_plan_designation'] ?? null),
            ':anticipated_start_date'    => $nullOrDate($data['anticipated_start_date'] ?? null),
            ':anticipated_end_date'      => $nullOrDate($data['anticipated_end_date'] ?? null),
            ':proposed_improvements'     => $nullOrStr($data['proposed_improvements'] ?? null),
            ':agreement_termination_date' => $nullOrDate($data['agreement_termination_date'] ?? null),
            ':planning_board_date'        => $nullOrDate($data['planning_board_date'] ?? null),
            ':town_council_hearing_date'  => $nullOrDate($data['town_council_hearing_date'] ?? null),
        ];
    }
}
