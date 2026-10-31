# Mini Enterprise Inventory & Financial Ledger Engine

A containerized PHP 8.2 & MySQL 8.0 enterprise inventory management and immutable financial ledger engine.

## Quick Start

### 1. Start Docker Containers
```bash
docker compose up -d --build
```

### 2. Verify Database & Application
Open your browser and navigate to:
```
http://localhost:8080
```

The system automatically initializes `database/schema.sql` and `database/seed.sql` on first launch.

### 3. Default Seed Credentials

All test users are seeded with the password: `Password123!`

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| **Admin** | `admin@congova.local` | `Password123!` | System configuration, audit ledger oversight, user management |
| **Store Manager** | `manager@congova.local` | `Password123!` | Inbound stock receiving, catalog & inventory thresholds |
| **Sales Operator** | `operator@congova.local` | `Password123!` | Sales order entry, invoice creation, item checkout |

---

## Architecture Overview

- **Web Server**: Apache running PHP 8.2 with `pdo_mysql`, `bcmath`, `opcache`, and `mod_rewrite` enabled. Document root configured to `/var/www/html/public`.
- **Database**: MySQL 8.0 with InnoDB storage engine, persistent Docker volume (`db_data`), healthcheck monitoring, and UTF-8 (`utf8mb4_unicode_ci`) collation.
- **Relational Tables**:
  - `roles`: RBAC roles (`Admin`, `Store Manager`, `Sales Operator`).
  - `users`: Staff credentials with role foreign keys and hashed passwords.
  - `products`: Product catalog with SKUs, costs, prices, stock levels, and reorder levels.
  - `stock_inbound`: Supplier receipt logs and purchase order tracking.
  - `invoices`: Sales transactions with financial subtotals, tax, discounts, and payment methods.
  - `invoice_items`: Invoice line items linked to products and invoices.
  - `audit_ledger`: Double-entry audit ledger recording inventory delta changes and revenue events.
