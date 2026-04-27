-- Migration: add consortium fields to bidding_compliance table

SET @dbname = DATABASE();
SET @tbl    = 'bidding_compliance';

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='is_consortium';
SET @sql = IF(@has = 0, 'ALTER TABLE bidding_compliance ADD COLUMN is_consortium TINYINT(1) NOT NULL DEFAULT 0 AFTER comment', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='consortium_name';
SET @sql = IF(@has = 0, 'ALTER TABLE bidding_compliance ADD COLUMN consortium_name VARCHAR(200) DEFAULT NULL AFTER is_consortium', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT COUNT(*) INTO @has FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tbl AND COLUMN_NAME='consortium_contract_number';
SET @sql = IF(@has = 0, 'ALTER TABLE bidding_compliance ADD COLUMN consortium_contract_number VARCHAR(100) DEFAULT NULL AFTER consortium_name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
