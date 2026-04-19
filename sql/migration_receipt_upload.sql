-- Bonnetje upload: BLOB opslag in transactions tabel
ALTER TABLE transactions
  ADD COLUMN `receipt_blob` longblob DEFAULT NULL
    COMMENT 'Binary content van bonnetje (afbeelding of PDF)' AFTER `invoice_number`,
  ADD COLUMN `receipt_original_name` varchar(255) DEFAULT NULL
    COMMENT 'Originele bestandsnaam' AFTER `receipt_blob`,
  ADD COLUMN `receipt_mime_type` varchar(100) DEFAULT NULL
    COMMENT 'MIME type van het bonnetje' AFTER `receipt_original_name`;