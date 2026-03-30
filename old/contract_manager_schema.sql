-- MySQL dump 10.13  Distrib 9.4.0, for macos15.4 (arm64)
--
-- Host: localhost    Database: contract_manager
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `company_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('internal','customer','vendor','partner','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vendor',
  `company_type_id` int DEFAULT NULL,
  `tax_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_of_incorporation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coi_exp_date` date DEFAULT NULL,
  `coi_carrier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coi_verified_by_person_id` int DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`company_id`),
  KEY `fk_companies_company_type` (`company_type_id`),
  KEY `idx_companies_coi_verified_by` (`coi_verified_by_person_id`),
  CONSTRAINT `fk_companies_coi_verified_by` FOREIGN KEY (`coi_verified_by_person_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_companies_company_type` FOREIGN KEY (`company_type_id`) REFERENCES `company_types` (`company_type_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_comments`
--

DROP TABLE IF EXISTS `company_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_comments` (
  `company_comment_id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `person_id` int NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_comment_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_person` (`person_id`),
  CONSTRAINT `fk_company_comments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_company_comments_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`person_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_types`
--

DROP TABLE IF EXISTS `company_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_types` (
  `company_type_id` int NOT NULL AUTO_INCREMENT,
  `company_type` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`company_type_id`),
  UNIQUE KEY `company_type` (`company_type`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_body_versions`
--

DROP TABLE IF EXISTS `contract_body_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_body_versions` (
  `contract_body_version_id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `body_html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_person_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`contract_body_version_id`),
  KEY `contract_id` (`contract_id`),
  KEY `fk_cbv_person` (`created_by_person_id`),
  CONSTRAINT `fk_cbv_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cbv_person` FOREIGN KEY (`created_by_person_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_document_revisions`
--

DROP TABLE IF EXISTS `contract_document_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_document_revisions` (
  `revision_id` int NOT NULL AUTO_INCREMENT,
  `contract_document_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `version` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_person_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`revision_id`),
  UNIQUE KEY `uniq_doc_version` (`contract_document_id`,`version`),
  KEY `contract_document_id` (`contract_document_id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `fk_rev_doc` FOREIGN KEY (`contract_document_id`) REFERENCES `contract_documents` (`contract_document_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_documents`
--

DROP TABLE IF EXISTS `contract_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_documents` (
  `contract_document_id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `doc_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated_contract',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  `created_by_person_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`contract_document_id`),
  KEY `fk_contract_documents_user` (`created_by_person_id`),
  KEY `idx_contract_documents_contract_id` (`contract_id`),
  CONSTRAINT `fk_contract_documents_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_documents_user` FOREIGN KEY (`created_by_person_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_exhibits`
--

DROP TABLE IF EXISTS `contract_exhibits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_exhibits` (
  `exhibit_id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `exhibit_label` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `sha256` char(64) NOT NULL,
  `pdf_blob` longblob NOT NULL,
  `uploaded_by_person_id` int NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `exhibit_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`exhibit_id`),
  KEY `idx_contract` (`contract_id`),
  CONSTRAINT `fk_exhibits_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_html_revisions`
--

DROP TABLE IF EXISTS `contract_html_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_html_revisions` (
  `revision_id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `document_id` int DEFAULT NULL,
  `created_by_person_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_accepted` tinyint(1) NOT NULL DEFAULT '0',
  `accepted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`revision_id`),
  KEY `idx_chr_contract` (`contract_id`,`created_at`),
  KEY `idx_chr_doc` (`document_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_status_history`
--

DROP TABLE IF EXISTS `contract_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_status_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`history_id`),
  KEY `contract_id` (`contract_id`),
  CONSTRAINT `contract_status_history_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_types`
--

DROP TABLE IF EXISTS `contract_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_types` (
  `contract_type_id` int NOT NULL AUTO_INCREMENT,
  `contract_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `formal_bidding_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `template_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_file_docx` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_file_html` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`contract_type_id`),
  UNIQUE KEY `contract_type` (`contract_type`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `contract_id` int NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','in_review','signed','expired','terminated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `governing_law` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'North Carolina',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0',
  `renewal_term_months` int DEFAULT NULL,
  `total_contract_value` decimal(18,2) DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `owner_company_id` int DEFAULT '3',
  `counterparty_company_id` int NOT NULL,
  `owner_primary_contact_id` int DEFAULT NULL,
  `counterparty_primary_contact_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `documents_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_id` int DEFAULT NULL,
  `contract_type_id` int DEFAULT NULL,
  `contract_body_html` longtext COLLATE utf8mb4_unicode_ci,
  `payment_terms_id` int DEFAULT '1',
  PRIMARY KEY (`contract_id`),
  UNIQUE KEY `contract_number` (`contract_number`),
  KEY `idx_contracts_status` (`status`),
  KEY `idx_contracts_end_date` (`end_date`),
  KEY `idx_contracts_owner` (`owner_company_id`),
  KEY `idx_contracts_counterparty` (`counterparty_company_id`),
  KEY `idx_contracts_department_id` (`department_id`),
  KEY `idx_contracts_contract_type_id` (`contract_type_id`),
  KEY `fk_contract_owner_contact` (`owner_primary_contact_id`),
  KEY `fk_contract_counterparty_contact` (`counterparty_primary_contact_id`),
  KEY `fk_contracts_payment_terms` (`payment_terms_id`),
  CONSTRAINT `fk_contract_counterparty` FOREIGN KEY (`counterparty_company_id`) REFERENCES `companies` (`company_id`),
  CONSTRAINT `fk_contract_counterparty_contact` FOREIGN KEY (`counterparty_primary_contact_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_owner` FOREIGN KEY (`owner_company_id`) REFERENCES `companies` (`company_id`),
  CONSTRAINT `fk_contract_owner_contact` FOREIGN KEY (`owner_primary_contact_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contract_payment_terms` FOREIGN KEY (`payment_terms_id`) REFERENCES `payment_terms` (`payment_terms_id`),
  CONSTRAINT `fk_contracts_contract_type` FOREIGN KEY (`contract_type_id`) REFERENCES `contract_types` (`contract_type_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_payment_terms` FOREIGN KEY (`payment_terms_id`) REFERENCES `payment_terms` (`payment_terms_id`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `department_id` int NOT NULL AUTO_INCREMENT,
  `department_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dept_initials` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_head_id` int DEFAULT NULL,
  `assistant_town_manager_id` int DEFAULT NULL,
  `contract_admin_id` int DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `fk_departments_contract_admin` (`contract_admin_id`),
  CONSTRAINT `fk_departments_contract_admin` FOREIGN KEY (`contract_admin_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `legal_matters`
--

DROP TABLE IF EXISTS `legal_matters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_matters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `file_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matter_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matter_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matter_desc` text COLLATE utf8mb4_unicode_ci,
  `assigned_to` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `requestedby_id` int DEFAULT NULL,
  `assigned_to_person_id` int DEFAULT NULL,
  `status` enum('New','In Progress','Under Review','Pending Council/Approval','On Hold','Completed','Closed','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'New',
  `date_started` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `date_closed` date DEFAULT NULL,
  `matter_long_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue1_notes` text COLLATE utf8mb4_unicode_ci,
  `issue2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue2_notes` text COLLATE utf8mb4_unicode_ci,
  `council_update_date` date DEFAULT NULL,
  `council_update_text` text COLLATE utf8mb4_unicode_ci,
  `tasks_needed` text COLLATE utf8mb4_unicode_ci,
  `date_completed` date DEFAULT NULL,
  `contact_id` int DEFAULT NULL,
  `active_lawsuit` tinyint(1) DEFAULT '0',
  `nclm_claim_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engineering_flag` tinyint(1) DEFAULT '0',
  `public_safety_flag` tinyint(1) DEFAULT '0',
  `file_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `print_label` tinyint(1) DEFAULT '0',
  `archive_flag` tinyint(1) DEFAULT '0',
  `requested_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `planning_flag` tinyint(1) DEFAULT '0',
  `administration_flag` tinyint(1) DEFAULT '0',
  `public_works_flag` tinyint(1) DEFAULT '0',
  `hr_flag` tinyint(1) DEFAULT '0',
  `public_utilities_flag` tinyint(1) DEFAULT '0',
  `parks_rec_flag` tinyint(1) DEFAULT '0',
  `finance_flag` tinyint(1) DEFAULT '0',
  `inspections_flag` tinyint(1) DEFAULT '0',
  `economic_flag` tinyint(1) DEFAULT '0',
  `it_flag` tinyint(1) DEFAULT '0',
  `clerk_pio_flag` tinyint(1) DEFAULT '0',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'UNC path to supporting document(s), e.g. \\serversharefolderfile.pdf',
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_number` (`file_number`),
  KEY `idx_file_number` (`file_number`),
  KEY `idx_status` (`status`),
  KEY `idx_date_started` (`date_started`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `assigned_to_person_id` (`assigned_to_person_id`),
  KEY `department_id` (`department_id`),
  KEY `requestedby_id` (`requestedby_id`),
  CONSTRAINT `legal_matters_ibfk_1` FOREIGN KEY (`assigned_to_person_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL,
  CONSTRAINT `legal_matters_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `legal_matters_ibfk_3` FOREIGN KEY (`requestedby_id`) REFERENCES `people` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18893 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `password_reset_id` int NOT NULL AUTO_INCREMENT,
  `person_id` int NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`password_reset_id`),
  UNIQUE KEY `uq_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_person` (`person_id`),
  KEY `idx_password_resets_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`person_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_terms`
--

DROP TABLE IF EXISTS `payment_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_terms` (
  `payment_terms_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`payment_terms_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `people`
--

DROP TABLE IF EXISTS `people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `people` (
  `person_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',`last_name`)) STORED,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `officephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cellphone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_town_employee` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_id` int DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_login` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`person_id`),
  UNIQUE KEY `uq_people_email` (`email`),
  KEY `fk_people_company` (`company_id`),
  KEY `fk_people_department` (`department_id`),
  CONSTRAINT `fk_people_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_people_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `person_department_roles`
--

DROP TABLE IF EXISTS `person_department_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_department_roles` (
  `person_id` int NOT NULL,
  `department_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`person_id`,`department_id`,`role_id`),
  KEY `fk_pdr_dept` (`department_id`),
  KEY `fk_pdr_role` (`role_id`),
  CONSTRAINT `fk_pdr_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdr_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`person_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `person_roles`
--

DROP TABLE IF EXISTS `person_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_roles` (
  `person_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`person_id`,`role_id`),
  KEY `fk_person_roles_role` (`role_id`),
  CONSTRAINT `fk_person_roles_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`person_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_person_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-23 11:46:42
