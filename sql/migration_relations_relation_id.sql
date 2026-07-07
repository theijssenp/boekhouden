-- Migratie: voeg relation_id kolom toe aan transactions
-- Sluit de kloof tussen de code (add_income/add_expense/index.php JOIN/INSERT
-- op transactions.relation_id) en oudere databases waarin deze kolom ontbreekt.
-- Idempotent: veilig meerdere keren uit te voeren.

-- 1. Voeg de kolom toe als deze nog niet bestaat
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND COLUMN_NAME = 'relation_id'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `transactions` ADD COLUMN `relation_id` int DEFAULT NULL AFTER `vat_deductible`',
  'SELECT "relation_id kolom bestaat al, ALTER overgeslagen" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Voeg index toe als deze nog niet bestaat
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND INDEX_NAME = 'idx_relation_id'
);

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `transactions` ADD KEY `idx_relation_id` (`relation_id`)',
  'SELECT "idx_relation_id bestaat al, index overgeslagen" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Voeg foreign key toe als deze nog niet bestaat
--    (alleen als de relations-tabel aanwezig is)
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND CONSTRAINT_NAME = 'fk_transactions_relation_id'
);

SET @relations_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'relations'
);

SET @sql = IF(@fk_exists = 0 AND @relations_exists = 1,
  'ALTER TABLE `transactions` ADD CONSTRAINT `fk_transactions_relation_id` FOREIGN KEY (`relation_id`) REFERENCES `relations` (`id`) ON DELETE SET NULL',
  'SELECT "fk_transactions_relation_id bestaat al of relations-tabel ontbreekt, FK overgeslagen" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;