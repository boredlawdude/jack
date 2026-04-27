-- Migration: add signer + consent fields to contract_intake_submissions (local ALTER)
-- The contract_intake_migration.sql already contains these columns for fresh installs.
-- Run this only on environments where the table was already created without them.

SET @dbname = DATABASE();
SET @tbl    = 'contract_intake_submissions';

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer1_name';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer1_name  VARCHAR(100) DEFAULT NULL AFTER notes', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer1_title';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer1_title VARCHAR(100) DEFAULT NULL AFTER counterparty_signer1_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer1_email';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer1_email VARCHAR(200) DEFAULT NULL AFTER counterparty_signer1_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer2_name';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer2_name  VARCHAR(100) DEFAULT NULL AFTER counterparty_signer1_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer2_title';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer2_title VARCHAR(100) DEFAULT NULL AFTER counterparty_signer2_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer2_email';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer2_email VARCHAR(200) DEFAULT NULL AFTER counterparty_signer2_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer3_name';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer3_name  VARCHAR(100) DEFAULT NULL AFTER counterparty_signer2_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer3_title';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer3_title VARCHAR(100) DEFAULT NULL AFTER counterparty_signer3_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='counterparty_signer3_email';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN counterparty_signer3_email VARCHAR(200) DEFAULT NULL AFTER counterparty_signer3_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='esign_consent';
SET @sql = IF(@has = 0, 'ALTER TABLE contract_intake_submissions ADD COLUMN esign_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER counterparty_signer3_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
