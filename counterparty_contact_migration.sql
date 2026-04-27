-- Add counterparty_contact_name and counterparty_contact_email to contracts
-- These store the vendor contact as plain text (sourced from the companies table)
-- so that counterparty contacts do not need to be duplicated in the people table.

SET @dbname = DATABASE();

-- counterparty_contact_name
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME   = 'contracts'
  AND COLUMN_NAME  = 'counterparty_contact_name';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE contracts ADD COLUMN counterparty_contact_name VARCHAR(255) DEFAULT NULL AFTER counterparty_primary_contact_id',
    'SELECT "counterparty_contact_name already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- counterparty_contact_email
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME   = 'contracts'
  AND COLUMN_NAME  = 'counterparty_contact_email';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE contracts ADD COLUMN counterparty_contact_email VARCHAR(255) DEFAULT NULL AFTER counterparty_contact_name',
    'SELECT "counterparty_contact_email already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
