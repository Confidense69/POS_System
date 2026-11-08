-- ============================================================
-- Migration: store simple store settings (currently the VAT rate)
-- ============================================================
-- Apply with:  php console.php db:migrate
--
-- The settings table is a key/value store so later options (store name,
-- receipt header, etc.) do not need another ALTER TABLE.
--
-- setting_value for vat_rate is a PERCENT: 12.00 means 12%.
-- The API divides it by 100 before using it in tax_engine.php.
-- ============================================================

USE pos_accounting;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(50)  NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value)
VALUES ('vat_rate', '12.00')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
