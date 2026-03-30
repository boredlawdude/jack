-- Migration: Create contract_statuses table
CREATE TABLE contract_statuses (
    contract_status_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_status_name VARCHAR(64) NOT NULL UNIQUE,
    contract_status_desc TEXT
);

-- Seed initial statuses
INSERT INTO contract_statuses (contract_status_name, contract_status_desc) VALUES
('Draft', 'Initial draft of the contract'),
('Negotiate', 'Contract is under negotiation'),
('Legal Review', 'Contract is being reviewed by legal'),
('Dept Head Review', 'Department head is reviewing the contract'),
('Manager Review', 'Manager is reviewing the contract'),
('Town Council', 'Awaiting town council approval'),
('Out For Signature', 'Contract is out for signature'),
('Executed', 'Contract has been fully executed');
