-- Migration: add DocuSign signer fields to companies table
-- Run via information_schema check (MySQL 5.7 compatible — no ADD COLUMN IF NOT EXISTS)

SET @dbname = DATABASE();
SET @tbl    = 'companies';

-- signer1
SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer1_name';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer1_name  VARCHAR(100) DEFAULT NULL AFTER sosid', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer1_title';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer1_title VARCHAR(100) DEFAULT NULL AFTER signer1_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer1_email';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer1_email VARCHAR(200) DEFAULT NULL AFTER signer1_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- signer2
SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer2_name';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer2_name  VARCHAR(100) DEFAULT NULL AFTER signer1_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer2_title';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer2_title VARCHAR(100) DEFAULT NULL AFTER signer2_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer2_email';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer2_email VARCHAR(200) DEFAULT NULL AFTER signer2_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- signer3
SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer3_name';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer3_name  VARCHAR(100) DEFAULT NULL AFTER signer2_email', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer3_title';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer3_title VARCHAR(100) DEFAULT NULL AFTER signer3_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='signer3_email';
SET @sql = IF(@has = 0, 'ALTER TABLE companies ADD COLUMN signer3_email VARCHAR(200) DEFAULT NULL AFTER signer3_title', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
