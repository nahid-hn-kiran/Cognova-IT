<?php
/**
 * Invoices Ledger & Transaction History View
 *
 * Restricted to: Sales Operator, Admin, Store Manager
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\InvoiceService;

requireRole(['Admin', 'Sales Operator', 'Store Manager']);

$invoiceService = new InvoiceService();

$query = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$filters = [
    'q' => $query,
    'status' => $status,
];

$invoices = $invoiceService->getAllInvoices($filters);
$summary = $invoiceService->getSalesSummary();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Invoices Ledger - Cognova IT</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --surface-alt: #334155;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .page-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .page-title h1 {
            font-size: 1.65rem;
            font-weight: 700;
            color: #fff;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--primary);
            color: #fff;
            padding: 0.6rem 1.15rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }
        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.8rem;
        }

        /* Stats Ribbon */
        .stats-grid {
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
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.4rem;
        }
        .stat-value {
            font-size: 1.65rem;
            font-weight: 700;
            color: #fff;
        }

        /* Controls */
        .controls-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            max-width: 450px;
        }
        .search-input {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.55rem 0.85rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        .search-input:focus { outline: none; border-color: var(--primary); }

        /* Table */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            text-align: left;
        }
        thead th {
            background: #111827;
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(255, 255, 255, 0.02); }
        .mono { font-family: ui-monospace, monospace; }
        .badge-inv {
            font-family: ui-monospace, monospace;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .badge-status {
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Sales Invoices & Revenue Ledger</h1>
            <p>Historical audit of customer sales invoices, settled revenue, and itemized receipts.</p>
        </div>
        <div>
            <?php if (hasRole(['Admin', 'Sales Operator'])): ?>
                <a href="/sales/index.php" class="btn btn-success">
                    <span>+</span> New Sales Checkout
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Revenue Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Invoiced Volume</div>
            <div class="stat-value" style="color: #34d399;">$<?= number_format($summary['total_revenue'], 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Processed Invoices</div>
            <div class="stat-value"><?= $summary['invoice_count'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Average Order Value</div>
            <div class="stat-value" style="color: #60a5fa;">
                $<?= $summary['invoice_count'] > 0 ? number_format($summary['total_revenue'] / $summary['invoice_count'], 2) : '0.00' ?>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls-card">
        <form class="search-form" method="GET" action="/sales/invoices.php">
            <input 
                type="text" 
                name="q" 
                class="search-input" 
                placeholder="Search by Invoice # or Customer Name..." 
                value="<?= htmlspecialchars($query) ?>"
            >
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            <?php if ($query): ?>
                <a href="/sales/invoices.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer Name</th>
                    <th>Items</th>
                    <th>Subtotal</th>
                    <th>Net Total</th>
                    <th>Payment Method</th>
                    <th>Operator</th>
                    <th>Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No sales invoices found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td>
                                <span class="badge-inv"><?= htmlspecialchars($inv['invoice_number']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($inv['customer_name']) ?></div>
                                <?php if ($inv['customer_email']): ?>
                                    <div style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($inv['customer_email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$inv['item_count'] ?> items</td>
                            <td class="mono">$<?= number_format((float)$inv['subtotal'], 2) ?></td>
                            <td>
                                <strong class="mono" style="color: #34d399;">$<?= number_format((float)$inv['total_amount'], 2) ?></strong>
                            </td>
                            <td><?= ucwords(str_replace('_', ' ', $inv['payment_method'])) ?></td>
                            <td><?= htmlspecialchars($inv['operator_name']) ?></td>
                            <td class="mono" style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= date('M d, Y H:i', strtotime($inv['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="/sales/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-outline btn-sm">
                                    View Receipt &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

