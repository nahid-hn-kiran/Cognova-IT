# Mini Enterprise Inventory & Financial Ledger Engine

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-Proprietary-blue.svg?style=flat-square)]()

A high-concurrency, containerized **PHP 8.2 & MySQL 8.0 Enterprise Inventory Management & Double-Entry Financial Ledger Engine**. Built with strict ACID transactional guarantees, row-level concurrency locking (`SELECT ... FOR UPDATE`), zero-stock enforcement, role-based access control (RBAC), and an immutable audit trail.

---

## Table of Contents
1. [Prerequisites & System Requirements](#prerequisites--system-requirements)
2. [Quick Start & Setup Instructions](#quick-start--setup-instructions)
3. [Default Demo Credentials & RBAC Access Matrix](#default-demo-credentials--rbac-access-matrix)
4. [Concurrency Engine & ACID Transaction Architecture](#concurrency-engine--acid-transaction-architecture)
5. [Core Architectural Modules](#core-architectural-modules)
   - [Module 1: Docker Environment & Database Schema](#module-1-docker-environment--database-schema)
   - [Module 2: Core Backend Foundation & Role-Based Auth](#module-2-core-backend-foundation--role-based-auth)
   - [Module 3: Inventory Management & Stock-In Module](#module-3-inventory-management--stock-in-module)
   - [Module 4: Sales Invoicing & Atomic Concurrency Engine](#module-4-sales-invoicing--atomic-concurrency-engine)
   - [Module 5: Global Audit Ledger & Administrative Reversals](#module-5-global-audit-ledger--administrative-reversals)
6. [API Endpoints Reference](#api-endpoints-reference)
7. [Database Schema Overview](#database-schema-overview)
8. [Troubleshooting & Maintenance](#troubleshooting--maintenance)

---

## Prerequisites & System Requirements

- **Docker**: Version `20.10+` (or Docker Desktop on macOS/Windows)
- **Docker Compose**: Version `v2.0+` (`docker compose`)
- **Port Availability**:
  - `8080` (HTTP Web Server)
  - `3306` (MySQL Database)
- **Web Browser**: Modern Chromium, Firefox, or Safari with JavaScript enabled.

---

## Quick Start & Setup Instructions

### 1. Clone & Enter Project Root
```bash
git clone <repository-url> congova-it
cd congova-it
```

### 2. Launch Docker Containers
Build and start the PHP 8.2 Apache web container and the MySQL 8.0 database service in detached mode:
```bash
docker compose up -d --build
```

The database initializes automatically on first startup by executing `database/schema.sql` followed by `database/seed.sql`.

### 3. Access Web Application
Open your browser and navigate to:
```
http://localhost:8080
```

To access specific application views:
- **Authentication Portal:** [http://localhost:8080/login.php](http://localhost:8080/login.php)
- **Executive Dashboard:** [http://localhost:8080/index.php](http://localhost:8080/index.php)
- **Sales Checkout POS:** [http://localhost:8080/sales/index.php](http://localhost:8080/sales/index.php)
- **Sales Invoices Register:** [http://localhost:8080/sales/invoices.php](http://localhost:8080/sales/invoices.php)
- **Product Inventory Catalog:** [http://localhost:8080/inventory/index.php](http://localhost:8080/inventory/index.php)
- **Stock Inbound Receiving:** [http://localhost:8080/inventory/stock-in.php](http://localhost:8080/inventory/stock-in.php)
- **Global Audit Ledger Console:** [http://localhost:8080/admin/audit-ledger.php](http://localhost:8080/admin/audit-ledger.php)
- **Staff User Management:** [http://localhost:8080/admin/users.php](http://localhost:8080/admin/users.php)

---

## Default Demo Credentials & RBAC Access Matrix

The system is pre-seeded with test accounts representing three operational roles. All seeded accounts share the default password: **`Password123!`**

| Role | Email Address | Password | Permissions & Operational Scope |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@congova.local` | `Password123!` | **Full Executive Clearance:** Global audit ledger review, ACID transactional reversals (refunds/voids), staff account provisioning & status toggles, product catalog management, stock inbound receiving, and sales POS checkout. |
| **Store Manager** | `manager@congova.local` | `Password123!` | **Inventory & Supply Chain:** Product catalog creation and updates, low-stock threshold monitoring, inbound stock purchase order receipts, and invoice history viewing. |
| **Sales Operator** | `operator@congova.local` | `Password123!` | **Point of Sale & Invoicing:** Customer sales order entry, live cart calculations, atomic checkout execution, receipt printing, and sales register viewing. |

> **Pro Tip:** The login page includes **Quick-Fill Buttons** for Admin, Manager, and Operator to instantly populate credentials with a single click.

---

## Concurrency Engine & ACID Transaction Architecture

In a high-throughput enterprise environment, multiple sales operators and store managers concurrently execute transactions against the same product catalog. Without rigorous concurrency controls, race conditions lead to overselling (negative stock) and balance drift.

The engine guarantees strict **ACID compliance** (Atomicity, Consistency, Isolation, Durability) through a multi-tier concurrency pattern:

```
[ Sales Operator Checkout Request ]
                │
                ▼
      [ Input & Cart Sanity ]
                │
                ▼
   $pdo->beginTransaction()
                │
                ▼
  [ 1. Sort Product IDs Ascending ] ─────────► [ Deadlock Elimination ]
                │
                ▼
  [ 2. SELECT ... FOR UPDATE ] ──────────────► [ Row-Level Exclusive Lock ]
                │
                ▼
  [ 3. Stock Level Validation ]
       ├── If Stock <= 0          ───────────► Rollback & Throw (Out of Stock)
       └── If Requested > Stock   ───────────► Rollback & Throw (Insufficient Stock)
                │
                ▼
  [ 4. Atomic Balance Deductions ] ──────────► products.stock_quantity = stock - qty
                │
                ▼
  [ 5. Insert Invoice & Line Items ]
                │
                ▼
  [ 6. Double-Entry Audit Ledger ] ──────────► Invariant: Stock Delta & Financial Revenue
                │
                ▼
        $pdo->commit()
                │
                ▼
     [ Invoice Settled (200 OK) ]
```

### 1. Deadlock Elimination via Ordered Product Locking
When a customer orders multiple products in a single invoice, locking rows in arbitrary order across concurrent threads can trigger a database deadlock (e.g., Transaction A locks Product 1 and waits for Product 2; Transaction B locks Product 2 and waits for Product 1).

The engine eliminates deadlocks by sorting all unique product IDs numerically in ascending order before executing lock queries:
```php
// Consolidate duplicate products and sort numerically
$productIds = array_keys($consolidatedItems);
sort($productIds, SORT_NUMERIC);

// Acquire locks in deterministic ascending sequence
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$lockSql = "SELECT * FROM products WHERE id IN ({$placeholders}) ORDER BY id ASC FOR UPDATE";
$lockStmt = $pdo->prepare($lockSql);
$lockStmt->execute($productIds);
```

### 2. Pessimistic Row-Level Locking (`SELECT ... FOR UPDATE`)
By querying the target product records with `FOR UPDATE` inside an open transaction (`$pdo->beginTransaction()`), MySQL's InnoDB engine places an exclusive row-level lock on the affected records. Any competing checkout or inventory adjustment thread attempting to read or modify those exact rows must wait until the current transaction commits or rolls back.

### 3. Strict Pre-Commit Stock Validation
With exclusive locks acquired, the engine inspects live `stock_quantity`:
```php
if ($availableStock <= 0) {
    throw new InvalidArgumentException("Product '{$prod['name']}' is out of stock. Checkout halted.");
}
if ($requestedQty > $availableStock) {
    throw new InvalidArgumentException("Insufficient stock for '{$prod['name']}'. Requested: {$requestedQty}, Available: {$availableStock}.");
}
```
If any item fails validation, `$pdo->rollBack()` releases all locks immediately and returns a descriptive HTTP 422 JSON error to the client without modifying database state.

### 4. Double-Entry Immutable Audit Log
Every stock increment or deduction automatically triggers two immutable audit entries:
1. **Inventory Track:** Records the exact `quantity_delta` (`-qty` for sales, `+qty` for inbound shipments), unit cost valuation, and the resulting `stock_balance_after`.
2. **Financial Track:** Records recognized gross revenue (`total_amount`) linked to the invoice entity.

---

## Core Architectural Modules

### Module 1: Docker Environment & Database Schema
- **Container Infrastructure:** Official PHP 8.2 Apache base image configured with `pdo_mysql`, `bcmath`, `opcache`, `zip`, and `mod_rewrite`.
- **Relational Storage:** MySQL 8.0 configured with `InnoDB`, `utf8mb4_unicode_ci` collation, and a persistent Docker volume (`db_data`).
- **Core Entities:** `roles`, `users`, `products`, `stock_inbound`, `invoices`, `invoice_items`, and `audit_ledger`.

### Module 2: Core Backend Foundation & Role-Based Auth
- **Database Manager (`src/Config/Database.php`)**: Thread-safe singleton PDO connection with disabled emulated prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`) and strict error handling (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`).
- **Authentication & Route Guards (`src/Middleware/AuthMiddleware.php` & `includes/auth.php`)**: Hardened session handling (`HttpOnly`, `SameSite=Lax`, strict mode) with bcrypt credential validation and route guards:
  ```php
  requireRole(['Admin']);                  // Global admin routes
  requireRole(['Admin', 'Store Manager']); // Inventory management routes
  requireRole(['Admin', 'Sales Operator']);// Point-of-sale routes
  ```
- **Login Portal (`public/login.php` & `public/logout.php`)**: Responsive authentication UI with CSRF protection, session fixation prevention (`session_regenerate_id(true)`), and quick-fill test accounts.

### Module 3: Inventory Management & Stock-In Module
- **Product Catalog Service (`src/Services/ProductService.php`)**: Complete CRUD catalog management with SKU uniqueness validation, gross margin calculation, and threshold monitoring (`stock_quantity <= reorder_level`).
- **Inbound Orders Service (`src/Services/InventoryService.php`)**: Concurrency-safe atomic stock receiving using row locks. Increments product stock, logs supplier receipts, and updates the double-entry audit ledger.
- **Inventory UI (`public/inventory/`)**: Clean dashboard with search, tabbed low-stock alert views, modal-based product authoring, and a live inbound stock impact calculator.

### Module 4: Sales Invoicing & Atomic Concurrency Engine
- **Invoicing Service (`src/Services/InvoiceService.php`)**: High-concurrency sales processing engine with sorted row locks, zero-stock blocking, line item insertion, and automated sequential invoice generation (`INV-YYYY-XXXX`).
- **POS Checkout Portal (`public/sales/index.php`)**: Asynchronous checkout terminal with dynamic row additions, live subtotal/tax/discount calculations, client-side stock validation, and instant receipt redirection.
- **Receipt & Invoice Views (`public/sales/invoice.php` & `invoices.php`)**: Searchable invoice transaction register and printer-ready enterprise invoice view (`@media print` supported).

### Module 5: Global Audit Ledger & Administrative Reversals
- **Ledger & Reversal Service (`src/Services/LedgerService.php`)**:
  - Paginated, filterable audit log tracking all inventory and financial state transitions.
  - **Transactional Reversals:** Atomic reversal of settled invoices (marking invoice as `refunded`, restoring stock quantities, and generating compensating `REFUND` ledger entries).
  - Inbound shipment voiding with stock validation preventing negative balances.
- **Staff User Service (`src/Services/UserService.php`)**: Administrator console to provision new staff credentials, assign roles, reset passwords, and toggle account statuses (`active`, `suspended`, `inactive`) with self-lockout prevention.
- **Admin UI (`public/admin/`)**: Comprehensive audit ledger console (`audit-ledger.php`) and staff management dashboard (`users.php`).

---

## API Endpoints Reference

All API endpoints require active session authentication and verify CSRF security tokens on `POST` requests.

| Method | Endpoint | Allowed Roles | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/inventory/api.php?action=get_product&id=:id` | `Admin`, `Store Manager` | Fetch individual product specifications and stock levels. |
| `GET` | `/inventory/api.php?action=list_products` | `Admin`, `Store Manager` | Retrieve catalog list with search and low-stock filters. |
| `POST` | `/inventory/api.php` (`action: create_product`) | `Admin`, `Store Manager` | Create a new SKU in the catalog. |
| `POST` | `/inventory/api.php` (`action: update_product`) | `Admin`, `Store Manager` | Update product details, prices, or reorder levels. |
| `POST` | `/inventory/api.php` (`action: stock_in`) | `Admin`, `Store Manager` | Process inbound purchase order and increment stock. |
| `GET` | `/sales/api.php?action=catalog` | `Admin`, `Sales Operator` | Retrieve active products for POS cart selection. |
| `POST` | `/sales/api.php` (`action: checkout`) | `Admin`, `Sales Operator` | Atomic checkout with row locking, invoice creation, and stock decrement. |
| `POST` | `/admin/api.php` (`action: reverse_invoice`) | `Admin` | ACID reversal of a sales invoice with stock restoration. |
| `POST` | `/admin/api.php` (`action: reverse_inbound`) | `Admin` | Void an inbound shipment and deduct stock. |
| `POST` | `/admin/api.php` (`action: create_user`) | `Admin` | Provision new staff credentials with bcrypt hashing. |
| `POST` | `/admin/api.php` (`action: update_user`) | `Admin` | Edit staff role, email, name, or password. |
| `POST` | `/admin/api.php` (`action: toggle_user_status`) | `Admin` | Suspend or activate a staff account. |

---

## Database Schema Overview

```
 ┌──────────────┐       ┌──────────────┐       ┌─────────────────┐
 │    roles     │◀──────│    users     │◀──────│  stock_inbound  │
 └──────────────┘ 1   * └──────────────┘ 1   * └─────────────────┘
                               │                         │ *
                               │ 1                       │
                               ▼ *                       ▼ 1
                        ┌──────────────┐       ┌─────────────────┐
                        │   invoices   │       │    products     │
                        └──────────────┘       └─────────────────┘
                               │ 1                       │ 1
                               ▼ *                       ▼ *
                        ┌──────────────┐       ┌─────────────────┐
                        │invoice_items │──────▶│  audit_ledger   │
                        └──────────────┘ *   1 └─────────────────┘
```

- **`roles`**: System permission tiers (`Admin`, `Store Manager`, `Sales Operator`).
- **`users`**: Staff accounts linked to roles with bcrypt password hashes and account statuses.
- **`products`**: Master product inventory with SKU, unit cost, selling price, stock quantity, and reorder levels.
- **`stock_inbound`**: Inbound shipment receipts tracking supplier name, purchase order references, received quantities, and unit costs.
- **`invoices`**: Sales headers containing customer billing details, payment method, subtotals, taxes, discounts, and total net amounts.
- **`invoice_items`**: Line items detailing products, quantities sold, unit prices, and subtotals.
- **`audit_ledger`**: Immutable audit journal capturing inventory movements (`quantity_delta`, `stock_balance_after`) and financial revenue events.

---

## Troubleshooting & Maintenance

### Stopping and Starting the Stack
```bash
# Stop all services
docker compose down

# Start services in the background
docker compose up -d

# View live container logs
docker compose logs -f web
```

### Resetting Database to Seed State
To clear the persistent volume and re-run initial schema and seed scripts:
```bash
docker compose down -v
docker compose up -d --build
```

### Port Conflicts
If port `8080` or `3306` is occupied by another process on your host machine:
1. Inspect running containers: `docker ps`
2. Stop the conflicting service: `docker stop <container-id>`
3. Alternatively, adjust the mapped host ports in `docker-compose.yml` (e.g. `"8081:80"`).
