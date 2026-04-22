-- ─────────────────────────────────────────────────────────────────────────────
-- Migration: Add RISK_MANAGER role
-- Allows assigning people the Risk Manager role so they receive approval emails.
-- Run once: mysql -u contract_user -p contract_manager < risk_manager_role_migration.sql
-- ─────────────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO roles (role_key, role_name, is_active)
VALUES ('RISK_MANAGER', 'Risk Manager', 1);
