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
- **Product Management:** `http://localhost:8080/inventory/index.php`
- **Inbound Stock Orders:** `http://localhost:8080/inventory/stock-in.php`
- **Role Guard Test:** `http://localhost:8080/protected.php?role=Admin`
- **Logout:** `http://localhost:8080/logout.php`

### 3. Default Seed Credentials

All test users are seeded with the password: `Password123!`

| Role | Email | Password | Scope & Permissions |
|------|-------|----------|---------------------|
| **Admin** | `admin@congova.local` | `Password123!` | Full system administration, product catalog, inbound stock, ledger oversight |
| **Store Manager** | `manager@congova.local` | `Password123!` | Inbound stock receiving, catalog creation/updates, low-stock threshold monitoring |
| **Sales Operator** | `operator@congova.local` | `Password123!` | Sales order entry, invoice creation, customer checkout |

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
    requireRole(['Admin', 'Store Manager']); // Protect inventory modules
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
  - `getInboundHistory($limit)`: Returns recent purchase order receipts with product and user details.
  - `getLedgerHistory($limit)`: Returns audit ledger entries.
- **Inventory UI (`public/inventory/index.php` & `public/inventory/stock-in.php`)**:
  - Protected by `requireRole(['Admin', 'Store Manager'])`.
  - Real-time low-stock banner with active counter and visual table highlights.
  - Add/Edit product modal with JavaScript `fetch()` API integration (`public/inventory/api.php`).
  - Real-time stock impact calculator on the Stock Inbound portal.
