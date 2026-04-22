-- Migration: Add is_imported flag to contracts table
-- Purpose: Mark records that were bulk-imported so they can be deleted cleanly if needed
-- Run once via the run_contract_import_migration.php helper or manually.

ALTER TABLE contracts
    ADD COLUMN is_imported TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Set to 1 for records imported via the bulk CSV import tool';

CREATE INDEX idx_contracts_is_imported ON contracts (is_imported);
