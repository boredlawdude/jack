-- Development Agreement Tracts: one-to-many parcels per development agreement
CREATE TABLE IF NOT EXISTS development_agreement_tracts (
    tract_id             INT          NOT NULL AUTO_INCREMENT,
    dev_agreement_id     INT          NOT NULL,
    property_address     VARCHAR(255) DEFAULT NULL,
    property_pin         VARCHAR(50)  DEFAULT NULL,
    property_realestateid VARCHAR(50) DEFAULT NULL,
    property_acerage     DECIMAL(10,4) DEFAULT NULL,
    property_owner_id    INT          DEFAULT NULL,  -- FK to people
    sort_order           INT          NOT NULL DEFAULT 0,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tract_id),
    KEY fk_tract_devagr  (dev_agreement_id),
    KEY fk_tract_owner   (property_owner_id),
    CONSTRAINT fk_tract_devagr  FOREIGN KEY (dev_agreement_id) REFERENCES development_agreements (dev_agreement_id) ON DELETE CASCADE,
    CONSTRAINT fk_tract_owner   FOREIGN KEY (property_owner_id) REFERENCES people (person_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
