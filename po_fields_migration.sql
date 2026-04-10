-- Migration: add po_number and po_amount to contracts
-- Run once on local and VPS databases

ALTER TABLE contracts
    ADD COLUMN po_number  VARCHAR(20)    DEFAULT NULL AFTER total_contract_value,
    ADD COLUMN po_amount  DECIMAL(15,2)  DEFAULT NULL AFTER po_number;
