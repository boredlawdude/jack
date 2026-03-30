<?php
// app/models/PaymentTerm.php
class PaymentTerm {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function all(): array {
        $stmt = $this->db->query("SELECT * FROM payment_terms ORDER BY payment_terms_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create(string $name, string $desc): bool {
        $stmt = $this->db->prepare("INSERT INTO payment_terms (name, description) VALUES (?, ?)");
        return $stmt->execute([$name, $desc]);
    }
    public function update(int $id, string $name, string $desc): bool {
        $stmt = $this->db->prepare("UPDATE payment_terms SET name = ?, description = ? WHERE payment_terms_id = ?");
        return $stmt->execute([$name, $desc, $id]);
    }
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM payment_terms WHERE payment_terms_id = ?");
        return $stmt->execute([$id]);
    }
}
