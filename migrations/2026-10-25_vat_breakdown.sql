-- ============================================================
-- Migration: store the VAT breakdown on each transaction
-- ============================================================
-- Apply with:  php console.php db:migrate
-- (or manually: Get-Content migrations\2026-10-25_vat_breakdown.sql |
--               C:\xampp\mysql\bin\mysql.exe -u root pos_accounting)
--
-- Safe to re-run: uses MariaDB's ADD COLUMN IF NOT EXISTS (XAMPP 10.x).
-- Fresh installs already get these columns from database.sql, so the ALTER
-- is a no-op there.
-- ============================================================

USE pos_accounting;

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS vatable_sales    DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
  ADD COLUMN IF NOT EXISTS vat_exempt_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vatable_sales,
  MODIFY COLUMN discount_id VARCHAR(50) NULL;

-- Backfill existing rows so old reports are not misleading.
-- The old checkout stored the cart subtotal in `subtotal` (VAT-exclusive) and
-- added VAT on top, so regular rows backfill directly.
UPDATE transactions
SET vatable_sales = subtotal
WHERE customer_type = 'regular'
  AND vatable_sales = 0
  AND subtotal > 0;

-- Old senior/PWD rows stored the cart subtotal with zero VAT; prices are
-- VAT-exclusive, so the whole subtotal is the VAT-exempt amount.
UPDATE transactions
SET vat_exempt_sales = subtotal
WHERE customer_type IN ('senior', 'pwd')
  AND vat_exempt_sales = 0
  AND subtotal > 0;
