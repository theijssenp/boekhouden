-- Boekhouden Database Backup
-- Generated: 2026-01-17 14:13:12
-- Database: boekhouden
-- Host: localhost:3306
-- @author P. Theijssen
create database boekhouden ;
use boekhouden;
-- Table structure
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET TIME_ZONE = '+00:00';

CREATE TABLE `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail for administrative actions';

CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `is_system` tinyint(1) DEFAULT '0' COMMENT '1 = system category, 0 = user category',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_categories_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Transaction categories with user ownership';

CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('inkomst','uitgave') NOT NULL,
  `category_id` int DEFAULT NULL,
  `vat_percentage` decimal(5,2) DEFAULT '0.00',
  `vat_included` tinyint(1) DEFAULT '0',
  `vat_deductible` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `invoice_number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_transactions_user_date` (`user_id`,`date`),
  KEY `idx_transactions_user_type` (`user_id`,`type`),
  CONSTRAINT `fk_transactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Financial transactions with user ownership';

<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>/Users/pieter/projects/boekhouden/backup_database.php</b> on line <b>59</b><br />
;

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='User session management for security';

<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>/Users/pieter/projects/boekhouden/backup_database.php</b> on line <b>59</b><br />
;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `user_type` enum('administrator','administratie_houder') NOT NULL DEFAULT 'administratie_houder',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL COMMENT 'User who created this account (for admin tracking)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `fk_created_by` (`created_by`),
  KEY `idx_users_username_active` (`username`,`is_active`),
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System users with authentication information';

<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>/Users/pieter/projects/boekhouden/backup_database.php</b> on line <b>59</b><br />
;

CREATE TABLE `vat_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rate` decimal(5,2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_effective_dates` (`effective_from`,`effective_to`,`is_active`),
  KEY `idx_rate_active` (`rate`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data dump
--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('1', '1', 'Inkomsten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('3', '1', 'Overig', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('7', '1', 'Transportkosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('8', '1', 'Administratiekosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('9', '1', 'Hotelkosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('10', '1', 'Verzekeringskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('11', '1', 'Andere kosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('12', '1', 'Communicatiekosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('13', '1', 'Cloud diensten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('14', '1', 'Kantoorkosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('15', '1', 'Inkoopkosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('16', '1', 'Personeelskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('17', '1', 'Vaste lasten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('18', '1', 'Variabele lasten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('19', '1', 'Financiële kosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('20', '1', 'Afschrijvingskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('21', '1', 'Bijzondere lasten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('22', '1', 'Reiskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('23', '1', 'Vertegenwoordigingskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('24', '1', 'Advertentiekosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('25', '1', 'Commissies en provisies', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('26', '1', 'Rentekosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('27', '1', 'Bankkosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('28', '1', 'Onderhoud- en reparatiekosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('29', '1', 'Schade- en verlieskosten', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('30', '1', 'Boetes en sancties', '1');
INSERT INTO `categories` (`id`, `user_id`, `name`, `is_system`) VALUES ('31', '1', 'Donaties en sponsoring', '1');

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('61', '2', '2025-01-24', 'Schoonmaakmiddelen', '14.55', 'uitgave', '11', '0.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('62', '2', '2025-08-19', 'Treinreis Groningen', '361.12', 'uitgave', '12', '0.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('63', '2', '2025-06-22', 'Koffie apparatuur', '76.05', 'uitgave', '13', '0.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('64', '2', '2025-10-27', 'Schoonmaakmiddelen', '261.74', 'uitgave', '9', '0.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('65', '2', '2025-12-17', 'Postzegels', '162.17', 'uitgave', '3', '21.00', '1', '1', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('66', '2', '2025-05-06', 'Envelopes', '108.99', 'uitgave', '9', '21.00', '1', '0', '2026-01-16 19:18:47', 'FACT-2025-7560');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('67', '2', '2025-08-17', 'Stroomverbruik september', '329.05', 'uitgave', '14', '21.00', '0', '1', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('68', '2', '2025-01-15', 'Treinreis Utrecht', '186.24', 'uitgave', '9', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('69', '2', '2025-11-20', 'Webhosting voorbeeld.nl', '67.43', 'uitgave', '3', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('70', '2', '2025-12-06', 'Koffie apparatuur', '86.52', 'uitgave', '7', '21.00', '0', '1', '2026-01-16 19:18:47', 'FACT-2025-5488');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('71', '2', '2025-08-28', 'Water cooler', '301.15', 'uitgave', '11', '9.00', '1', '1', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('72', '2', '2025-07-14', 'Koffie apparatuur', '398.22', 'uitgave', '8', '21.00', '1', '1', '2026-01-16 19:18:47', 'FACT-2025-5157');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('73', '2', '2025-01-27', 'Stroomverbruik augustus', '412.42', 'uitgave', '7', '0.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('74', '2', '2025-08-02', 'Taxi Utrecht-Utrecht', '62.47', 'uitgave', '11', '21.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('75', '2', '2025-10-08', 'OV abonnement', '432.62', 'uitgave', '13', '21.00', '1', '1', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('76', '2', '2025-08-26', 'OV abonnement', '89.68', 'uitgave', '12', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('77', '2', '2025-07-20', 'Parkeerkosten Amsterdam', '487.34', 'uitgave', '13', '9.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('78', '2', '2025-05-09', 'Gasrekening maart', '387.63', 'uitgave', '3', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('79', '2', '2025-01-19', 'Internet abonnement', '7.41', 'uitgave', '8', '0.00', '0', '0', '2026-01-16 19:18:47', 'FACT-2025-4251');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('80', '2', '2025-11-04', 'Kantoorartikelen Bol.com', '171.29', 'uitgave', '9', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('81', '2', '2025-08-23', 'Notitieboeken', '101.98', 'uitgave', '3', '21.00', '0', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('82', '2', '2025-06-10', 'Conferentie Cloud Summit', '368.61', 'uitgave', '12', '0.00', '1', '0', '2026-01-16 19:18:47', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('83', '2', '2025-09-14', 'Postzegels', '289.79', 'uitgave', '13', '9.00', '1', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('84', '2', '2025-03-20', 'Benzine tankbeurt', '368.41', 'uitgave', '13', '21.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('85', '2', '2025-02-11', 'Postzegels', '179.05', 'uitgave', '7', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('86', '2', '2025-12-26', 'Taxi Den Haag-Groningen', '335.64', 'uitgave', '7', '9.00', '0', '0', '2026-01-16 19:18:48', 'FACT-2025-4780');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('87', '2', '2025-07-07', 'Gasrekening maart', '294.96', 'uitgave', '14', '9.00', '1', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('88', '2', '2025-07-23', 'Stroomverbruik mei', '73.61', 'uitgave', '14', '0.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('89', '2', '2025-12-03', 'Internet abonnement', '35.89', 'uitgave', '13', '9.00', '0', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('90', '2', '2025-07-12', 'Conferentie Developer Days', '448.23', 'uitgave', '12', '21.00', '1', '0', '2026-01-16 19:18:48', 'FACT-2025-8071');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('91', '2', '2025-06-26', 'Cloud storage Microsoft', '297.78', 'uitgave', '12', '0.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('92', '2', '2025-12-19', 'Gasrekening september', '50.00', 'uitgave', '12', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('93', '2', '2025-07-19', 'Hotel overnachting Eindhoven', '383.66', 'uitgave', '3', '21.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('94', '2', '2025-08-19', 'Waterrekening mei', '470.96', 'uitgave', '3', '0.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('95', '2', '2025-09-10', 'Notitieboeken', '84.62', 'uitgave', '9', '21.00', '0', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('96', '2', '2025-01-04', 'Parkeerkosten Eindhoven', '168.25', 'uitgave', '8', '21.00', '0', '0', '2026-01-16 19:18:48', 'FACT-2025-9125');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('97', '2', '2025-08-11', 'Conferentie Security Expo', '391.14', 'uitgave', '7', '0.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('98', '2', '2025-07-06', 'Envelopes', '421.90', 'uitgave', '8', '21.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('99', '2', '2025-12-13', 'Vliegticket Den Haag', '281.04', 'uitgave', '14', '0.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('100', '2', '2025-10-01', 'Printer inkt', '152.11', 'uitgave', '11', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('101', '2', '2025-07-26', 'Schoonmaakmiddelen', '494.16', 'uitgave', '3', '0.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('102', '2', '2025-05-08', 'Koffie apparatuur', '184.88', 'uitgave', '3', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('103', '2', '2025-11-26', 'Hotel overnachting Groningen', '482.08', 'uitgave', '3', '0.00', '1', '0', '2026-01-16 19:18:48', 'FACT-2025-6952');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('104', '2', '2025-12-16', 'Postzegels', '100.78', 'uitgave', '14', '9.00', '0', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('105', '2', '2025-07-07', 'Telefoonrekening AWS', '375.66', 'uitgave', '13', '9.00', '0', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('106', '2', '2025-11-01', 'OV abonnement', '153.78', 'uitgave', '11', '21.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('107', '2', '2025-03-04', 'Taxi Amsterdam-Eindhoven', '68.04', 'uitgave', '14', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('108', '2', '2025-09-04', 'Webhosting prototype.io', '90.27', 'uitgave', '13', '21.00', '0', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('109', '2', '2025-06-06', 'Webhosting voorbeeld.nl', '387.13', 'uitgave', '14', '9.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('110', '2', '2025-09-17', 'Treinreis Utrecht', '59.19', 'uitgave', '7', '21.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('111', '2', '2025-04-06', 'Treinreis Rotterdam', '258.27', 'uitgave', '8', '0.00', '0', '0', '2026-01-16 19:18:48', 'FACT-2025-4583');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('112', '2', '2025-06-06', 'Webhosting testwebsite.com', '199.53', 'uitgave', '11', '21.00', '1', '1', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('113', '2', '2025-06-16', 'Mobiele data', '46.64', 'uitgave', '14', '0.00', '0', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('114', '2', '2025-03-02', 'Taxi Eindhoven-Groningen', '90.56', 'uitgave', '13', '0.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('115', '2', '2025-11-10', 'Benzine tankbeurt', '104.71', 'uitgave', '12', '0.00', '1', '0', '2026-01-16 19:18:48', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('116', '2', '2025-02-24', 'Vliegticket Amsterdam', '346.47', 'uitgave', '11', '21.00', '0', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('117', '2', '2025-05-03', 'Koffie apparatuur', '387.27', 'uitgave', '3', '9.00', '0', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('118', '2', '2025-06-23', 'Postzegels', '465.34', 'uitgave', '8', '0.00', '1', '0', '2026-01-16 19:18:49', 'FACT-2025-1323');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('119', '2', '2025-09-12', 'Internet abonnement', '276.41', 'uitgave', '8', '0.00', '0', '0', '2026-01-16 19:18:49', 'FACT-2025-2207');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('120', '2', '2025-07-05', 'Benzine tankbeurt', '137.00', 'uitgave', '7', '21.00', '0', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('121', '2', '2025-06-24', 'Conferentie Cloud Summit', '270.73', 'uitgave', '11', '0.00', '1', '0', '2026-01-16 19:18:49', 'FACT-2025-1501');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('122', '2', '2025-04-05', 'Notitieboeken', '83.02', 'uitgave', '3', '21.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('123', '2', '2025-04-07', 'Water cooler', '8.40', 'uitgave', '8', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('124', '2', '2025-12-06', 'Mobiele data', '55.62', 'uitgave', '13', '9.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('125', '2', '2025-04-12', 'Stroomverbruik juni', '359.20', 'uitgave', '8', '9.00', '0', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('126', '2', '2025-08-16', 'Webhosting voorbeeld.nl', '346.89', 'uitgave', '12', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('127', '2', '2025-07-05', 'Schoonmaakmiddelen', '389.54', 'uitgave', '13', '0.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('128', '2', '2025-07-18', 'Benzine tankbeurt', '330.56', 'uitgave', '3', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('129', '2', '2025-02-07', 'Conferentie Cloud Summit', '233.07', 'uitgave', '12', '9.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('130', '2', '2025-06-03', 'Conferentie Tech Conference', '380.42', 'uitgave', '13', '9.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('131', '2', '2025-02-21', 'Schoonmaakmiddelen', '326.24', 'uitgave', '3', '9.00', '0', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('132', '2', '2025-01-16', 'Envelopes', '11.47', 'uitgave', '7', '9.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('133', '2', '2025-08-01', 'Conferentie Tech Conference', '158.43', 'uitgave', '13', '0.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('134', '2', '2025-12-18', 'Envelopes', '172.18', 'uitgave', '9', '0.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('135', '2', '2025-06-26', 'Internet abonnement', '131.25', 'uitgave', '3', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('136', '2', '2025-02-03', 'Envelopes', '151.79', 'uitgave', '14', '0.00', '0', '0', '2026-01-16 19:18:49', 'FACT-2025-0603');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('137', '2', '2025-07-15', 'Treinreis Rotterdam', '128.83', 'uitgave', '7', '9.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('138', '2', '2025-07-21', 'Cloud storage Vodafone', '147.39', 'uitgave', '13', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('139', '2', '2025-06-22', 'Hotel overnachting Den Haag', '427.68', 'uitgave', '11', '21.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('140', '2', '2025-09-11', 'Benzine tankbeurt', '60.29', 'uitgave', '8', '0.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('141', '2', '2025-06-26', 'Conferentie Cloud Summit', '495.46', 'uitgave', '12', '21.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('142', '2', '2025-07-08', 'Taxi Eindhoven-Groningen', '60.82', 'uitgave', '11', '9.00', '1', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('143', '2', '2025-10-16', 'Benzine tankbeurt', '428.83', 'uitgave', '14', '9.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('144', '2', '2025-12-13', 'Vliegticket Den Haag', '153.93', 'uitgave', '13', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('145', '2', '2025-04-26', 'Notitieboeken', '46.97', 'uitgave', '7', '0.00', '1', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('146', '2', '2025-04-22', 'Gasrekening april', '128.73', 'uitgave', '8', '0.00', '1', '0', '2026-01-16 19:18:49', 'FACT-2025-6479');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('147', '2', '2025-04-23', 'Software licentie Zoom', '277.54', 'uitgave', '9', '9.00', '1', '1', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('148', '2', '2025-01-15', 'Vliegticket Utrecht', '441.47', 'uitgave', '9', '0.00', '0', '0', '2026-01-16 19:18:49', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('149', '2', '2025-03-26', 'Printer inkt', '98.01', 'uitgave', '7', '9.00', '0', '1', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('150', '2', '2025-08-12', 'Printer inkt', '454.99', 'uitgave', '7', '0.00', '1', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('151', '2', '2025-01-07', 'Waterrekening maart', '459.29', 'uitgave', '11', '21.00', '1', '1', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('152', '2', '2025-11-26', 'Telefoonrekening AWS', '131.01', 'uitgave', '9', '0.00', '1', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('153', '2', '2025-06-28', 'Postzegels', '40.91', 'uitgave', '8', '21.00', '0', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('154', '2', '2025-09-28', 'Envelopes', '350.82', 'uitgave', '9', '0.00', '0', '0', '2026-01-16 19:18:50', 'FACT-2025-6814');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('155', '2', '2025-07-08', 'Printer inkt', '59.07', 'uitgave', '14', '21.00', '1', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('156', '2', '2025-01-09', 'Benzine tankbeurt', '111.47', 'uitgave', '11', '21.00', '1', '1', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('157', '2', '2025-03-12', 'Telefoonrekening Vodafone', '112.28', 'uitgave', '11', '9.00', '0', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('158', '2', '2025-01-05', 'Telefoonrekening KPN', '33.32', 'uitgave', '12', '21.00', '0', '1', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('159', '2', '2025-04-12', 'Schoonmaakmiddelen', '456.16', 'uitgave', '9', '21.00', '0', '0', '2026-01-16 19:18:50', 'FACT-2025-7965');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('160', '2', '2025-02-16', 'Cloud storage T-Mobile', '206.95', 'uitgave', '9', '21.00', '0', '0', '2026-01-16 19:18:50', NULL);
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('161', '2', '2025-12-22', 'Consultancy diensten oktober', '4856.45', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-3162');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('162', '2', '2025-04-04', 'Webdevelopment prototype.io', '4062.47', 'inkomst', '1', '9.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-7642');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('163', '2', '2025-04-23', 'Hosting voorbeeld.nl', '2241.95', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-2990');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('164', '2', '2025-07-11', 'Advies Logistiek', '2161.78', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-6715');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('165', '2', '2025-03-11', 'Support Backup', '871.71', 'inkomst', '1', '9.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-8918');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('166', '2', '2025-08-27', 'Betaling project Website redesign', '300.20', 'inkomst', '1', '9.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-5213');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('167', '2', '2025-12-22', 'Betaling project Database migration', '4623.38', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-1491');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('168', '2', '2025-02-04', 'Betaling project Website redesign', '4388.86', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-2958');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('169', '2', '2025-10-03', 'Advies Financieel', '244.41', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-7791');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('170', '2', '2025-02-12', 'Factuur klant DigitalWorks', '659.76', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-6242');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('171', '2', '2025-12-01', 'Advies Onderwijs', '4268.26', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-7955');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('172', '2', '2025-09-24', 'Consultancy diensten juni', '978.74', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-3071');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('173', '2', '2025-03-26', 'Support Managed services', '1690.29', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-4743');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('174', '2', '2025-08-07', 'Webdevelopment voorbeeld.nl', '4950.79', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-1232');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('175', '2', '2025-05-04', 'Webdevelopment prototype.io', '3640.03', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-4666');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('176', '2', '2025-05-17', 'Consultancy diensten januari', '4951.07', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-1554');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('177', '2', '2025-07-26', 'Hosting testwebsite.com', '1435.32', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:50', 'INV-2025-3036');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('178', '2', '2025-03-22', 'Support Backup', '4448.01', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-9408');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('179', '2', '2025-07-05', 'Hosting prototype.io', '4644.84', 'inkomst', '1', '9.00', '0', '0', '2026-01-16 19:18:50', 'INV-2025-7743');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('180', '2', '2025-10-24', 'Onderhoudscontract 2025', '3524.86', 'inkomst', '1', '9.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-1901');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('181', '2', '2025-04-27', 'Betaling project Mobile app', '4327.26', 'inkomst', '1', '0.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-9696');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('182', '2', '2025-10-03', 'Advies Financieel', '2484.11', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-9451');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('183', '2', '2025-03-16', 'Consultancy diensten oktober', '2631.10', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-9223');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('184', '2', '2025-06-11', 'Factuur klant CloudExperts', '4374.15', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-9612');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('185', '2', '2025-05-22', 'Factuur klant DigitalWorks', '3141.84', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-2765');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('186', '2', '2025-11-02', 'Support Backup', '3403.21', 'inkomst', '1', '21.00', '1', '0', '2026-01-16 19:18:51', 'INV-2025-9598');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('187', '2', '2025-02-13', 'Support Backup', '1550.20', 'inkomst', '1', '9.00', '0', '0', '2026-01-16 19:18:51', 'INV-2025-1090');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('188', '2', '2025-01-16', 'Factuur klant ABC BV', '1083.37', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:51', 'INV-2025-6287');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('189', '2', '2025-05-11', 'Advies Financieel', '1213.00', 'inkomst', '1', '0.00', '0', '0', '2026-01-16 19:18:51', 'INV-2025-6306');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('190', '2', '2025-11-23', 'Support 24/7 support', '2304.35', 'inkomst', '1', '21.00', '0', '0', '2026-01-16 19:18:51', 'INV-2025-7583');
INSERT INTO `transactions` (`id`, `user_id`, `date`, `description`, `amount`, `type`, `category_id`, `vat_percentage`, `vat_included`, `vat_deductible`, `created_at`, `invoice_number`) VALUES ('191', NULL, '2026-01-17', 'testje', '20.00', 'uitgave', '7', '21.00', '1', '1', '2026-01-17 15:09:48', NULL);

--
-- Dumping data for table `user_categories`
--

INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('1', '1', 'Inkomsten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('3', '1', 'Overig', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('7', '1', 'Transportkosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('8', '1', 'Administratiekosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('9', '1', 'Hotelkosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('10', '1', 'Verzekeringskosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('11', '1', 'Andere kosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('12', '1', 'Communicatiekosten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('13', '1', 'Cloud diensten', '1', 'admin', 'System Administrator');
INSERT INTO `user_categories` (`id`, `user_id`, `name`, `is_system`, `username`, `user_full_name`) VALUES ('14', '1', 'Kantoorkosten', '1', 'admin', 'System Administrator');

--
-- Dumping data for table `user_sessions`
--

--

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `user_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `last_login`) VALUES ('1', 'admin', 'admin@boekhouden.nl', '$2y$12$GEb.j87TqoLjB1gEix.BbO8ZRZmrInAxbYM7NYxp/OXrX3fnYdhp.', 'System Administrator', 'administrator', '1', NULL, '2026-01-17 14:30:36', '2026-01-17 15:12:25', '2026-01-17 15:12:25');
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `user_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `last_login`) VALUES ('2', 'gebruiker1', 'gebruiker1@voorbeeld.nl', '$2y$12$ID2hck8F6MKcIat3.YIuReNF3vwaN.zH1LSV9eq4ih6l6cjGCOZOe', 'Jan Jansen', 'administratie_houder', '1', '1', '2026-01-17 14:30:36', '2026-01-17 15:11:40', '2026-01-17 14:50:24');

--
-- Dumping data for table `vat_rates`
--

INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '21.00', 'Hoog tarief', 'Standaard BTW tarief', '2012-10-01', NULL, '1', '2026-01-16 18:41:50', '2026-01-16 18:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '6.00', 'Verlaagd tarief', 'Verlaagd BTW tarief (oud)', '2012-10-01', '2018-12-31', '1', '2026-01-16 18:41:50', '2026-01-16 18:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('3', '9.00', 'Verlaagd tarief', 'Verlaagd BTW tarief', '2019-01-01', NULL, '1', '2026-01-16 18:41:50', '2026-01-16 18:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('4', '0.00', 'Vrijgesteld', 'Geen BTW (vrijgestelde goederen/diensten)', '2012-10-01', NULL, '1', '2026-01-16 18:41:50', '2026-01-16 18:41:50');

-- End of backup
SET FOREIGN_KEY_CHECKS = 1;

-- boekhouden.user_categories source

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `boekhouden`.`user_categories` AS
select
    `c`.`id` AS `id`,
    `c`.`user_id` AS `user_id`,
    `c`.`name` AS `name`,
    `c`.`is_system` AS `is_system`,
    `u`.`username` AS `username`,
    `u`.`full_name` AS `user_full_name`
from
    (`boekhouden`.`categories` `c`
left join `boekhouden`.`users` `u` on
    ((`c`.`user_id` = `u`.`id`)));

    -- boekhouden.user_transactions source

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `boekhouden`.`user_transactions` AS
select
    `t`.`id` AS `id`,
    `t`.`user_id` AS `user_id`,
    `t`.`date` AS `date`,
    `t`.`description` AS `description`,
    `t`.`amount` AS `amount`,
    `t`.`type` AS `type`,
    `t`.`category_id` AS `category_id`,
    `t`.`vat_percentage` AS `vat_percentage`,
    `t`.`vat_included` AS `vat_included`,
    `t`.`vat_deductible` AS `vat_deductible`,
    `t`.`created_at` AS `created_at`,
    `t`.`invoice_number` AS `invoice_number`,
    `u`.`username` AS `username`,
    `u`.`full_name` AS `user_full_name`
from
    (`boekhouden`.`transactions` `t`
left join `boekhouden`.`users` `u` on
    ((`t`.`user_id` = `u`.`id`)));

    -- boekhouden.vat_calculations source

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `boekhouden`.`vat_calculations` AS
select
    `boekhouden`.`transactions`.`id` AS `id`,
    `boekhouden`.`transactions`.`date` AS `date`,
    `boekhouden`.`transactions`.`description` AS `description`,
    `boekhouden`.`transactions`.`amount` AS `amount`,
    `boekhouden`.`transactions`.`type` AS `type`,
    `boekhouden`.`transactions`.`category_id` AS `category_id`,
    `boekhouden`.`transactions`.`vat_percentage` AS `vat_percentage`,
    `boekhouden`.`transactions`.`vat_included` AS `vat_included`,
    `boekhouden`.`transactions`.`vat_deductible` AS `vat_deductible`,
    (case
        when ((`boekhouden`.`transactions`.`vat_included` = true)
        and (`boekhouden`.`transactions`.`vat_percentage` > 0)) then (`boekhouden`.`transactions`.`amount` - (`boekhouden`.`transactions`.`amount` / (1 + (`boekhouden`.`transactions`.`vat_percentage` / 100))))
        when ((`boekhouden`.`transactions`.`vat_included` = false)
        and (`boekhouden`.`transactions`.`vat_percentage` > 0)) then (`boekhouden`.`transactions`.`amount` * (`boekhouden`.`transactions`.`vat_percentage` / 100))
        else 0
    end) AS `vat_amount`,
    (case
        when ((`boekhouden`.`transactions`.`vat_included` = true)
        and (`boekhouden`.`transactions`.`vat_percentage` > 0)) then (`boekhouden`.`transactions`.`amount` / (1 + (`boekhouden`.`transactions`.`vat_percentage` / 100)))
        else `boekhouden`.`transactions`.`amount`
    end) AS `base_amount`
from
    `boekhouden`.`transactions`;