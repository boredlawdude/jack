-- Add date_approved_by_* columns to contracts table
-- These are required by the contract edit/update form.
-- Run with: mysql -u {user} -p {dbname} < date_approved_columns_migration.sql
-- Note: if any statement errors with "Duplicate column name", the column already exists -- safe to ignore.

ALTER TABLE contracts ADD COLUMN date_approved_by_procurement DATE NULL DEFAULT NULL;
ALTER TABLE contracts ADD COLUMN date_approved_by_manager     DATE NULL DEFAULT NULL;
ALTER TABLE contracts ADD COLUMN date_approved_by_council     DATE NULL DEFAULT NULL;
