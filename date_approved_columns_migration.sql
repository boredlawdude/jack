-- Add date_approved_by_* columns to contracts table
-- These are required by the contract edit/update form.
-- Run with: mysql -u {user} -p {dbname} < date_approved_columns_migration.sql

DROP PROCEDURE IF EXISTS _add_col;
DELIMITER $$
CREATE PROCEDURE _add_col(tbl VARCHAR(64), col VARCHAR(64), col_def TEXT, after_col VARCHAR(64))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def, ' AFTER `', after_col, '`');
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL _add_col('contracts', 'date_approved_by_procurement', 'DATE NULL DEFAULT NULL', 'procurement_notes');
CALL _add_col('contracts', 'date_approved_by_manager',     'DATE NULL DEFAULT NULL', 'date_approved_by_procurement');
CALL _add_col('contracts', 'date_approved_by_council',     'DATE NULL DEFAULT NULL', 'date_approved_by_manager');

DROP PROCEDURE IF EXISTS _add_col;
