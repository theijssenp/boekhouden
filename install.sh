#!/bin/bash
# Boekhouden - Installatiescript voor lokale ontwikkeling
# Vooraf: sudo-rechten vereist
set -e

echo "=== Boekhouden Installatie ==="
echo ""

# 1. Installeer PHP en MySQL
echo "[1/6] PHP en MariaDB installeren..."
sudo apt-get update -qq
sudo apt-get install -y -qq php php-mysql php-mbstring php-xml mariadb-server 2>/dev/null

# 2. Start MariaDB
echo "[2/6] MariaDB starten..."
sudo systemctl start mariadb 2>/dev/null || true
sudo systemctl enable mariadb 2>/dev/null || true

# 3. Maak database en gebruiker aan
echo "[3/6] Database aanmaken..."
DB_PASS="boekhouden_dev"
sudo mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS boekhouden CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'boekhouden'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON boekhouden.* TO 'boekhouden'@'localhost';
FLUSH PRIVILEGES;
EOSQL

# 4. Schoon schema op en importeer
echo "[4/6] Database schema importeren..."
SCHEMA_FILE="/tmp/boekhouden_schema_clean.sql"
# Verwijder PHP warning artefacten uit het schema
sed '/<br \/>/d; /<b>Warning<\/b>/d; /Undefined array key/d; /backup_database\.php/d' \
    /home/pieter/boekhouden/sql/schema.sql > "$SCHEMA_FILE"

# Voeg relations tabel toe als deze ontbreekt
grep -q "CREATE TABLE.*relations" "$SCHEMA_FILE" || cat >> "$SCHEMA_FILE" <<'EOSQL'

CREATE TABLE IF NOT EXISTS `relations` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  KEY `idx_relation_type` (`relation_type`),
  CONSTRAINT `fk_relations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
EOSQL

sudo mysql -u root boekhouden < "$SCHEMA_FILE"
rm "$SCHEMA_FILE"

# 5. Maak config.php aan
echo "[5/6] Configuratie aanmaken..."
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

# 6. Start PHP built-in server
echo "[6/6] PHP server starten op poort 8080..."
echo ""
echo "=== Klaar! ==="
echo ""
echo "Open http://localhost:8080 in je browser"
echo ""
echo "Database:  boekhouden"
echo "DB user:    boekhouden"
echo "DB pass:    boekhouden_dev"
echo ""
echo "Druk op Ctrl+C om de server te stoppen."
echo ""

cd /home/pieter/boekhouden
php -S localhost:8080