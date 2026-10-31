<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * System Diagnostics & Environment Verification Dashboard
 */

header('Content-Type: text/html; charset=utf-8');

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'inventory_ledger';
$dbUser = getenv('DB_USERNAME') ?: 'app_user';
$dbPass = getenv('DB_PASSWORD') ?: 'app_secret_password';

$dbConnected = false;
$dbError = null;
$tableStats = [];
$sampleProducts = [];

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
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
    <title>Mini Enterprise Inventory & Ledger - Status</title>
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
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
        .header h1 { font-size: 1.75rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .header p { color: var(--text-muted); font-size: 0.95rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; }
        .card h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; }
        .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .list-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .list-item:last-child { border-bottom: none; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-top: 0.5rem; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 600; background: rgba(0,0,0,0.2); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: 6px; font-size: 0.9rem; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Mini Enterprise Inventory & Financial Ledger Engine</h1>
        <p>Docker Environment & MySQL Database Verification</p>
    </div>

    <div class="grid">
        <!-- PHP Runtime -->
        <div class="card">
            <h2>PHP 8.2+ Runtime <span class="badge badge-success">Active</span></h2>
            <div class="list-item">
                <span class="text-muted">PHP Version</span>
                <span class="mono"><?= PHP_VERSION ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Server Software</span>
                <span class="mono"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Document Root</span>
                <span class="mono"><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '') ?></span>
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

        <!-- Database Connectivity -->
        <div class="card">
            <h2>
                Database (MySQL)
                <span class="badge <?= $dbConnected ? 'badge-success' : 'badge-danger' ?>">
                    <?= $dbConnected ? 'Connected' : 'Unavailable' ?>
                </span>
            </h2>
            <div class="list-item">
                <span class="text-muted">Host:Port</span>
                <span class="mono"><?= htmlspecialchars($dbHost . ':' . $dbPort) ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">Database</span>
                <span class="mono"><?= htmlspecialchars($dbName) ?></span>
            </div>
            <div class="list-item">
                <span class="text-muted">User</span>
                <span class="mono"><?= htmlspecialchars($dbUser) ?></span>
            </div>
            <?php if ($dbError): ?>
                <div class="alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
                </div>
            <?php endif; ?>
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

