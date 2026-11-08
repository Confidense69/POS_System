-- ============================================================
-- Migration: store simple store settings (currently the VAT rate)
-- ============================================================
-- Apply with: php console.php db:migrate
-- vat_rate is a percent: 12.00 = 12%.
-- ============================================================

USE pos_accounting;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(50)  NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value)
VALUES ('vat_rate', '12.00')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
