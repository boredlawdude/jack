-- Replace property_owner_id (FK to people) with a free-text owner_name column
ALTER TABLE development_agreement_tracts
    DROP FOREIGN KEY fk_tract_owner,
    DROP KEY fk_tract_owner,
    DROP COLUMN property_owner_id,
    ADD COLUMN owner_name VARCHAR(200) DEFAULT NULL AFTER property_acerage;
