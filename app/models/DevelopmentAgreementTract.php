<?php
declare(strict_types=1);

class DevelopmentAgreementTract
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function allForAgreement(int $devAgreementId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   CONCAT_WS(' ', p.first_name, p.last_name) AS owner_name
            FROM development_agreement_tracts t
            LEFT JOIN people p ON p.person_id = t.property_owner_id
            WHERE t.dev_agreement_id = ?
            ORDER BY t.sort_order ASC, t.tract_id ASC
        ");
        $stmt->execute([$devAgreementId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tractId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   CONCAT_WS(' ', p.first_name, p.last_name) AS owner_name
            FROM development_agreement_tracts t
            LEFT JOIN people p ON p.person_id = t.property_owner_id
            WHERE t.tract_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $devAgreementId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO development_agreement_tracts
                (dev_agreement_id, property_address, property_pin, property_realestateid,
                 property_acerage, property_owner_id, sort_order)
            VALUES
                (:dev_agreement_id, :property_address, :property_pin, :property_realestateid,
                 :property_acerage, :property_owner_id, :sort_order)
        ");
        $stmt->execute($this->bind($devAgreementId, $data));
        return (int)$this->db->lastInsertId();
    }

    public function update(int $tractId, array $data): void
    {
        // Fetch dev_agreement_id for binding
        $row = $this->find($tractId);
        if (!$row) return;
        $stmt = $this->db->prepare("
            UPDATE development_agreement_tracts SET
                property_address      = :property_address,
                property_pin          = :property_pin,
                property_realestateid = :property_realestateid,
                property_acerage      = :property_acerage,
                property_owner_id     = :property_owner_id,
                sort_order            = :sort_order
            WHERE tract_id = :tract_id
        ");
        $params = $this->bind((int)$row['dev_agreement_id'], $data);
        unset($params[':dev_agreement_id']);
        $params[':tract_id'] = $tractId;
        $stmt->execute($params);
    }

    public function delete(int $tractId): void
    {
        $stmt = $this->db->prepare("DELETE FROM development_agreement_tracts WHERE tract_id = ?");
        $stmt->execute([$tractId]);
    }

    public function deleteAllForAgreement(int $devAgreementId): void
    {
        $stmt = $this->db->prepare("DELETE FROM development_agreement_tracts WHERE dev_agreement_id = ?");
        $stmt->execute([$devAgreementId]);
    }

    private function bind(int $devAgreementId, array $d): array
    {
        $nullStr  = fn($v) => (trim((string)$v) !== '') ? trim((string)$v) : null;
        $nullInt  = fn($v) => ($v !== '' && $v !== null) ? (int)$v : null;
        $nullDec  = fn($v) => ($v !== '' && $v !== null) ? $v : null;

        return [
            ':dev_agreement_id'     => $devAgreementId,
            ':property_address'     => $nullStr($d['property_address']      ?? null),
            ':property_pin'         => $nullStr($d['property_pin']           ?? null),
            ':property_realestateid' => $nullStr($d['property_realestateid'] ?? null),
            ':property_acerage'     => $nullDec($d['property_acerage']       ?? null),
            ':property_owner_id'    => $nullInt($d['property_owner_id']      ?? null),
            ':sort_order'           => (int)($d['sort_order'] ?? 0),
        ];
    }
}
