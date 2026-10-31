-- =============================================================================
-- Mini Enterprise Inventory & Financial Ledger Engine
-- Database Seed Data
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Clean existing data
TRUNCATE TABLE audit_ledger;
TRUNCATE TABLE invoice_items;
TRUNCATE TABLE invoices;
TRUNCATE TABLE stock_inbound;
TRUNCATE TABLE products;
TRUNCATE TABLE users;
TRUNCATE TABLE roles;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. Seed Roles
-- Default roles: Admin, Store Manager, Sales Operator
-- -----------------------------------------------------------------------------
INSERT INTO roles (id, name, slug, description, created_at, updated_at) VALUES
(1, 'Admin', 'admin', 'Full system administration, ledger oversight, and user management', NOW(), NOW()),
(2, 'Store Manager', 'store-manager', 'Inventory management, inbound stock receiving, and adjustments', NOW(), NOW()),
(3, 'Sales Operator', 'sales-operator', 'Point-of-sale operations, invoice generation, and customer checkout', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 2. Seed Test Users
-- All users are initialized with default password: Password123!
-- Bcrypt Hash: $2y$10$3D566euhmYAYqBcXVkVar.rgsdNMwFIN.pP3i/XJpuC4Z18C.L2pS
-- -----------------------------------------------------------------------------
INSERT INTO users (id, role_id, name, email, password, status, created_at, updated_at) VALUES
(1, 1, 'System Administrator', 'admin@cognova.local', '$2y$10$3D566euhmYAYqBcXVkVar.rgsdNMwFIN.pP3i/XJpuC4Z18C.L2pS', 'active', NOW(), NOW()),
(2, 2, 'Sarah Manager', 'manager@cognova.local', '$2y$10$3D566euhmYAYqBcXVkVar.rgsdNMwFIN.pP3i/XJpuC4Z18C.L2pS', 'active', NOW(), NOW()),
(3, 3, 'David Operator', 'operator@cognova.local', '$2y$10$3D566euhmYAYqBcXVkVar.rgsdNMwFIN.pP3i/XJpuC4Z18C.L2pS', 'active', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 3. Seed Sample Products
-- Products across hardware, peripherals, and enterprise networking
-- -----------------------------------------------------------------------------
INSERT INTO products (id, sku, name, description, unit_cost, selling_price, stock_quantity, reorder_level, status, created_at, updated_at) VALUES
(1, 'SKU-SRV-R750', 'Dell PowerEdge R750 2U Rack Server', 'Dual Intel Xeon Silver 4314, 64GB DDR4, 2x 960GB NVMe SSD', 4200.0000, 5600.0000, 8, 3, 'active', NOW(), NOW()),
(2, 'SKU-NET-SW48', 'Cisco Catalyst 48-Port PoE+ Switch', 'Layer 3 enterprise switch with 4x 10G SFP+ uplinks', 1850.0000, 2499.0000, 15, 5, 'active', NOW(), NOW()),
(3, 'SKU-WAP-AX60', 'Ubiquiti UniFi 6 Pro Access Point', 'Dual-band WiFi 6 wireless AP, 5.3 Gbps aggregate throughput', 115.0000, 179.0000, 42, 10, 'active', NOW(), NOW()),
(4, 'SKU-UPS-3000', 'APC Smart-UPS RT 3000VA On-Line', 'Pure sine wave rackmount power backup with management card', 1200.0000, 1650.0000, 6, 2, 'active', NOW(), NOW()),
(5, 'SKU-SSD-2TB', 'Samsung 870 EVO 2TB SATA SSD', '2.5 inch V-NAND enterprise client SSD', 110.0000, 169.9900, 85, 20, 'active', NOW(), NOW()),
(6, 'SKU-RAM-32GB', 'Kingston 32GB DDR4 3200MHz ECC REG', 'Enterprise server memory module with error-correcting code', 75.0000, 119.5000, 60, 15, 'active', NOW(), NOW()),
(7, 'SKU-MON-27UHD', 'Dell UltraSharp 27 4K USB-C Hub Monitor', 'IPS panel with 90W power delivery and RJ45 ethernet pass-through', 420.0000, 589.0000, 18, 5, 'active', NOW(), NOW()),
(8, 'SKU-CAB-CAT6A', 'Cat6A 10Gbps Shielded Bulk Cable 1000ft', 'CMR blue 23AWG solid bare copper spool', 180.0000, 265.0000, 25, 5, 'active', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 4. Seed Initial Inbound Stock (Purchase / Receiving Log)
-- Initial shipment records received by the Store Manager (user_id = 2)
-- -----------------------------------------------------------------------------
INSERT INTO stock_inbound (id, product_id, user_id, reference_no, supplier_name, quantity, unit_cost, total_cost, batch_no, received_at, notes, created_at, updated_at) VALUES
(1, 1, 2, 'PO-2026-001', 'Enterprise Hardware Direct', 8, 4200.0000, 33600.0000, 'BATCH-R750-01', NOW(), 'Initial server inventory receipt', NOW(), NOW()),
(2, 2, 2, 'PO-2026-001', 'Enterprise Hardware Direct', 15, 1850.0000, 27750.0000, 'BATCH-CISCO-01', NOW(), 'Switch receipt', NOW(), NOW()),
(3, 3, 2, 'PO-2026-002', 'Global Telecom Supply', 50, 115.0000, 5750.0000, 'BATCH-UBNT-01', NOW(), 'Access points shipment', NOW(), NOW()),
(4, 5, 2, 'PO-2026-003', 'Storage Solutions Co', 100, 110.0000, 11000.0000, 'BATCH-SSD-01', NOW(), 'SSD bulk shipment', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 5. Seed Sample Invoices & Invoice Items
-- Completed sale handled by David Operator (user_id = 3)
-- -----------------------------------------------------------------------------
INSERT INTO invoices (id, invoice_number, user_id, customer_name, customer_email, customer_phone, subtotal, tax_amount, discount_amount, total_amount, status, payment_method, notes, created_at, updated_at) VALUES
(1, 'INV-2026-0001', 3, 'Acme Cloud Solutions', 'billing@acmecloud.com', '+1-555-0199', 3981.8500, 398.1500, 0.0000, 4380.0000, 'paid', 'bank_transfer', 'Standard enterprise networking order', NOW(), NOW());

INSERT INTO invoice_items (id, invoice_id, product_id, quantity, unit_price, subtotal, created_at, updated_at) VALUES
(1, 1, 3, 8, 179.0000, 1432.0000, NOW(), NOW()),
(2, 1, 5, 15, 169.9900, 2549.8500, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 6. Seed Audit Ledger Entries
-- Double-entry immutable ledger tracking inventory movements & financial impacts
-- -----------------------------------------------------------------------------
INSERT INTO audit_ledger (transaction_type, entry_category, entity_type, entity_id, product_id, user_id, quantity_delta, unit_cost, total_amount, stock_balance_after, notes, created_at) VALUES
('STOCK_IN', 'INVENTORY', 'stock_inbound', 1, 1, 2, 8, 4200.0000, 33600.0000, 8, 'Inbound PO-2026-001 receipt', NOW()),
('STOCK_IN', 'INVENTORY', 'stock_inbound', 2, 2, 2, 15, 1850.0000, 27750.0000, 15, 'Inbound PO-2026-001 receipt', NOW()),
('STOCK_IN', 'INVENTORY', 'stock_inbound', 3, 3, 2, 50, 115.0000, 5750.0000, 50, 'Inbound PO-2026-002 receipt', NOW()),
('STOCK_IN', 'INVENTORY', 'stock_inbound', 4, 5, 2, 100, 110.0000, 11000.0000, 100, 'Inbound PO-2026-003 receipt', NOW()),
('SALE', 'INVENTORY', 'invoices', 1, 3, 3, -8, 115.0000, 1432.0000, 42, 'Deduction for invoice INV-2026-0001 (8x SKU-WAP-AX60)', NOW()),
('SALE', 'INVENTORY', 'invoices', 1, 5, 3, -15, 110.0000, 2549.8500, 85, 'Deduction for invoice INV-2026-0001 (15x SKU-SSD-2TB)', NOW()),
('SALE', 'FINANCIAL', 'invoices', 1, NULL, 3, 0, 0.0000, 4380.0000, NULL, 'Revenue recognized for invoice INV-2026-0001', NOW());

