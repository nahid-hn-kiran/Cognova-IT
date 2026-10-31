-- =============================================================================
-- Mini Enterprise Inventory & Financial Ledger Engine
-- Database Schema for MySQL 8.0+
-- Storage Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables in reverse dependency order
DROP TABLE IF EXISTS audit_ledger;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS stock_inbound;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. Table: roles
-- Purpose: System access roles for authorization (Admin, Store Manager, Sales Operator)
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_roles_slug (slug),
    INDEX idx_roles_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Access control roles';

-- -----------------------------------------------------------------------------
-- 2. Table: users
-- Purpose: System users with assigned role and credentials
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_email (email),
    INDEX idx_users_status (status),
    INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User credentials and account status';

-- -----------------------------------------------------------------------------
-- 3. Table: products
-- Purpose: Inventory catalog with pricing, stock counts, and reorder levels
-- -----------------------------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    unit_cost DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    selling_price DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_sku (sku),
    INDEX idx_products_name (name),
    INDEX idx_products_stock_quantity (stock_quantity),
    INDEX idx_products_status (status),
    INDEX idx_products_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product master catalog with inventory thresholds';

-- -----------------------------------------------------------------------------
-- 4. Table: stock_inbound
-- Purpose: Inbound inventory shipments / purchase order receipts
-- -----------------------------------------------------------------------------
CREATE TABLE stock_inbound (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reference_no VARCHAR(100) NOT NULL,
    supplier_name VARCHAR(150) NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12, 4) NOT NULL,
    total_cost DECIMAL(12, 4) NOT NULL,
    batch_no VARCHAR(100) NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_inbound_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_inbound_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_stock_inbound_product_id (product_id),
    INDEX idx_stock_inbound_user_id (user_id),
    INDEX idx_stock_inbound_reference_no (reference_no),
    INDEX idx_stock_inbound_received_at (received_at),
    INDEX idx_stock_inbound_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound stock receiving logs';

-- -----------------------------------------------------------------------------
-- 5. Table: invoices
-- Purpose: Sales invoices containing financial totals and customer information
-- -----------------------------------------------------------------------------
CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(64) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NULL,
    customer_phone VARCHAR(50) NULL,
    subtotal DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    tax_amount DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    discount_amount DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    total_amount DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    status ENUM('draft', 'paid', 'partially_paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'paid',
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_invoices_invoice_number (invoice_number),
    INDEX idx_invoices_user_id (user_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales invoice headers';

-- -----------------------------------------------------------------------------
-- 6. Table: invoice_items
-- Purpose: Line items for invoices detailing products, quantities, and pricing
-- -----------------------------------------------------------------------------
CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12, 4) NOT NULL,
    subtotal DECIMAL(12, 4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_invoice_items_invoice_id (invoice_id),
    INDEX idx_invoice_items_product_id (product_id),
    INDEX idx_invoice_items_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales invoice line items';

-- -----------------------------------------------------------------------------
-- 7. Table: audit_ledger
-- Purpose: Immutable double-entry style audit ledger tracking all stock movements & financial entries
-- -----------------------------------------------------------------------------
CREATE TABLE audit_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('STOCK_IN', 'STOCK_OUT', 'SALE', 'REFUND', 'ADJUSTMENT', 'TRANSFER') NOT NULL,
    entry_category ENUM('INVENTORY', 'FINANCIAL') NOT NULL DEFAULT 'INVENTORY',
    entity_type VARCHAR(50) NOT NULL, -- 'stock_inbound', 'invoices', 'products', etc.
    entity_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    quantity_delta INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    total_amount DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    stock_balance_after INT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_ledger_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_audit_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_ledger_tx_type (transaction_type),
    INDEX idx_audit_ledger_entry_category (entry_category),
    INDEX idx_audit_ledger_entity (entity_type, entity_id),
    INDEX idx_audit_ledger_product_id (product_id),
    INDEX idx_audit_ledger_user_id (user_id),
    INDEX idx_audit_ledger_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable audit ledger for stock movements and financial events';

