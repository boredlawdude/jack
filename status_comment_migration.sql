ALTER TABLE contracts
    ADD COLUMN status_comment VARCHAR(20) DEFAULT NULL AFTER contract_status_id;
