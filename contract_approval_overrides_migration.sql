-- Per-contract approval overrides: manually mark an approval as required
-- even if no approval_rule matches that contract.
CREATE TABLE IF NOT EXISTS contract_approval_overrides (
    override_id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    contract_id          INT UNSIGNED     NOT NULL,
    approval_type        VARCHAR(50)      NOT NULL,   -- manager|purchasing|legal|risk_manager|council
    added_by_person_id   INT UNSIGNED     DEFAULT NULL,
    added_at             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (override_id),
    UNIQUE KEY uq_contract_approval (contract_id, approval_type),
    KEY idx_contract_id (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
