-- Add contract_status_id to contracts table
ALTER TABLE contracts ADD COLUMN contract_status_id INTEGER;

-- Optional: Migrate existing status text to contract_status_id
-- (Assumes status text matches contract_status_name exactly)
UPDATE contracts c
JOIN contract_statuses s ON c.status = s.contract_status_name
SET c.contract_status_id = s.contract_status_id;

-- Optional: Set contract_status_id to NOT NULL and drop old status column after verifying data
-- ALTER TABLE contracts MODIFY contract_status_id INTEGER NOT NULL;
-- ALTER TABLE contracts DROP COLUMN status;
