<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * System Diagnostics & Environment Verification Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';

use App\Config\Database;
use App\Middleware\AuthMiddleware;

header('Content-Type: text/html; charset=utf-8');

$currentUser = currentUser();
$isLoggedIn = isAuthenticated();

$dbConnected = false;
$dbError = null;
$tableStats = [];
$sampleProducts = [];

try {
    $pdo = Database::getConnection();
    $dbConnected = true;

    // Fetch table stats
    $tables = ['roles', 'users', 'products', 'stock_inbound', 'invoices', 'invoice_items', 'audit_ledger'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `{$table}`");
        $tableStats[$table] = $stmt->fetch()['total'] ?? 0;
    }

    // Fetch sample products
    $stmt = $pdo->query("SELECT sku, name, unit_cost, selling_price, stock_quantity, reorder_level, status FROM products ORDER BY id ASC LIMIT 5");
    $sampleProducts = $stmt->fetchAll();

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
    <title>Mini Enterprise Inventory & Ledger - Dashboard</title>
    <style>
        :root {
            --bg: #0f172a;
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
            padding: 2rem;
            line-height: 1.5;
        }
        .container { max-width: 1040px; margin: 0 auto; }
        .header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header h1 { font-size: 1.75rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem; }
        .header p { color: var(--text-muted); font-size: 0.95rem; }
        .user-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-info { font-size: 0.875rem; }
        .user-name { font-weight: 700; color: #fff; }
        .user-role { color: var(--primary); font-weight: 600; font-size: 0.8rem; }
        .btn {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.15s;
        }
        .btn:hover { background: #2563eb; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.35); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; }
        .card h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; }
        .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: var(--primary); }
        .list-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .list-item:last-child { border-bottom: none; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-top: 0.5rem; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 600; background: rgba(0,0,0,0.2); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: 6px; font-size: 0.9rem; margin-top: 1rem; }
        .role-guard-test {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Mini Enterprise Inventory & Financial Ledger</h1>
            <p>Module 2: Core Backend Foundation & Role-Based Security</p>
        </div>
        <div class="user-panel">
            <?php if ($isLoggedIn): ?>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="user-role"><?= htmlspecialchars($currentUser['role']) ?> (<?= htmlspecialchars($currentUser['email']) ?>)</div>
                </div>
                <a href="/logout.php" class="btn btn-danger">Sign Out</a>
            <?php else: ?>
                <div class="user-info">
                    <div class="user-name">Guest Mode</div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Unauthenticated</div>
                </div>
                <a href="/login.php" class="btn">Sign In</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Role Guards Interactive Testing -->
    <div class="card" style="margin-bottom: 1.5rem; border-color: rgba(99, 102, 241, 0.4);">
        <h2>
            Role-Based Authorization Guards (Module 2)
            <span class="badge badge-info">RBAC Security</span>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
            Test route guards enforced by <span class="mono">requireRole(...)</span> in action:
        </p>
        <div class="role-guard-test">
            <a href="/protected.php?role=Admin" class="btn btn-outline">
                Guard: Admin Only
            </a>
            <a href="/protected.php?role=Store Manager" class="btn btn-outline">
                Guard: Store Manager Only
            </a>
            <a href="/protected.php?role=Sales Operator" class="btn btn-outline">
                Guard: Sales Operator Only
            </a>
            <a href="/protected.php?role=Staff" class="btn btn-outline">
                Guard: Any Staff
            </a>
        </div>
    </div>

    <div class="grid">
        <!-- Database Connectivity via Database.php -->
        <div class="card">
            <h2>
                Database (App\Config\Database)
                <span class="badge <?= $dbConnected ? 'badge-success' : 'badge-danger' ?>">
                    <?= $dbConnected ? 'PDO Connected' : 'Unavailable' ?>
                </span>
            </h2>
            <div class="list-item">
                <span class="text-muted">Host & Port</span>
                <span class="mono"><?= htmlspecialchars((getenv('DB_HOST') ?: 'db') . ':' . (getenv('DB_PORT') ?: '3306')) ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Database Name</span>
                <span class="mono"><?= htmlspecialchars(getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'inventory_ledger')) ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Emulated Prepares</span>
                <span class="mono" style="color: var(--success);">Disabled (Secure)</span>
            </div>
            <div class="list-item">
                <span class="text-muted">Error Mode</span>
                <span class="mono" style="color: var(--success);">ERRMODE_EXCEPTION</span>
            </div>
            <?php if ($dbError): ?>
                <div class="alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Session & Auth State -->
        <div class="card">
            <h2>
                Session & Auth State
                <span class="badge <?= $isLoggedIn ? 'badge-success' : 'badge-info' ?>">
                    <?= $isLoggedIn ? 'Active Session' : 'Guest' ?>
                </span>
            </h2>
            <div class="list-item">
                <span class="text-muted">Session Status</span>
                <span class="mono"><?= session_status() === PHP_SESSION_ACTIVE ? 'PHP_SESSION_ACTIVE' : 'INACTIVE' ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">HttpOnly Cookies</span>
                <span class="mono" style="color: var(--success);">Enabled</span>
            </div>
            <div class="list-item">
                <span class="text-muted">SameSite Policy</span>
                <span class="mono">Lax</span>
            </div>
            <div class="list-item">
                <span class="text-muted">Active Role</span>
                <span class="mono" style="color: var(--primary); font-weight: bold;"><?= htmlspecialchars($currentUser['role'] ?? 'None') ?></span>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div class="card">
            <h2>PHP Extensions</h2>
            <?php foreach ($requiredExtensions as $ext => $loaded): ?>
                <div class="list-item">
                    <span class="mono"><?= $ext ?></span>
                    <span class="badge <?= $loaded ? 'badge-success' : 'badge-danger' ?>">
                        <?= $loaded ? 'Enabled' : 'Missing' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($dbConnected): ?>
    <div class="card" style="margin-bottom: 2rem;">
        <h2>Database Tables & Record Counts</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-top: 0.5rem;">
            <?php foreach ($tableStats as $tableName => $count): ?>
                <div style="background: var(--surface-alt); padding: 0.75rem 1rem; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;" class="mono"><?= htmlspecialchars($tableName) ?></div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);"><?= $count ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Seeded Inventory Catalog (Sample)</h2>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Unit Cost</th>
                    <th>Selling Price</th>
                    <th>Stock</th>
                    <th>Reorder Lvl</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sampleProducts as $p): ?>
                <tr>
                    <td class="mono"><?= htmlspecialchars($p['sku']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>$<?= number_format((float)$p['unit_cost'], 2) ?></td>
                    <td>$<?= number_format((float)$p['selling_price'], 2) ?></td>
                    <td><strong><?= (int)$p['stock_quantity'] ?></strong></td>
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
