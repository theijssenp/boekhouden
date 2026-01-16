-- Boekhouden Database Backup (Manual Fallback)
-- Generated: 2026-01-16 17:33:43
-- Note: Using PHP fallback method

create database `boekhouden` ;

USE `boekhouden` ;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET TIME_ZONE = '+00:00';

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES ('1', 'Inkomsten');
INSERT INTO `categories` (`id`, `name`) VALUES ('3', 'Overig');
INSERT INTO `categories` (`id`, `name`) VALUES ('7', 'Transportkosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('8', 'Administratiekosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('9', 'Hotelkosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('10', 'Verzekeringskosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('11', 'Andere kosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('12', 'Communicatiekosten');
INSERT INTO `categories` (`id`, `name`) VALUES ('13', 'Cloud diensten');
INSERT INTO `categories` (`id`, `name`) VALUES ('14', 'Kantoorkosten');

--
-- Table structure for table `current_vat_rates`
--

<br />
<b>Warning</b>:  Undefined array key "Create Table" in <b>/Users/pieter/projects/boekhouden/backup_database.php</b> on line <b>126</b><br />
;

--
-- Dumping data for table `current_vat_rates`
--

INSERT INTO `current_vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`) VALUES ('1', '21.00', 'Hoog tarief', 'Standaard BTW tarief', '2012-10-01', NULL, '1');
INSERT INTO `current_vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`) VALUES ('3', '9.00', 'Verlaagd tarief', 'Verlaagd BTW tarief', '2019-01-01', NULL, '1');
INSERT INTO `current_vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`) VALUES ('4', '0.00', 'Vrijgesteld', 'Geen BTW (vrijgestelde goederen/diensten)', '2012-10-01', NULL, '1');

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
--

-- Table structure for table `vat_rates`
--

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

--
-- Dumping data for table `vat_rates`
--

INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '21.00', 'Hoog tarief', 'Standaard BTW tarief', '2012-10-01', NULL, '1', '2026-01-16 17:41:50', '2026-01-16 17:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '6.00', 'Verlaagd tarief', 'Verlaagd BTW tarief (oud)', '2012-10-01', '2018-12-31', '1', '2026-01-16 17:41:50', '2026-01-16 17:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('3', '9.00', 'Verlaagd tarief', 'Verlaagd BTW tarief', '2019-01-01', NULL, '1', '2026-01-16 17:41:50', '2026-01-16 17:41:50');
INSERT INTO `vat_rates` (`id`, `rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`, `created_at`, `updated_at`) VALUES ('4', '0.00', 'Vrijgesteld', 'Geen BTW (vrijgestelde goederen/diensten)', '2012-10-01', NULL, '1', '2026-01-16 17:41:50', '2026-01-16 17:41:50');

SET FOREIGN_KEY_CHECKS = 1;
-- End of backup

