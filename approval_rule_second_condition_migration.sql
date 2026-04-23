-- Add optional second condition columns to approval_rules
-- Allows rules like: "value > 90000 AND contract_type_id = 5 → council approval"
-- All three columns are nullable — when NULL, only the first condition is evaluated.

DROP PROCEDURE IF EXISTS add_approval_rule_second_condition;

DELIMITER $$
CREATE PROCEDURE add_approval_rule_second_condition()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'approval_rules'
          AND COLUMN_NAME  = 'contract_field_2'
    ) THEN
        ALTER TABLE approval_rules
            ADD COLUMN contract_field_2   VARCHAR(100)                                      NULL DEFAULT NULL AFTER threshold_value,
            ADD COLUMN operator_2         ENUM('>','>=','<','<=','=','!=')                  NULL DEFAULT NULL AFTER contract_field_2,
            ADD COLUMN threshold_value_2  VARCHAR(255)                                      NULL DEFAULT NULL AFTER operator_2;
    END IF;
END$$
DELIMITER ;

CALL add_approval_rule_second_condition();
DROP PROCEDURE IF EXISTS add_approval_rule_second_condition;
