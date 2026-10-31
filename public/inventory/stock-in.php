<?php
/**
 * Inbound Stock Receiving Portal (Stock In)
 *
 * Restricted to: Store Manager, Admin
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\ProductService;
use App\Services\InventoryService;

// Enforce role guard: Admin or Store Manager
requireRole(['Admin', 'Store Manager']);

$productService = new ProductService();
$inventoryService = new InventoryService();
$currentUser = currentUser();
$csrfToken = csrfToken();

$products = $productService->getAll(['status' => 'active']);
$preselectedProductId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$successMessage = null;
$errorMessage = null;

// Handle direct standard form POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($submittedCsrf)) {
        $errorMessage = 'Invalid or expired security token (CSRF). Please retry.';
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $batchNo = trim($_POST['batch_no'] ?? '');
        $unitCostInput = $_POST['unit_cost'] ?? '';
        $unitCost = ($unitCostInput !== '') ? (float)$unitCostInput : null;
        $notes = trim($_POST['notes'] ?? '');

        try {
            $result = $inventoryService->recordStockIn(
                $productId,
                $quantity,
                $referenceNo,
                $notes,
                $unitCost,
                $supplierName,
                $batchNo,
                $currentUser['id']
            );
            $successMessage = $result['message'];
            // Reset preselected product or update
            $preselectedProductId = $productId;
            // Refresh product list with updated counts
            $products = $productService->getAll(['status' => 'active']);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$recentInbounds = $inventoryService->getInboundHistory(15);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Stock Orders - Congova IT</title>
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
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #059669; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .layout-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.7rem 0.85rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        /* Summary Preview Card */
        .preview-box {
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        .preview-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.875rem;
        }
        .preview-row:last-child {
            border-bottom: none;
            padding-top: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            color: #34d399;
        }
        .mono {
            font-family: ui-monospace, monospace;
        }

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
        .badge-ref {
            font-family: ui-monospace, monospace;
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.775rem;
        }
        .badge-qty {
            font-weight: 700;
            color: #34d399;
            background: rgba(16, 185, 129, 0.15);
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.775rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Inbound Stock Receiving (Stock In)</h1>
            <p>Log incoming purchase orders and shipments. Automatically increments stock levels and writes immutable audit entries.</p>
        </div>
        <a href="/inventory/index.php" class="btn btn-outline">
            &larr; Back to Catalog
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <span style="font-size: 1.25rem;">&#10003;</span>
            <div><?= htmlspecialchars($successMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <span style="font-size: 1.25rem;">&#9888;</span>
            <div><?= htmlspecialchars($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <div class="layout-grid">
        <!-- Stock In Form -->
        <div class="card">
            <div class="card-title">
                <span>Receive Inventory Shipment</span>
                <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">Operator: <?= htmlspecialchars($currentUser['name']) ?></span>
            </div>

            <form action="/inventory/stock-in.php" method="POST" id="stockInForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="productSelect">Select Product Catalog SKU *</label>
                    <select name="product_id" id="productSelect" class="form-control" required onchange="handleProductChange()">
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option 
                                value="<?= $p['id'] ?>"
                                data-cost="<?= $p['unit_cost'] ?>"
                                data-stock="<?= $p['stock_quantity'] ?>"
                                data-sku="<?= htmlspecialchars($p['sku']) ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                <?= ($p['id'] == $preselectedProductId) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($p['sku']) ?> - <?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['stock_quantity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="quantityInput">Quantity Added *</label>
                        <input 
                            type="number" 
                            name="quantity" 
                            id="quantityInput" 
                            class="form-control" 
                            min="1" 
                            required 
                            placeholder="e.g. 25"
                            oninput="updateCalculation()"
                        >
                    </div>

                    <div class="form-group">
                        <label for="unitCostInput">Shipment Unit Cost (USD)</label>
                        <input 
                            type="number" 
                            step="0.0001" 
                            min="0" 
                            name="unit_cost" 
                            id="unitCostInput" 
                            class="form-control" 
                            placeholder="Defaults to standard cost"
                            oninput="updateCalculation()"
                        >
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="referenceInput">Reference / PO Number</label>
                        <input 
                            type="text" 
                            name="reference_no" 
                            id="referenceInput" 
                            class="form-control" 
                            placeholder="Auto-generated if empty"
                            value="PO-<?= date('Y') ?>-<?= strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="supplierInput">Supplier / Vendor Name</label>
                        <input 
                            type="text" 
                            name="supplier_name" 
                            id="supplierInput" 
                            class="form-control" 
                            placeholder="e.g. Global Tech Distributors"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="batchInput">Batch / Lot Tracking # (Optional)</label>
                    <input 
                        type="text" 
                        name="batch_no" 
                        id="batchInput" 
                        class="form-control" 
                        placeholder="e.g. BATCH-2026-OCT-01"
                    >
                </div>

                <div class="form-group">
                    <label for="notesInput">Notes / Inspection Comments</label>
                    <textarea 
                        name="notes" 
                        id="notesInput" 
                        class="form-control" 
                        rows="2" 
                        placeholder="e.g. Received in good condition at warehouse bay 3"
                    ></textarea>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%; justify-content: center; padding: 0.85rem;">
                    <span>&#10515;</span> Commit Stock Inbound Order
                </button>
            </form>
        </div>

        <!-- Real-Time Calculation & Verification Preview -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div class="card-title">
                    <span>Stock Impact Verification</span>
                    <span style="color: var(--success); font-size: 0.8rem; font-weight: 600;">Double-Entry Ledger Verified</span>
                </div>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
                    Preview the balance increment and financial valuation before submitting this shipment.
                </p>

                <div class="preview-box">
                    <div class="preview-row">
                        <span style="color: var(--text-muted);">Target SKU</span>
                        <span class="mono" id="previewSku">--</span>
                    </div>
                    <div class="preview-row">
                        <span style="color: var(--text-muted);">Current On-Hand Balance</span>
                        <span id="previewCurrentStock">0 units</span>
                    </div>
                    <div class="preview-row">
                        <span style="color: var(--text-muted);">Inbound Quantity</span>
                        <span style="color: #60a5fa;" id="previewQuantity">+0 units</span>
                    </div>
                    <div class="preview-row">
                        <span style="color: var(--text-muted);">Unit Valuation</span>
                        <span id="previewUnitCost">$0.00</span>
                    </div>
                    <div class="preview-row">
                        <span style="color: var(--text-muted);">Total Inbound Valuation</span>
                        <span id="previewTotalValuation" style="color: #facc15;">$0.00</span>
                    </div>
                    <div class="preview-row">
                        <span>New On-Hand Stock</span>
                        <span id="previewNewStock">0 units</span>
                    </div>
                </div>
            </div>

            <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 1rem; margin-top: 1.5rem;">
                <div style="font-size: 0.8rem; color: #93c5fd; font-weight: 600; margin-bottom: 0.25rem;">
                    &#9432; Ledger Guarantee
                </div>
                <div style="font-size: 0.775rem; color: var(--text-muted);">
                    When committed, the engine executes an atomic database transaction locking the product row, updating inventory count, and writing an immutable audit record to <span class="mono">audit_ledger</span>.
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Inbound Shipments Table -->
    <div class="table-card">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; color: #fff;">Recent Inbound Shipments (Stock-In History)</h3>
            <span style="color: var(--text-muted); font-size: 0.8rem;">Showing last <?= count($recentInbounds) ?> shipments</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Ref Number</th>
                    <th>Product Received</th>
                    <th>Supplier</th>
                    <th>Quantity</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                    <th>Receiver</th>
                    <th>Received At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentInbounds)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No stock inbound orders recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentInbounds as $inb): ?>
                        <tr>
                            <td>
                                <span class="badge-ref"><?= htmlspecialchars($inb['reference_no']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($inb['product_name']) ?></div>
                                <div class="mono" style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($inb['product_sku']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($inb['supplier_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge-qty">+<?= (int)$inb['quantity'] ?></span>
                            </td>
                            <td>$<?= number_format((float)$inb['unit_cost'], 2) ?></td>
                            <td><strong>$<?= number_format((float)$inb['total_cost'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($inb['receiver_name']) ?></td>
                            <td class="mono" style="font-size: 0.775rem; color: var(--text-muted);">
                                <?= htmlspecialchars(date('M d, Y H:i', strtotime($inb['received_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function handleProductChange() {
    const select = document.getElementById('productSelect');
    const selectedOption = select.options[select.selectedIndex];
    const unitCostInput = document.getElementById('unitCostInput');

    if (selectedOption && selectedOption.value) {
        const cost = parseFloat(selectedOption.getAttribute('data-cost') || 0);
        if (!unitCostInput.value || unitCostInput.value == 0) {
            unitCostInput.value = cost.toFixed(2);
        }
    }
    updateCalculation();
}

function updateCalculation() {
    const select = document.getElementById('productSelect');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption || !selectedOption.value) {
        document.getElementById('previewSku').textContent = '--';
        document.getElementById('previewCurrentStock').textContent = '0 units';
        document.getElementById('previewQuantity').textContent = '+0 units';
        document.getElementById('previewUnitCost').textContent = '$0.00';
        document.getElementById('previewTotalValuation').textContent = '$0.00';
        document.getElementById('previewNewStock').textContent = '0 units';
        return;
    }

    const sku = selectedOption.getAttribute('data-sku') || '';
    const currentStock = parseInt(selectedOption.getAttribute('data-stock') || 0, 10);
    const qtyInput = document.getElementById('quantityInput');
    const quantity = parseInt(qtyInput.value || 0, 10);

    const unitCostInput = document.getElementById('unitCostInput');
    const defaultCost = parseFloat(selectedOption.getAttribute('data-cost') || 0);
    const unitCost = (unitCostInput.value !== '') ? parseFloat(unitCostInput.value || 0) : defaultCost;

    const totalValuation = quantity * unitCost;
    const newStock = currentStock + (quantity > 0 ? quantity : 0);

    document.getElementById('previewSku').textContent = sku;
    document.getElementById('previewCurrentStock').textContent = currentStock + ' units';
    document.getElementById('previewQuantity').textContent = '+' + (quantity > 0 ? quantity : 0) + ' units';
    document.getElementById('previewUnitCost').textContent = '$' + unitCost.toFixed(2);
    document.getElementById('previewTotalValuation').textContent = '$' + totalValuation.toFixed(2);
    document.getElementById('previewNewStock').textContent = newStock + ' units';
}

// Initial calculation on load
window.addEventListener('DOMContentLoaded', () => {
    handleProductChange();
});
</script>

</body>
</html>

