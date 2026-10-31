# Mini Enterprise Inventory & Financial Ledger Engine

A containerized PHP 8.2 & MySQL 8.0 enterprise inventory management and immutable financial ledger engine.

## Quick Start

### 1. Start Docker Containers
```bash
docker compose up -d --build
```

### 2. Verify Application & Access Dashboard
Open your browser and navigate to:
```
http://localhost:8080
```
- **Login Portal:** `http://localhost:8080/login.php`
- **Sales POS Terminal:** `http://localhost:8080/sales/index.php`
- **Sales Invoices History:** `http://localhost:8080/sales/invoices.php`
- **Product Management:** `http://localhost:8080/inventory/index.php`
- **Inbound Stock Orders:** `http://localhost:8080/inventory/stock-in.php`
- **Global Audit Ledger:** `http://localhost:8080/admin/audit-ledger.php`
- **Staff User Management:** `http://localhost:8080/admin/users.php`
- **Role Guard Test:** `http://localhost:8080/protected.php?role=Admin`
- **Logout:** `http://localhost:8080/logout.php`

### 3. Default Seed Credentials

All test users are seeded with the password: `Password123!`

| Role | Email | Password | Scope & Permissions |
|------|-------|----------|---------------------|
| **Admin** | `admin@congova.local` | `Password123!` | Full system administration, sales checkout, product catalog, inbound stock, audit ledger oversight, staff management, transactional reversals |
| **Sales Operator** | `operator@congova.local` | `Password123!` | Sales order entry, dynamic POS checkout, invoice generation, customer receipts |
| **Store Manager** | `manager@congova.local` | `Password123!` | Inbound stock receiving, catalog creation/updates, low-stock threshold monitoring |

---

## Architecture Overview

### Module 1: Environment & Database Schema
- **Web Server**: Apache running PHP 8.2 with `pdo_mysql`, `bcmath`, `opcache`, and `mod_rewrite`. Document root configured to `/var/www/html/public`.
- **Database**: MySQL 8.0 InnoDB with persistent Docker volume (`db_data`), healthcheck, and `utf8mb4_unicode_ci` collation.
- **Relational Tables**:
  - `roles`: RBAC roles (`Admin`, `Store Manager`, `Sales Operator`).
  - `users`: User accounts with role foreign keys, hashed passwords, and account statuses.
  - `products`: Product master catalog with SKUs, costs, prices, stock levels, and reorder levels.
  - `stock_inbound`: Supplier receipt logs and purchase order tracking.
  - `invoices`: Sales transactions with financial subtotals, tax, discounts, and payment methods.
  - `invoice_items`: Invoice line items linked to products and invoices.
  - `audit_ledger`: Double-entry audit ledger recording inventory movements and financial events.

### Module 2: Core Backend Foundation & Role-Based Auth
- **Database Connection (`src/Config/Database.php`)**:
  - Thread-safe singleton PDO connection using `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
  - Strict error reporting (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`).
  - Emulated prepared statements disabled (`PDO::ATTR_EMULATE_PREPARES => false`).
  - Default fetch mode set to `PDO::FETCH_ASSOC`.
- **Authentication & Route Guards (`src/Middleware/AuthMiddleware.php` & `includes/auth.php`)**:
  - Hardened session management with `HttpOnly`, `SameSite=Lax`, strict mode, and session regeneration on login.
  - Credential verification via `password_verify()` against bcrypt hashes.
  - Route guards:
    ```php
    requireRole(['Admin']);                  // Global admin console & reversals
    requireRole(['Admin', 'Sales Operator']); // Sales POS terminal
    requireRole(['Admin', 'Store Manager']);  // Inventory management
    ```
- **Authentication UI (`public/login.php` & `public/logout.php`)**:
  - Secure login portal with CSRF verification, session fixation prevention, and quick-fill test account chips.

### Module 3: Inventory Management & Stock-In Module
- **Product Service (`src/Services/ProductService.php`)**:
  - `getAll($filters)`: Query active/archived products with search (`q`), low stock calculation, and gross margin computation.
  - `create($data)`: Validates SKU uniqueness, sanitizes inputs, and logs initial stock valuation into `audit_ledger`.
  - `update($id, $data)`: Updates product specifications, costs, prices, and reorder thresholds.
  - `getLowStockCount()`: Counts items at or below their reorder level (`stock_quantity <= reorder_level`).
- **Inventory Service (`src/Services/InventoryService.php`)**:
  - `recordStockIn(...)`: Concurrency-safe atomic transaction using `SELECT ... FOR UPDATE` row-level locking.
    - Increments product's `stock_quantity`.
    - Inserts receipt into `stock_inbound`.
    - Inserts immutable transaction entry into `audit_ledger` with `quantity_delta`, `unit_cost`, `total_amount`, and `stock_balance_after`.
- **Inventory UI (`public/inventory/index.php` & `public/inventory/stock-in.php`)**:
  - Protected by `requireRole(['Admin', 'Store Manager'])`.
  - Real-time low-stock banner with active counter and visual table highlights.
  - Real-time stock impact calculator on the Stock Inbound portal.

### Module 4: Sales Invoicing & Atomic Concurrency Engine
- **Invoice Service (`src/Services/InvoiceService.php`)**:
  - `createInvoice($headerData, $items, $userId)`:
    - **ACID Transaction:** Executes inside `$pdo->beginTransaction()` ... `$pdo->commit()`.
    - **Deadlock-Free Row-Level Locking:** Product IDs are numerically sorted before executing `SELECT ... FOR UPDATE` across affected rows.
    - **Zero-Stock & Insufficient Stock Enforcement:** Strictly rejects transactions if available `stock_quantity <= 0` or if `requested_quantity > stock_quantity`.
    - **Atomic Stock Deduction:** Decrements `products.stock_quantity`.
    - **Audit Ledger Writes:**
      - Inventory reduction entry: `quantity_delta = -qty`, `stock_balance_after = newStock`, `category = INVENTORY`.
      - Financial revenue entry: `total_amount = netTotal`, `category = FINANCIAL`.
- **Sales UI & API (`public/sales/`)**:
  - `public/sales/index.php`: Dynamic POS terminal with cart row addition, live subtotals, tax/discount adjustments, and client-side insufficient stock guardrails.
  - `public/sales/api.php`: Asynchronous JSON checkout endpoint protected by CSRF verification and `requireRole(['Admin', 'Sales Operator'])`.
  - `public/sales/invoice.php`: Printer-ready enterprise receipt view with customer billing details and settlement confirmation.
  - `public/sales/invoices.php`: Searchable invoice transaction register with revenue statistics.

### Module 5: Audit Ledger & Administrative Controls (Admin)
- **Ledger Review & Reversal Service (`src/Services/LedgerService.php`)**:
  - `getAuditLogs($filters, $page, $perPage)`: Paginated, filterable double-entry audit log (by action type, category, user, SKU, date range).
  - `getMetrics()`: Aggregate metrics for events, recognized revenue, and refunded volume.
  - `reverseInvoice($invoiceId, $reason, $adminUserId)`:
    - Atomically locks invoice and line items in sorted order (`SELECT ... FOR UPDATE`).
    - Marks invoice status as `refunded`.
    - Restores inventory stock counts for every product item.
    - Logs compensating `REFUND` entries into `audit_ledger` for both inventory and financial ledger tracks.
  - `reverseInbound($inboundId, $reason, $adminUserId)`:
    - Deducts inbound quantity from stock, prevents negative stock, and logs an `ADJUSTMENT` audit entry.
- **User & Role Management Service (`src/Services/UserService.php`)**:
  - Staff user provisioning, bcrypt password hashing, and role assignments (`Admin`, `Store Manager`, `Sales Operator`).
  - Status management (`active`, `suspended`, `inactive`).
  - Self-deactivation and demotion lockout protection for active administrators.
- **Admin UI & API (`public/admin/`)**:
  - `public/admin/audit-ledger.php`: Filterable audit ledger console with transactional reversal modals.
  - `public/admin/users.php`: Staff accounts and RBAC permission manager with one-click suspension/activation toggles.
  - `public/admin/api.php`: Protected administrative endpoint for reversals and user CRUD operations.
