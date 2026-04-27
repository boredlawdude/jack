-- Migration: add account_number to contracts
-- Compatible with MySQL 5.7+
-- Run via: php -r "..." or check column existence before running
ALTER TABLE contracts
    ADD COLUMN account_number VARCHAR(20) DEFAULT NULL AFTER po_number;
-- Note: if column already exists this will error - that's safe to ignore.
