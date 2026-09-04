-- ============================================================
-- POS-Computer Accounting System
-- Database Schema
-- Import via phpMyAdmin or: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pos_accounting
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pos_accounting;

-- ------------------------------------------------------------
-- users: Admin, Manager, Cashier + suppliers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  role          ENUM('admin','manager','cashier') NOT NULL,
  email         VARCHAR(100) NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- suppliers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100) NULL,
  phone       VARCHAR(30)  NULL,
  email       VARCHAR(100) NULL,
  address     VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- products
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barcode       VARCHAR(50)  NOT NULL UNIQUE,
  name          VARCHAR(150) NOT NULL,
  category      VARCHAR(80)  NULL,
  price         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock_qty     INT          NOT NULL DEFAULT 0,
  reorder_level INT          NOT NULL DEFAULT 5,
  expiration_date DATE       NULL,
  supplier_id   INT UNSIGNED NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- transactions (POS sales)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cashier_id     INT UNSIGNED NULL,
  customer_type  ENUM('regular','pwd','senior') NOT NULL DEFAULT 'regular',
  discount_id    VARCHAR(30) NULL,
  tax_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amt   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_amt        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('cash','gcash','maya','maribank') NOT NULL DEFAULT 'cash',
  is_voided      TINYINT(1) NOT NULL DEFAULT 0,
  voided_by      INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (voided_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- transaction_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT UNSIGNED NOT NULL,
  product_id     INT UNSIGNED NULL,
  quantity       INT NOT NULL DEFAULT 1,
  unit_price     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id)     REFERENCES products(id)     ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- restock_log (inventory / restocking)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS restock_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT NOT NULL,
  restock_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note        VARCHAR(255) NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- employees (for payroll) + salaries
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   INT UNSIGNED NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name  VARCHAR(50) NOT NULL,
  role      VARCHAR(50) NULL,
  hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salaries (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT UNSIGNED NOT NULL,
  period_start  DATE NOT NULL,
  period_end    DATE NOT NULL,
  hours_worked  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  gross_pay     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deductions    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_pay       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  paid_at       TIMESTAMP NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- system_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(150) NOT NULL,
  details    TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed: default users
-- Passwords are plain here for setup; hash them in the backend.
-- Default password for all: password123
-- ------------------------------------------------------------
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin',    'password123', 'System Administrator', 'admin'),
('manager',  'password123', 'Store Manager',        'manager'),
('cashier',  'password123', 'Front Desk Cashier',   'cashier');

-- ------------------------------------------------------------
-- Sample suppliers & products (for demo/testing)
-- ------------------------------------------------------------
INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('Metro Distributors', 'Juan Dela Cruz', '0917-000-0001', 'sales@metro.ph', 'Quezon City'),
('FreshMart Grocery',  'Maria Santos',   '0917-000-0002', 'orders@freshmart.ph', 'Manila');

INSERT INTO products (barcode, name, category, price, stock_qty, reorder_level, expiration_date, supplier_id) VALUES
('4800012345678', 'Rice 1kg',        'Grocery',  55.00,  120, 10, '2027-12-31', 2),
('4800012345679', 'Cooking Oil 1L',  'Grocery', 129.00,   80, 10, '2027-12-31', 2),
('4800012345680', 'Coca-Cola 1.5L', 'Beverage', 89.00,   60, 15, '2027-06-30', 1),
('4800012345681', 'Instant Noodles Pack', 'Grocery', 12.00, 200, 50, '2028-01-31', 1);
