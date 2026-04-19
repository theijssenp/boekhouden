#!/bin/bash
set -e

echo "Database droppen en opnieuw aanmaken..."
sudo mysql -u root -e "DROP DATABASE IF EXISTS boekhouden; CREATE DATABASE boekhouden CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; GRANT ALL ON boekhouden.* TO 'boekhouden'@'localhost'; FLUSH PRIVILEGES;"

echo "Schema importeren..."
sudo mysql -u root boekhouden < /home/pieter/boekhouden/sql/schema.sql

echo "Relations tabel aanmaken..."
sudo mysql -u root boekhouden <<'SQL'
CREATE TABLE IF NOT EXISTS `relations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `relation_code` varchar(20) DEFAULT NULL,
  `relation_type` varchar(50) DEFAULT 'klant',
  `company_name` varchar(255) DEFAULT NULL,
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
  `payment_term` int DEFAULT 30,
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `default_vat_rate` decimal(5,2) DEFAULT 21.00,
  `currency` varchar(3) DEFAULT 'EUR',
  `language` varchar(10) DEFAULT 'nl',
  `notes` text,
  `user_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_relations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE transactions ADD COLUMN IF NOT EXISTS `relation_id` int DEFAULT NULL AFTER `invoice_number`;
ALTER TABLE transactions ADD KEY `idx_relation_id` (`relation_id`);
SQL

echo "Config aanmaken..."
cat > /home/pieter/boekhouden/php/config.php <<'EOCFG'
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'boekhouden');
define('DB_PASS', 'boekhouden_dev');
define('DB_NAME', 'boekhouden');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
EOCFG

echo ""
echo "Klaar! Start de server met:"
echo "  cd ~/boekhouden && php -S localhost:8081"