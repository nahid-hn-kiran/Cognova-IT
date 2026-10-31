<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * Main Application Dashboard & System Overview
 */

require_once __DIR__ . '/../includes/auth.php';

use App\Config\Database;
use App\Services\ProductService;
use App\Services\InvoiceService;

header('Content-Type: text/html; charset=utf-8');

$currentUser = currentUser();
$isLoggedIn = isAuthenticated();

$dbConnected = false;
$dbError = null;
$tableStats = [];
$sampleProducts = [];
$lowStockCount = 0;
$totalInventoryValuation = 0.0;
$salesSummary = ['total_revenue' => 0.0, 'invoice_count' => 0];

try {
    $pdo = Database::getConnection();
    $dbConnected = true;

    // Fetch table stats
    $tables = ['roles', 'users', 'products', 'stock_inbound', 'invoices', 'invoice_items', 'audit_ledger'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `{$table}`");
        $tableStats[$table] = $stmt->fetch()['total'] ?? 0;
    }

    $productService = new ProductService();
    $lowStockCount = $productService->getLowStockCount();

    $invoiceService = new InvoiceService();
    $salesSummary = $invoiceService->getSalesSummary();

    // Inventory Valuation
    $stmtValuation = $pdo->query("SELECT SUM(stock_quantity * unit_cost) AS total_val FROM products WHERE status = 'active'");
    $totalInventoryValuation = (float)($stmtValuation->fetch()['total_val'] ?? 0.0);

    // Fetch sample products
    $sampleProducts = $productService->getAll();

} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$requiredExtensions = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'bcmath' => extension_loaded('bcmath'),
    'opcache' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mini Enterprise Inventory & Ledger</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --surface-alt: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --primary: #3b82f6;
            --border: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .hero-title h1 { font-size: 1.85rem; font-weight: 700; color: #fff; margin-bottom: 0.35rem; }
        .hero-title p { color: var(--text-muted); font-size: 0.95rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--primary);
            color: #fff;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.15s;
            border: none;
        }
        .btn:hover { background: #2563eb; }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #059669; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }
        
        /* Stats Ribbon */
        .stats-ribbon {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
        }
        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
        }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; }
        .card h2 { font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; }
        .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: var(--primary); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .list-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .list-item:last-child { border-bottom: none; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-top: 0.5rem; }
        th, td { text-align: left; padding: 0.65rem 0.85rem; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 600; background: rgba(0,0,0,0.2); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: 6px; font-size: 0.9rem; margin-top: 1rem; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <div class="hero-banner">
        <div class="hero-title">
            <h1>Mini Enterprise Inventory & Ledger Engine</h1>
            <p>Unified Real-Time Inventory Control, Inbound Orders & Immutable Financial Ledgers</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <?php if (hasRole(['Admin', 'Store Manager'])): ?>
                <a href="/inventory/index.php" class="btn">
                    <span>&#9638;</span> Product Catalog
                </a>
                <a href="/inventory/stock-in.php" class="btn btn-success">
                    <span>&#10515;</span> Stock Inbound
                </a>
            <?php else: ?>
                <a href="/login.php" class="btn">Sign In to Manage Inventory</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Ribbon -->
    <div class="stats-ribbon">
        <div class="stat-card">
            <div class="stat-label">Total SKUs in Catalog</div>
            <div class="stat-value"><?= (int)($tableStats['products'] ?? 0) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Inventory Valuation</div>
            <div class="stat-value" style="color: #34d399;">$<?= number_format($totalInventoryValuation, 2) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Invoiced Revenue (Module 4)</div>
            <div class="stat-value" style="color: #60a5fa;">$<?= number_format($salesSummary['total_revenue'], 2) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-value" style="color: <?= $lowStockCount > 0 ? '#f87171' : '#34d399' ?>;">
                <?= $lowStockCount ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Audit Ledger Events</div>
            <div class="stat-value"><?= (int)($tableStats['audit_ledger'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Navigation & Quick Access Modules -->
    <div class="grid">
        <!-- Module 4: Sales POS & Invoicing Card -->
        <div class="card" style="border-color: rgba(16, 185, 129, 0.4);">
            <h2>
                Sales Invoicing & POS (Module 4)
                <span class="badge badge-success">ACID Concurrency</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                Sales Operator checkout terminal with row-level locks (SELECT FOR UPDATE) preventing zero-stock and negative inventory conditions.
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php if (hasRole(['Admin', 'Sales Operator'])): ?>
                    <a href="/sales/index.php" class="btn btn-success" style="font-size: 0.825rem;">
                        <span>+</span> Open POS Terminal
                    </a>
                <?php endif; ?>
                <a href="/sales/invoices.php" class="btn btn-outline" style="font-size: 0.825rem;">
                    Invoices (<?= $salesSummary['invoice_count'] ?>)
                </a>
            </div>
        </div>

        <!-- Module 3: Inventory Management Card -->
        <div class="card" style="border-color: rgba(59, 130, 246, 0.4);">
            <h2>
                Inventory & Stock In (Module 3)
                <span class="badge badge-info">Active</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                Store Manager portal for managing products, tracking low-stock thresholds, and recording inbound supplier shipments.
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="/inventory/index.php" class="btn btn-outline" style="font-size: 0.825rem;">
                    Browse Catalog
                </a>
                <a href="/inventory/index.php?low_stock=1" class="btn btn-outline" style="font-size: 0.825rem; border-color: rgba(239,68,68,0.5); color: #f87171;">
                    Low Stock Filter (<?= $lowStockCount ?>)
                </a>
                <a href="/inventory/stock-in.php" class="btn btn-success" style="font-size: 0.825rem;">
                    Record Stock In
                </a>
            </div>
        </div>

        <!-- Module 5: Audit Ledger & Admin Controls Card -->
        <div class="card" style="border-color: rgba(245, 158, 11, 0.4);">
            <h2>
                Audit Ledger & Admin (Module 5)
                <span class="badge badge-warning">Admin Console</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                Global double-entry audit trail inspection, transactional reversals (refunds/voids), and staff account RBAC provisioning.
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php if (hasRole(['Admin'])): ?>
                    <a href="/admin/audit-ledger.php" class="btn btn-outline" style="font-size: 0.825rem; border-color: rgba(245, 158, 11, 0.5); color: #fbbf24;">
                        Audit Ledger
                    </a>
                    <a href="/admin/users.php" class="btn btn-outline" style="font-size: 0.825rem;">
                        Staff Management
                    </a>
                <?php else: ?>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Admin clearance required</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role Guard Security Card -->
        <div class="card" style="border-color: rgba(99, 102, 241, 0.4);">
            <h2>
                Role Guard RBAC (Module 2)
                <span class="badge badge-info">Security</span>
            </h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                Current clearance: <strong style="color: #60a5fa;"><?= htmlspecialchars($currentUser['role'] ?? 'Guest (Unauthenticated)') ?></strong>
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="/protected.php?role=Admin" class="btn btn-outline" style="font-size: 0.8rem;">Guard: Admin</a>
                <a href="/protected.php?role=Store Manager" class="btn btn-outline" style="font-size: 0.8rem;">Guard: Manager</a>
                <a href="/protected.php?role=Sales Operator" class="btn btn-outline" style="font-size: 0.8rem;">Guard: Operator</a>
            </div>
        </div>

        <!-- Database & Engine Health -->
        <div class="card">
            <h2>
                Engine Diagnostics
                <span class="badge <?= $dbConnected ? 'badge-success' : 'badge-danger' ?>">
                    <?= $dbConnected ? 'PDO Connected' : 'Unavailable' ?>
                </span>
            </h2>
            <div class="list-item">
                <span class="text-muted">Storage Engine</span>
                <span class="mono">MySQL 8.0 (InnoDB)</span>
            </div>
            <div class="list-item">
                <span class="text-muted">PHP Runtime</span>
                <span class="mono"><?= PHP_VERSION ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Inbound Orders</span>
                <span class="mono"><?= (int)($tableStats['stock_inbound'] ?? 0) ?></span>
            </div>
        </div>
    </div>

    <?php if ($dbConnected && !empty($sampleProducts)): ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>Live Inventory Catalog Preview</h2>
            <a href="/inventory/index.php" style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 600;">View Full Catalog &rarr;</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Unit Cost</th>
                    <th>Selling Price</th>
                    <th>In Stock</th>
                    <th>Reorder Lvl</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($sampleProducts, 0, 6) as $p): ?>
                <tr>
                    <td class="mono" style="color: #60a5fa; font-weight: 600;"><?= htmlspecialchars($p['sku']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>$<?= number_format((float)$p['unit_cost'], 2) ?></td>
                    <td>$<?= number_format((float)$p['selling_price'], 2) ?></td>
                    <td>
                        <?php if ($p['is_low_stock']): ?>
                            <span style="color: #f87171; font-weight: 700; background: rgba(239,68,68,0.15); padding: 0.15rem 0.5rem; border-radius: 9999px;">
                                &#9888; <?= (int)$p['stock_quantity'] ?> units
                            </span>
                        <?php else: ?>
                            <span style="color: #34d399; font-weight: 600;">
                                <?= (int)$p['stock_quantity'] ?> units
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$p['reorder_level'] ?></td>
                    <td><span class="badge badge-success"><?= htmlspecialchars($p['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
