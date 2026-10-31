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
- **Logout:** `http://localhost:8080/logout.php`
- **Role Guard Test:** `http://localhost:8080/protected.php?role=Admin`

### 3. Default Seed Credentials

All test users are seeded with the password: `Password123!`

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| **Admin** | `admin@congova.local` | `Password123!` | System configuration, audit ledger oversight, user management |
| **Store Manager** | `manager@congova.local` | `Password123!` | Inbound stock receiving, catalog & inventory thresholds |
| **Sales Operator** | `operator@congova.local` | `Password123!` | Sales order entry, invoice creation, item checkout |

---

## Architecture Overview

### Module 1: Environment & Schema
- **Web Server**: Apache running PHP 8.2 with `pdo_mysql`, `bcmath`, `opcache`, and `mod_rewrite`. Document root configured to `/var/www/html/public`.
- **Database**: MySQL 8.0 InnoDB with persistent Docker volume (`db_data`), healthcheck, and `utf8mb4_unicode_ci` collation.
- **Relational Tables**:
  - `roles`: RBAC roles (`Admin`, `Store Manager`, `Sales Operator`).
  - `users`: User accounts with foreign keys to `roles`, hashed passwords, and account statuses.
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
    // Enforce role-based clearance
    requireRole(['Admin']);
    requireRole(['Store Manager']);
    requireRole(['Sales Operator']);
    requireRole(['Admin', 'Store Manager']); // Multiple allowed roles
    ```
  - Automatic handling for browser requests (redirects to `/login.php?redirect=...` or renders 403 Forbidden page) and AJAX/JSON API requests (returns HTTP 401/403 JSON payloads).
- **Authentication UI (`public/login.php` & `public/logout.php`)**:
  - Secure login portal with CSRF verification and session fixation prevention.
  - Quick-fill buttons for seeded test accounts (Admin, Store Manager, Sales Operator).
