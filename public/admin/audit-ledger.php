<?php
/**
 * Global Audit Ledger & Reversal Controls
 *
 * Restricted strictly to: Admin
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\LedgerService;

requireRole(['Admin']);

$ledgerService = new LedgerService();
$currentUser = currentUser();
$csrfToken = csrfToken();

$txType = $_GET['transaction_type'] ?? 'ALL';
$category = $_GET['entry_category'] ?? 'ALL';
$query = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'transaction_type' => $txType,
    'entry_category' => $category,
    'q' => $query,
];

$ledgerData = $ledgerService->getAuditLogs($filters, $page, 30);
$metrics = $ledgerService->getMetrics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Audit Ledger - Admin Console</title>
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
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #8b5cf6;
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
            max-width: 1300px;
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
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.775rem;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.1rem;
        }
        .metric-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }

        /* Controls */
        .controls-bar {
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
        .filter-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            flex: 1;
        }
        .form-select, .search-input {
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.55rem 0.85rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .form-select:focus, .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Ledger Table */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
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
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:hover { background: rgba(255, 255, 255, 0.02); }
        .mono { font-family: ui-monospace, monospace; }

        /* Badges */
        .tx-badge {
            font-size: 0.725rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-block;
        }
        .tx-STOCK_IN { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .tx-STOCK_OUT { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .tx-SALE { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .tx-REFUND { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .tx-ADJUSTMENT { background: rgba(139, 92, 246, 0.15); color: #c4b5fd; }

        .cat-badge {
            font-size: 0.675rem;
            font-weight: 600;
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
        }
        .cat-INVENTORY { background: #334155; color: #cbd5e1; }
        .cat-FINANCIAL { background: #1e3a8a; color: #93c5fd; }

        .delta-pos { color: #34d399; font-weight: 700; }
        .delta-neg { color: #f87171; font-weight: 700; }
        .delta-zero { color: var(--text-muted); }

        /* Pagination */
        .pagination-bar {
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #111827;
            border-top: 1px solid var(--border);
            font-size: 0.85rem;
        }

        /* Reversal Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: var(--surface);
            border: 1px solid var(--danger);
            border-radius: 10px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            padding: 1.75rem;
        }
        .modal-card h3 {
            color: #f87171;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        .modal-card p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Global Audit Ledger & Administrative Oversight</h1>
            <p>Immutable double-entry log tracking all inventory state transitions, financial recognized revenues, and reversal controls.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="/admin/users.php" class="btn btn-outline">
                <span>&#128101;</span> User & Role Management
            </a>
            <a href="/sales/invoices.php" class="btn btn-outline">
                <span>&#128196;</span> Invoices
            </a>
        </div>
    </div>

    <!-- Aggregate Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total Immutable Events</div>
            <div class="metric-value"><?= (int)($metrics['total_events'] ?? 0) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Inbound Stock Shipments</div>
            <div class="metric-value" style="color: #34d399;"><?= (int)($metrics['inbound_events'] ?? 0) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Sales Checkout Events</div>
            <div class="metric-value" style="color: #60a5fa;"><?= (int)($metrics['sale_events'] ?? 0) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Reversals & Refunds</div>
            <div class="metric-value" style="color: #fbbf24;"><?= (int)($metrics['refund_events'] ?? 0) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Recognized Revenue</div>
            <div class="metric-value" style="color: #34d399;">$<?= number_format((float)($metrics['gross_revenue_recognized'] ?? 0), 2) ?></div>
        </div>
    </div>

    <!-- Controls & Filters -->
    <div class="controls-bar">
        <form class="filter-form" method="GET" action="/admin/audit-ledger.php">
            <select name="transaction_type" class="form-select" onchange="this.form.submit()">
                <option value="ALL" <?= $txType === 'ALL' ? 'selected' : '' ?>>All Actions (Any Type)</option>
                <option value="STOCK_IN" <?= $txType === 'STOCK_IN' ? 'selected' : '' ?>>STOCK_IN (Inbound)</option>
                <option value="SALE" <?= $txType === 'SALE' ? 'selected' : '' ?>>SALE (Checkout)</option>
                <option value="REFUND" <?= $txType === 'REFUND' ? 'selected' : '' ?>>REFUND (Reversals)</option>
                <option value="ADJUSTMENT" <?= $txType === 'ADJUSTMENT' ? 'selected' : '' ?>>ADJUSTMENT (Manual/Void)</option>
            </select>

            <select name="entry_category" class="form-select" onchange="this.form.submit()">
                <option value="ALL" <?= $category === 'ALL' ? 'selected' : '' ?>>All Categories</option>
                <option value="INVENTORY" <?= $category === 'INVENTORY' ? 'selected' : '' ?>>INVENTORY</option>
                <option value="FINANCIAL" <?= $category === 'FINANCIAL' ? 'selected' : '' ?>>FINANCIAL</option>
            </select>

            <input 
                type="text" 
                name="q" 
                class="search-input" 
                placeholder="Search notes, SKU, or user..." 
                value="<?= htmlspecialchars($query) ?>"
                style="min-width: 260px;"
            >

            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            <?php if ($txType !== 'ALL' || $category !== 'ALL' || $query): ?>
                <a href="/admin/audit-ledger.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Ledger Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th># ID</th>
                    <th>Action</th>
                    <th>Category</th>
                    <th>Entity</th>
                    <th>Product / SKU</th>
                    <th>Qty Delta</th>
                    <th>Stock After</th>
                    <th>Financial Amt</th>
                    <th>Recorded By</th>
                    <th>Notes & Reason</th>
                    <th>Timestamp</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerData['items'])): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No audit ledger entries match the criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ledgerData['items'] as $log): ?>
                        <tr>
                            <td class="mono" style="color: var(--text-muted); font-size: 0.775rem;">
                                <?= $log['id'] ?>
                            </td>
                            <td>
                                <span class="tx-badge tx-<?= htmlspecialchars($log['transaction_type']) ?>">
                                    <?= htmlspecialchars($log['transaction_type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="cat-badge cat-<?= htmlspecialchars($log['entry_category']) ?>">
                                    <?= htmlspecialchars($log['entry_category']) ?>
                                </span>
                            </td>
                            <td class="mono" style="font-size: 0.8rem;">
                                <?= htmlspecialchars($log['entity_type']) ?> #<?= $log['entity_id'] ?>
                            </td>
                            <td>
                                <?php if ($log['product_sku']): ?>
                                    <span class="mono" style="color: #60a5fa; font-weight: 600;"><?= htmlspecialchars($log['product_sku']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono">
                                <?php if ($log['quantity_delta'] > 0): ?>
                                    <span class="delta-pos">+<?= (int)$log['quantity_delta'] ?></span>
                                <?php elseif ($log['quantity_delta'] < 0): ?>
                                    <span class="delta-neg"><?= (int)$log['quantity_delta'] ?></span>
                                <?php else: ?>
                                    <span class="delta-zero">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono">
                                <?= $log['stock_balance_after'] !== null ? "{$log['stock_balance_after']} units" : '—' ?>
                            </td>
                            <td class="mono" style="font-weight: 600;">
                                <?php if ((float)$log['total_amount'] > 0): ?>
                                    $<?= number_format((float)$log['total_amount'], 2) ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #fff; font-size: 0.825rem;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                <div style="font-size: 0.725rem; color: var(--text-muted);"><?= htmlspecialchars($log['user_role'] ?? 'Automated') ?></div>
                            </td>
                            <td style="max-width: 250px; font-size: 0.8rem; color: #cbd5e1;">
                                <?= htmlspecialchars($log['notes']) ?>
                            </td>
                            <td class="mono" style="font-size: 0.775rem; color: var(--text-muted);">
                                <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($log['entity_type'] === 'invoices' && $log['transaction_type'] === 'SALE'): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm" 
                                        style="border-color: rgba(245, 158, 11, 0.4); color: #fbbf24;"
                                        onclick="openReversalModal('invoice', <?= $log['entity_id'] ?>, 'Invoice #<?= $log['entity_id'] ?>')"
                                    >
                                        Reverse Sale
                                    </button>
                                <?php elseif ($log['entity_type'] === 'stock_inbound' && $log['transaction_type'] === 'STOCK_IN'): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm" 
                                        style="border-color: rgba(239, 68, 68, 0.4); color: #f87171;"
                                        onclick="openReversalModal('inbound', <?= $log['entity_id'] ?>, 'Inbound #<?= $log['entity_id'] ?>')"
                                    >
                                        Reverse Stock
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.75rem;">Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination-bar">
            <div>
                Showing page <strong><?= $ledgerData['page'] ?></strong> of <strong><?= max(1, $ledgerData['total_pages']) ?></strong> (<?= $ledgerData['total'] ?> total events)
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <?php if ($ledgerData['page'] > 1): ?>
                    <a href="?page=<?= $ledgerData['page'] - 1 ?>&transaction_type=<?= urlencode($txType) ?>&entry_category=<?= urlencode($category) ?>&q=<?= urlencode($query) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($ledgerData['page'] < $ledgerData['total_pages']): ?>
                    <a href="?page=<?= $ledgerData['page'] + 1 ?>&transaction_type=<?= urlencode($txType) ?>&entry_category=<?= urlencode($category) ?>&q=<?= urlencode($query) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Transactional Reversal -->
<div class="modal-overlay" id="reversalModal">
    <div class="modal-card">
        <h3>&#9888; Authorize Transactional Reversal</h3>
        <p id="reversalModalDesc">This action executes an ACID transaction restoring product stock and logging a compensating REFUND or ADJUSTMENT audit entry.</p>

        <form id="reversalForm" onsubmit="handleReversalSubmit(event)">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="entity_type" id="revEntityType" value="">
            <input type="hidden" name="entity_id" id="revEntityId" value="">

            <div style="margin-bottom: 1.25rem;">
                <label for="revReason" style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem;">
                    Reversal Justification Reason *
                </label>
                <textarea 
                    id="revReason" 
                    name="reason" 
                    class="search-input" 
                    style="width: 100%; min-height: 80px;" 
                    placeholder="e.g. Customer returned items / Data entry correction by store manager"
                    required
                ></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline" onclick="closeReversalModal()">Cancel</button>
                <button type="submit" class="btn btn-danger" id="confirmRevBtn">Execute Reversal</button>
            </div>
        </form>
    </div>
</div>

<script>
const revModal = document.getElementById('reversalModal');
const revForm = document.getElementById('reversalForm');
const revEntityType = document.getElementById('revEntityType');
const revEntityId = document.getElementById('revEntityId');
const revModalDesc = document.getElementById('reversalModalDesc');
const confirmRevBtn = document.getElementById('confirmRevBtn');

function openReversalModal(type, id, label) {
    revEntityType.value = type;
    revEntityId.value = id;
    document.getElementById('revReason').value = '';

    if (type === 'invoice') {
        revModalDesc.textContent = `Reversing ${label} will mark the invoice as Refunded, increment product stock back to inventory, and log a compensating REFUND ledger entry.`;
    } else {
        revModalDesc.textContent = `Reversing ${label} will deduct the inbound quantity from inventory and log an ADJUSTMENT ledger entry.`;
    }

    revModal.classList.add('active');
}

function closeReversalModal() {
    revModal.classList.remove('active');
}

window.addEventListener('click', (e) => {
    if (e.target === revModal) closeReversalModal();
});

async function handleReversalSubmit(e) {
    e.preventDefault();
    confirmRevBtn.disabled = true;
    confirmRevBtn.textContent = 'Processing Reversal...';

    const type = revEntityType.value;
    const id = parseInt(revEntityId.value, 10);
    const reason = document.getElementById('revReason').value;
    const csrf = document.getElementById('csrfToken').value;

    const payload = {
        action: type === 'invoice' ? 'reverse_invoice' : 'reverse_inbound',
        csrf_token: csrf,
        reason: reason
    };

    if (type === 'invoice') {
        payload.invoice_id = id;
    } else {
        payload.inbound_id = id;
    }

    try {
        const response = await fetch('/admin/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message || 'Reversal processed successfully.');
            window.location.reload();
        } else {
            alert('Reversal failed: ' + (data.message || 'Unknown error'));
            confirmRevBtn.disabled = false;
            confirmRevBtn.textContent = 'Execute Reversal';
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        confirmRevBtn.disabled = false;
        confirmRevBtn.textContent = 'Execute Reversal';
    }
}
</script>

</body>
</html>

