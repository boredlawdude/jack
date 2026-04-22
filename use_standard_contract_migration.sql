-- ─────────────────────────────────────────────────────────────────────────────
-- Migration: use_standard_contract + contract-type approval rules
--   • contracts.use_standard_contract  TINYINT(1) DEFAULT 0
--   • approval_rules.waived_by_standard_contract  TINYINT(1) DEFAULT 0
-- Run once: mysql -u contract_user -p contract_manager < use_standard_contract_migration.sql
-- ─────────────────────────────────────────────────────────────────────────────

DELIMITER $$

DROP PROCEDURE IF EXISTS _add_standard_contract_fields $$
CREATE PROCEDURE _add_standard_contract_fields()
BEGIN
    -- use_standard_contract on contracts
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'contracts'
          AND COLUMN_NAME  = 'use_standard_contract'
    ) THEN
        ALTER TABLE contracts
            ADD COLUMN use_standard_contract TINYINT(1) NOT NULL DEFAULT 0
            AFTER auto_renew;
    END IF;

    -- waived_by_standard_contract on approval_rules
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'approval_rules'
          AND COLUMN_NAME  = 'waived_by_standard_contract'
    ) THEN
        ALTER TABLE approval_rules
            ADD COLUMN waived_by_standard_contract TINYINT(1) NOT NULL DEFAULT 0;
    END IF;
END $$

CALL _add_standard_contract_fields() $$
DROP PROCEDURE IF EXISTS _add_standard_contract_fields $$

DELIMITER ;
