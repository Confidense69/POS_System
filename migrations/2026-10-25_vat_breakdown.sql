-- ============================================================
-- Migration: store the VAT breakdown on each transaction
-- ============================================================
-- Apply with: php console.php db:migrate
-- ============================================================

USE pos_accounting;

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS vatable_sales    DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
  ADD COLUMN IF NOT EXISTS vat_exempt_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER vatable_sales,
  MODIFY COLUMN discount_id VARCHAR(50) NULL;

-- Backfill regular sales.
UPDATE transactions
SET vatable_sales = subtotal
WHERE customer_type = 'regular'
  AND vatable_sales = 0
  AND subtotal > 0;

-- Backfill senior/PWD sales.
UPDATE transactions
SET vat_exempt_sales = subtotal
WHERE customer_type IN ('senior', 'pwd')
  AND vat_exempt_sales = 0
  AND subtotal > 0;
