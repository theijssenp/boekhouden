-- Boekhouden - databaseschema
-- @author P. Theijssen
--
-- Dit is het enige schemabestand. Het bevat de structuur en de vaste
-- referentiedata (BTW-tarieven en systeemcategorieen), maar geen
-- gebruikers of transacties.
--
-- Idempotent: veilig om meerdere keren te draaien. Op een lege database
-- maakt het alles aan; op een bestaande database vult het alleen aan wat
-- ontbreekt. Er wordt nooit iets verwijderd.
--
-- Draai dit niet handmatig, gebruik ./setup.sh

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabellen
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `user_type` enum('administrator','administratie_houder') NOT NULL DEFAULT 'administratie_houder',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Gebruikers en hun rol';

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `is_system` tinyint(1) DEFAULT 0 COMMENT '1 = systeemcategorie (user_id is NULL), 0 = eigen categorie',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_categories_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Categorieen voor transacties';

CREATE TABLE IF NOT EXISTS `relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `relation_code` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `relation_type` enum('debiteur','crediteur','beide') NOT NULL DEFAULT 'debiteur',
  `contact_person` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nederland',
  `vat_number` varchar(50) DEFAULT NULL,
  `coc_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `payment_term` int(11) DEFAULT 30,
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `default_vat_rate` decimal(5,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'EUR',
  `language` varchar(10) DEFAULT 'nl',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `relation_code` (`relation_code`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_relation_type` (`relation_type`),
  CONSTRAINT `fk_relations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Relaties (debiteuren, crediteuren)';

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('inkomst','uitgave') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `vat_percentage` decimal(5,2) DEFAULT 0.00,
  `vat_included` tinyint(1) DEFAULT 0,
  `vat_deductible` tinyint(1) DEFAULT 0,
  -- `amount` betekent afhankelijk van `vat_included` iets anders: bij 0 is
  -- het het bedrag exclusief BTW, bij 1 inclusief. Deze drie kolommen maken
  -- de splitsing per boeking expliciet, zodat rapportages niet zelf hoeven
  -- te rekenen en er nooit twee verschillende uitkomsten kunnen ontstaan.
  -- De database berekent ze; ze zijn niet te schrijven en kunnen dus niet
  -- uit de pas lopen met `amount`.
  -- Afronding gebeurt per boeking, want dat is het bedrag dat op de factuur
  -- staat en in de aangifte terechtkomt.
  `amount_excl` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_included`,0) = 1 AND COALESCE(`vat_percentage`,0) > 0
           THEN ROUND(`amount` / (1 + `vat_percentage` / 100), 2)
           ELSE `amount` END) STORED,
  `vat_amount` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_percentage`,0) <= 0 THEN 0
           WHEN COALESCE(`vat_included`,0) = 1
           THEN `amount` - ROUND(`amount` / (1 + `vat_percentage` / 100), 2)
           ELSE ROUND(`amount` * `vat_percentage` / 100, 2) END) STORED,
  `amount_incl` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_percentage`,0) <= 0 THEN `amount`
           WHEN COALESCE(`vat_included`,0) = 1 THEN `amount`
           ELSE `amount` + ROUND(`amount` * `vat_percentage` / 100, 2) END) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `invoice_number` varchar(50) DEFAULT NULL,
  `relation_id` int(11) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL COMMENT 'Pad naar het bonnetje, relatief aan de opslagmap',
  `receipt_original_name` varchar(255) DEFAULT NULL COMMENT 'Originele bestandsnaam van het bonnetje',
  `receipt_mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME type van het bonnetje',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_relation_id` (`relation_id`),
  KEY `idx_transactions_user_date` (`user_id`,`date`),
  KEY `idx_transactions_user_type` (`user_id`,`type`),
  CONSTRAINT `fk_transactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transactions_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_transactions_relation_id` FOREIGN KEY (`relation_id`) REFERENCES `relations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Financiele transacties';

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Sessiebeheer';

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Audit trail voor beheerhandelingen';

CREATE TABLE IF NOT EXISTS `vat_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rate` decimal(5,2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_effective_dates` (`effective_from`,`effective_to`,`is_active`),
  KEY `idx_rate_active` (`rate`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='BTW-tarieven met geldigheidsperiode';

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Bijwerken van bestaande databases
--
-- Kolommen en indexen die later zijn toegevoegd. Op een verse installatie
-- staan ze al in de CREATE TABLE hierboven en gebeurt hier niets.
-- ---------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `_bh_add_column`;
DROP PROCEDURE IF EXISTS `_bh_add_index`;
DROP PROCEDURE IF EXISTS `_bh_add_foreign_key`;
DROP PROCEDURE IF EXISTS `_bh_drop_column_if_empty`;

DELIMITER $$

CREATE PROCEDURE `_bh_add_column`(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
    PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END$$

CREATE PROCEDURE `_bh_add_index`(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
  ) THEN
    SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_ddl);
    PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END$$

-- Verwijdert een kolom, maar alleen als er in geen enkele rij een waarde
-- in staat. Zo kan een achterhaalde kolom verdwijnen zonder ooit stilzwijgend
-- gegevens weg te gooien.
CREATE PROCEDURE `_bh_drop_column_if_empty`(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
  DECLARE v_filled INT DEFAULT 0;

  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @cnt = CONCAT('SELECT COUNT(*) INTO @filled_count FROM `', p_table,
                      '` WHERE `', p_column, '` IS NOT NULL');
    PREPARE stmt FROM @cnt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    SET v_filled = @filled_count;

    IF v_filled = 0 THEN
      SET @ddl = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
      PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    ELSE
      SELECT CONCAT('Kolom ', p_table, '.', p_column, ' niet verwijderd: ', v_filled,
                    ' rijen bevatten nog gegevens. Zet die eerst veilig.') AS waarschuwing;
    END IF;
  END IF;
END$$

-- Voegt een foreign key toe, maar alleen als er geen verweesde rijen zijn.
-- Bij oude data die niet klopt slaan we hem over in plaats van te crashen.
CREATE PROCEDURE `_bh_add_foreign_key`(IN p_table VARCHAR(64), IN p_name VARCHAR(64),
                                       IN p_column VARCHAR(64), IN p_ref_table VARCHAR(64),
                                       IN p_ddl TEXT)
BEGIN
  DECLARE v_orphans INT DEFAULT 0;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
      AND CONSTRAINT_NAME = p_name AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    SET @cnt = CONCAT(
      'SELECT COUNT(*) INTO @orphan_count FROM `', p_table, '` c ',
      'LEFT JOIN `', p_ref_table, '` p ON c.`', p_column, '` = p.`id` ',
      'WHERE c.`', p_column, '` IS NOT NULL AND p.`id` IS NULL');
    PREPARE stmt FROM @cnt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    SET v_orphans = @orphan_count;

    IF v_orphans = 0 THEN
      SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_name, '` ', p_ddl);
      PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    ELSE
      SELECT CONCAT('Foreign key ', p_name, ' overgeslagen: ', v_orphans,
                    ' rijen in ', p_table, '.', p_column,
                    ' verwijzen naar een niet-bestaande ', p_ref_table, '.') AS waarschuwing;
    END IF;
  END IF;
END$$

DELIMITER ;

CALL `_bh_add_column`('transactions', 'relation_id',
  '`relation_id` int(11) DEFAULT NULL AFTER `invoice_number`');
CALL `_bh_add_column`('transactions', 'receipt_path',
  '`receipt_path` varchar(255) DEFAULT NULL COMMENT ''Pad naar het bonnetje, relatief aan de opslagmap'' AFTER `relation_id`');
CALL `_bh_add_column`('transactions', 'receipt_original_name',
  '`receipt_original_name` varchar(255) DEFAULT NULL AFTER `receipt_path`');
CALL `_bh_add_column`('transactions', 'receipt_mime_type',
  '`receipt_mime_type` varchar(100) DEFAULT NULL AFTER `receipt_original_name`');

-- Bonnetjes stonden ooit als BLOB in de database en staan nu op het
-- filesystem. De kolom verdwijnt alleen als er niets meer in staat.
CALL `_bh_drop_column_if_empty`('transactions', 'receipt_blob');
CALL `_bh_add_column`('relations', 'is_active',
  '`is_active` tinyint(1) DEFAULT 1');

-- Bedragen exclusief/inclusief BTW per boeking (zie toelichting bij de
-- tabeldefinitie). Bestaande installaties krijgen ze hier alsnog.
CALL `_bh_add_column`('transactions', 'amount_excl',
  '`amount_excl` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_included`,0) = 1 AND COALESCE(`vat_percentage`,0) > 0
           THEN ROUND(`amount` / (1 + `vat_percentage` / 100), 2)
           ELSE `amount` END) STORED AFTER `vat_deductible`');
CALL `_bh_add_column`('transactions', 'vat_amount',
  '`vat_amount` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_percentage`,0) <= 0 THEN 0
           WHEN COALESCE(`vat_included`,0) = 1
           THEN `amount` - ROUND(`amount` / (1 + `vat_percentage` / 100), 2)
           ELSE ROUND(`amount` * `vat_percentage` / 100, 2) END) STORED AFTER `amount_excl`');
CALL `_bh_add_column`('transactions', 'amount_incl',
  '`amount_incl` decimal(10,2) GENERATED ALWAYS AS (
      CASE WHEN COALESCE(`vat_percentage`,0) <= 0 THEN `amount`
           WHEN COALESCE(`vat_included`,0) = 1 THEN `amount`
           ELSE `amount` + ROUND(`amount` * `vat_percentage` / 100, 2) END) STORED AFTER `vat_amount`');

CALL `_bh_add_index`('transactions', 'idx_relation_id', 'KEY `idx_relation_id` (`relation_id`)');

CALL `_bh_add_foreign_key`('transactions', 'fk_transactions_relation_id', 'relation_id', 'relations',
  'FOREIGN KEY (`relation_id`) REFERENCES `relations` (`id`) ON DELETE SET NULL');

DROP PROCEDURE `_bh_add_column`;
DROP PROCEDURE `_bh_add_index`;
DROP PROCEDURE `_bh_add_foreign_key`;
DROP PROCEDURE `_bh_drop_column_if_empty`;

-- ---------------------------------------------------------------------
-- Views
-- ---------------------------------------------------------------------

CREATE OR REPLACE VIEW `user_transactions` AS
SELECT t.id, t.user_id, t.date, t.description, t.amount, t.type, t.category_id,
       t.vat_percentage, t.vat_included, t.vat_deductible, t.created_at, t.invoice_number,
       u.username, u.full_name AS user_full_name
FROM transactions t
LEFT JOIN users u ON t.user_id = u.id;

CREATE OR REPLACE VIEW `user_categories` AS
SELECT c.id, c.user_id, c.name, c.is_system,
       u.username, u.full_name AS user_full_name
FROM categories c
LEFT JOIN users u ON c.user_id = u.id;

-- De view rekende de BTW zelf uit; dat gebeurt nu in de tabel zelf.
-- `base_amount` blijft bestaan als alias voor `amount_excl`, zodat
-- bestaande queries blijven werken.
CREATE OR REPLACE VIEW `vat_calculations` AS
SELECT t.id, t.date, t.description, t.amount, t.type, t.category_id,
       t.vat_percentage, t.vat_included, t.vat_deductible,
       t.vat_amount,
       t.amount_excl AS base_amount,
       t.amount_excl,
       t.amount_incl
FROM transactions t;

-- ---------------------------------------------------------------------
-- Referentiedata
--
-- INSERT IGNORE / WHERE NOT EXISTS: bestaande rijen blijven ongemoeid.
-- ---------------------------------------------------------------------

INSERT INTO `vat_rates` (`rate`, `name`, `description`, `effective_from`, `effective_to`, `is_active`)
SELECT * FROM (
  SELECT 21.00 AS r, 'Hoog tarief'      AS n, 'Standaard BTW tarief'                       AS d, '2012-10-01' AS f, NULL         AS t, 1 AS a UNION ALL
  SELECT  9.00,      'Verlaagd tarief',      'Verlaagd BTW tarief',                             '2019-01-01',      NULL,              1 UNION ALL
  SELECT  6.00,      'Verlaagd tarief',      'Verlaagd BTW tarief (oud)',                       '2012-10-01',      '2018-12-31',      1 UNION ALL
  SELECT  0.00,      'Vrijgesteld',          'Geen BTW (vrijgestelde goederen/diensten)',       '2012-10-01',      NULL,              1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `vat_rates`);

INSERT INTO `categories` (`user_id`, `name`, `is_system`)
SELECT * FROM (
  SELECT NULL AS u, 'Inkomsten'                     AS n, 1 AS s UNION ALL
  SELECT NULL, 'Overig', 1                          UNION ALL
  SELECT NULL, 'Transportkosten', 1                 UNION ALL
  SELECT NULL, 'Administratiekosten', 1             UNION ALL
  SELECT NULL, 'Hotelkosten', 1                     UNION ALL
  SELECT NULL, 'Verzekeringskosten', 1              UNION ALL
  SELECT NULL, 'Andere kosten', 1                   UNION ALL
  SELECT NULL, 'Communicatiekosten', 1              UNION ALL
  SELECT NULL, 'Cloud diensten', 1                  UNION ALL
  SELECT NULL, 'Kantoorkosten', 1                   UNION ALL
  SELECT NULL, 'Inkoopkosten', 1                    UNION ALL
  SELECT NULL, 'Personeelskosten', 1                UNION ALL
  SELECT NULL, 'Vaste lasten', 1                    UNION ALL
  SELECT NULL, 'Variabele lasten', 1                UNION ALL
  SELECT NULL, 'Financiële kosten', 1               UNION ALL
  SELECT NULL, 'Afschrijvingskosten', 1             UNION ALL
  SELECT NULL, 'Bijzondere lasten', 1               UNION ALL
  SELECT NULL, 'Reiskosten', 1                      UNION ALL
  SELECT NULL, 'Vertegenwoordigingskosten', 1       UNION ALL
  SELECT NULL, 'Advertentiekosten', 1               UNION ALL
  SELECT NULL, 'Commissies en provisies', 1         UNION ALL
  SELECT NULL, 'Rentekosten', 1                     UNION ALL
  SELECT NULL, 'Bankkosten', 1                      UNION ALL
  SELECT NULL, 'Onderhoud- en reparatiekosten', 1   UNION ALL
  SELECT NULL, 'Schade- en verlieskosten', 1        UNION ALL
  SELECT NULL, 'Boetes en sancties', 1              UNION ALL
  SELECT NULL, 'Donaties en sponsoring', 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `is_system` = 1);
