-- Development Agreements module migration
-- Run once against the contracts_app database

CREATE TABLE IF NOT EXISTS `development_agreements` (
  `dev_agreement_id`          INT NOT NULL AUTO_INCREMENT,
  `applicant_id`              INT DEFAULT NULL,
  `property_owner_id`         INT DEFAULT NULL,
  `attorney_id`               INT DEFAULT NULL,
  `property_address`          VARCHAR(255) DEFAULT NULL,
  `property_pin`              VARCHAR(100) DEFAULT NULL,
  `property_realestateid`     VARCHAR(100) DEFAULT NULL,
  `project_name`              VARCHAR(255) NOT NULL DEFAULT '',
  `project_description`       LONGTEXT DEFAULT NULL,
  `property_acerage`          DECIMAL(10,4) DEFAULT NULL,
  `current_zoning`            VARCHAR(100) DEFAULT NULL,
  `proposed_zoning`           VARCHAR(100) DEFAULT NULL,
  `comp_plan_designation`     VARCHAR(255) DEFAULT NULL,
  `anticipated_start_date`    DATE DEFAULT NULL,
  `anticipated_end_date`      DATE DEFAULT NULL,
  `proposed_improvements`     LONGTEXT DEFAULT NULL,
  `agreement_termination_date` DATE DEFAULT NULL,
  `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`dev_agreement_id`),
  KEY `fk_devagr_applicant`      (`applicant_id`),
  KEY `fk_devagr_property_owner` (`property_owner_id`),
  KEY `fk_devagr_attorney`       (`attorney_id`),
  CONSTRAINT `fk_devagr_applicant`
    FOREIGN KEY (`applicant_id`)      REFERENCES `people` (`person_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_devagr_property_owner`
    FOREIGN KEY (`property_owner_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_devagr_attorney`
    FOREIGN KEY (`attorney_id`)       REFERENCES `people` (`person_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
