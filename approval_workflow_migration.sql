-- ============================================================
-- Approval Workflow Migration
-- Run once on each environment (local + VPS)
-- ============================================================

-- 1. Add approval date columns to contracts (idempotent via stored procedure)
DROP PROCEDURE IF EXISTS _add_approval_columns;
DELIMITER $$
CREATE PROCEDURE _add_approval_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='manager_approval_date') THEN
        ALTER TABLE `contracts` ADD COLUMN `manager_approval_date`      DATE DEFAULT NULL AFTER `total_contract_value`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='purchasing_approval_date') THEN
        ALTER TABLE `contracts` ADD COLUMN `purchasing_approval_date`   DATE DEFAULT NULL AFTER `manager_approval_date`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='legal_approval_date') THEN
        ALTER TABLE `contracts` ADD COLUMN `legal_approval_date`        DATE DEFAULT NULL AFTER `purchasing_approval_date`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='risk_manager_approval_date') THEN
        ALTER TABLE `contracts` ADD COLUMN `risk_manager_approval_date` DATE DEFAULT NULL AFTER `legal_approval_date`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='council_approval_date') THEN
        ALTER TABLE `contracts` ADD COLUMN `council_approval_date`      DATE DEFAULT NULL AFTER `risk_manager_approval_date`;
    END IF;
END$$
DELIMITER ;
CALL _add_approval_columns();
DROP PROCEDURE IF EXISTS _add_approval_columns;

-- 2. Create approval_rules table
CREATE TABLE IF NOT EXISTS `approval_rules` (
    `rule_id`           INT NOT NULL AUTO_INCREMENT,
    `rule_name`         VARCHAR(255) NOT NULL COMMENT 'Human-readable label, e.g. "Manager approval over $30k"',
    `contract_field`    VARCHAR(100) NOT NULL COMMENT 'Contract field to evaluate, e.g. total_contract_value',
    `operator`          ENUM('>','>=','<','<=','=','!=') NOT NULL DEFAULT '>',
    `threshold_value`   VARCHAR(255) NOT NULL COMMENT 'Value to compare against (stored as string)',
    `required_approval` ENUM('manager','purchasing','legal','risk_manager','council') NOT NULL,
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`        INT NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rule_id`),
    KEY `idx_approval_rules_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed some sensible default rules (safe to re-run; skips if names already exist)
INSERT IGNORE INTO `approval_rules` (`rule_name`, `contract_field`, `operator`, `threshold_value`, `required_approval`, `sort_order`)
VALUES
    ('Manager approval required over $30,000',        'total_contract_value', '>',  '30000',  'manager',      10),
    ('Purchasing approval required over $30,000',     'total_contract_value', '>',  '30000',  'purchasing',   20),
    ('Legal approval required over $100,000',         'total_contract_value', '>',  '100000', 'legal',        30),
    ('Risk Manager approval required over $250,000',  'total_contract_value', '>',  '250000', 'risk_manager', 40),
    ('Council approval required over $500,000',       'total_contract_value', '>',  '500000', 'council',      50);
