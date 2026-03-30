<?php
// app/models/ContractStatus.php
class ContractStatus {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function all(): array {
        $stmt = $this->db->query("SELECT * FROM contract_statuses ORDER BY sort_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create(string $name, string $desc): bool {
        $stmt = $this->db->prepare("INSERT INTO contract_statuses (contract_status_name, contract_status_desc) VALUES (?, ?)");
        return $stmt->execute([$name, $desc]);
    }
    public function update(int $id, string $name, string $desc): bool {
        $stmt = $this->db->prepare("UPDATE contract_statuses SET contract_status_name = ?, contract_status_desc = ? WHERE contract_status_id = ?");
        return $stmt->execute([$name, $desc, $id]);
    }
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM contract_statuses WHERE contract_status_id = ?");
        return $stmt->execute([$id]);
    }
    public function getForSelect(): array {
        $stmt = $this->db->query("SELECT contract_status_id, contract_status_name FROM contract_statuses ORDER BY sort_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
