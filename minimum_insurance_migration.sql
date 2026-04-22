-- ─────────────────────────────────────────────────────────────────────────────
-- Migration: minimum_insurance_coi + risk manager email
--   • contracts.minimum_insurance_coi         TINYINT(1) DEFAULT 0
--   • approval_rules.waived_by_min_insurance   TINYINT(1) DEFAULT 0
--   • system_settings: risk_manager_email
-- Run once: mysql -u contract_user -p contract_manager < minimum_insurance_migration.sql
-- ─────────────────────────────────────────────────────────────────────────────

DELIMITER $$

DROP PROCEDURE IF EXISTS _add_insurance_fields $$
CREATE PROCEDURE _add_insurance_fields()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'contracts'
          AND COLUMN_NAME  = 'minimum_insurance_coi'
    ) THEN
        ALTER TABLE contracts
            ADD COLUMN minimum_insurance_coi TINYINT(1) NOT NULL DEFAULT 0
            AFTER use_standard_contract;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'approval_rules'
          AND COLUMN_NAME  = 'waived_by_min_insurance'
    ) THEN
        ALTER TABLE approval_rules
            ADD COLUMN waived_by_min_insurance TINYINT(1) NOT NULL DEFAULT 0;
    END IF;
END $$

CALL _add_insurance_fields() $$
DROP PROCEDURE IF EXISTS _add_insurance_fields $$

DELIMITER ;

-- Add risk_manager_email system setting if not present
INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('risk_manager_email', '', 'Email address of the Risk Manager for approval notifications');
