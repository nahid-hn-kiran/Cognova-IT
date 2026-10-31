<?php
/**
 * Product Management & Inventory Catalog
 *
 * Restricted to: Admin, Store Manager
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\ProductService;

// Enforce role guard
requireRole(['Admin', 'Store Manager']);

$productService = new ProductService();
$currentUser = currentUser();
$csrfToken = csrfToken();

$searchQuery = trim($_GET['q'] ?? '');
$filterLowStock = !empty($_GET['low_stock']);
$filterStatus = $_GET['status'] ?? '';

$filters = [
    'q' => $searchQuery,
    'low_stock' => $filterLowStock,
    'status' => $filterStatus,
];

$products = $productService->getAll($filters);
$lowStockCount = $productService->getLowStockCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog & Inventory - Cognova IT</title>
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
            --danger-bg: rgba(239, 68, 68, 0.15);
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
            margin-bottom: 1.5rem;
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
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.8rem;
        }

        /* Low Stock Banner */
        .alert-banner {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .alert-banner-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-icon {
            font-size: 1.4rem;
            color: var(--danger);
        }
        .alert-text strong {
            color: #fca5a5;
            display: block;
            font-size: 0.95rem;
        }
        .alert-text span {
            color: #cbd5e1;
            font-size: 0.85rem;
        }

        /* Filter Controls */
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
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
        }
        .tab-link {
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            background: #0f172a;
            border: 1px solid var(--border);
            transition: all 0.15s;
        }
        .tab-link.active, .tab-link:hover {
            color: #fff;
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.15);
        }

        /* Catalog Table */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
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
        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }
        tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        tbody tr.row-low-stock {
            background: rgba(239, 68, 68, 0.05);
        }
        tbody tr.row-low-stock:hover {
            background: rgba(239, 68, 68, 0.09);
        }
        td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
        }
        .sku-badge {
            font-family: ui-monospace, monospace;
            font-weight: 600;
            color: #60a5fa;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.45rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .product-meta-name {
            font-weight: 600;
            color: #fff;
        }
        .product-meta-desc {
            font-size: 0.775rem;
            color: var(--text-muted);
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.775rem;
            font-weight: 700;
        }
        .stock-normal {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }
        .stock-low {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        .status-pill {
            font-size: 0.725rem;
            text-transform: uppercase;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
        }
        .status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .status-inactive { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
        .status-archived { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .actions-cell {
            display: flex;
            gap: 0.4rem;
        }

        /* Modal Styles */
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
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            max-width: 580px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: modalSlide 0.2s ease-out;
        }
        @keyframes modalSlide {
            from { transform: translateY(15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 1.2rem; color: #fff; }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-body {
            padding: 1.5rem;
            max-height: 80vh;
            overflow-y: auto;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-grid-full { grid-column: span 2; }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.6rem 0.8rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: rgba(0, 0, 0, 0.15);
        }
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #1e293b;
            border: 1px solid var(--border);
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: none;
            z-index: 2000;
            font-size: 0.9rem;
        }
        .toast-success { border-color: var(--success); color: #34d399; }
        .toast-error { border-color: var(--danger); color: #f87171; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Product Management & Inventory</h1>
            <p>Maintain SKU catalogs, pricing margins, stock counts, and threshold monitoring.</p>
        </div>
        <div class="header-actions">
            <a href="/inventory/stock-in.php" class="btn btn-success">
                <span>&#10515;</span> Record Stock In
            </a>
            <button type="button" class="btn" onclick="openCreateModal()">
                <span>+</span> Add Product
            </button>
        </div>
    </div>

    <?php if ($lowStockCount > 0): ?>
        <div class="alert-banner">
            <div class="alert-banner-content">
                <span class="alert-icon">&#9888;</span>
                <div class="alert-text">
                    <strong>Critical Reorder Warning: <?= $lowStockCount ?> product(s) at or below reorder threshold</strong>
                    <span>Review highlighted items below and initiate inbound supplier purchase orders to avoid stockout.</span>
                </div>
            </div>
            <a href="/inventory/index.php?low_stock=1" class="btn btn-outline btn-sm">
                View Low Stock Only
            </a>
        </div>
    <?php endif; ?>

    <!-- Controls & Filters -->
    <div class="controls-card">
        <form class="search-form" method="GET" action="/inventory/index.php">
            <?php if ($filterLowStock): ?>
                <input type="hidden" name="low_stock" value="1">
            <?php endif; ?>
            <input 
                type="text" 
                name="q" 
                class="search-input" 
                placeholder="Search by SKU or Product Name..." 
                value="<?= htmlspecialchars($searchQuery) ?>"
            >
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            <?php if ($searchQuery || $filterLowStock): ?>
                <a href="/inventory/index.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="filter-tabs">
            <a href="/inventory/index.php" class="tab-link <?= (!$filterLowStock && !$filterStatus) ? 'active' : '' ?>">
                All Products (<?= count($products) ?>)
            </a>
            <a href="/inventory/index.php?low_stock=1" class="tab-link <?= $filterLowStock ? 'active' : '' ?>">
                Low Stock Alerts <?= $lowStockCount > 0 ? "({$lowStockCount})" : '' ?>
            </a>
        </div>
    </div>

    <!-- Products Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Details</th>
                    <th>Unit Cost</th>
                    <th>Selling Price</th>
                    <th>Gross Margin</th>
                    <th>In Stock</th>
                    <th>Reorder Lvl</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No products found matching the criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr class="<?= $p['is_low_stock'] ? 'row-low-stock' : '' ?>" id="product-row-<?= $p['id'] ?>">
                            <td>
                                <span class="sku-badge"><?= htmlspecialchars($p['sku']) ?></span>
                            </td>
                            <td>
                                <div class="product-meta-name"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="product-meta-desc"><?= htmlspecialchars($p['description'] ?? 'No description provided') ?></div>
                            </td>
                            <td>$<?= number_format((float)$p['unit_cost'], 2) ?></td>
                            <td>$<?= number_format((float)$p['selling_price'], 2) ?></td>
                            <td>
                                <span style="font-weight: 600; color: <?= $p['margin_percentage'] > 20 ? '#34d399' : '#fbbf24' ?>;">
                                    <?= number_format((float)$p['margin_percentage'], 1) ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($p['is_low_stock']): ?>
                                    <span class="stock-badge stock-low" title="Stock at or below reorder level (<?= $p['reorder_level'] ?>)">
                                        &#9888; <?= (int)$p['stock_quantity'] ?> units
                                    </span>
                                <?php else: ?>
                                    <span class="stock-badge stock-normal">
                                        <?= (int)$p['stock_quantity'] ?> units
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$p['reorder_level'] ?></td>
                            <td>
                                <span class="status-pill status-<?= htmlspecialchars($p['status']) ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="actions-cell" style="justify-content: flex-end;">
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)"
                                    >
                                        Edit
                                    </button>
                                    <a 
                                        href="/inventory/stock-in.php?product_id=<?= $p['id'] ?>" 
                                        class="btn btn-outline btn-sm" 
                                        style="border-color: rgba(16, 185, 129, 0.4); color: #34d399;"
                                        title="Record inbound shipment for this SKU"
                                    >
                                        + Stock In
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Product -->
<div class="modal-overlay" id="productModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Product</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="productForm" onsubmit="handleProductSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="prodId" value="">
            <input type="hidden" name="action" id="prodAction" value="create_product">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="prodSku">SKU (Stock Keeping Unit) *</label>
                        <input 
                            type="text" 
                            name="sku" 
                            id="prodSku" 
                            class="form-control" 
                            placeholder="e.g. SKU-SRV-R750" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="prodStatus">Status *</label>
                        <select name="status" id="prodStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <div class="form-group form-grid-full">
                        <label for="prodName">Product Name *</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="prodName" 
                            class="form-control" 
                            placeholder="e.g. Dell PowerEdge Server" 
                            required
                        >
                    </div>

                    <div class="form-group form-grid-full">
                        <label for="prodDescription">Description</label>
                        <textarea 
                            name="description" 
                            id="prodDescription" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Hardware specifications, warranty info, etc."
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label for="prodUnitCost">Unit Cost (USD) *</label>
                        <input 
                            type="number" 
                            step="0.0001" 
                            min="0" 
                            name="unit_cost" 
                            id="prodUnitCost" 
                            class="form-control" 
                            placeholder="0.00" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="prodSellingPrice">Selling Price (USD) *</label>
                        <input 
                            type="number" 
                            step="0.0001" 
                            min="0" 
                            name="selling_price" 
                            id="prodSellingPrice" 
                            class="form-control" 
                            placeholder="0.00" 
                            required
                        >
                    </div>

                    <div class="form-group" id="initialStockGroup">
                        <label for="prodStockQuantity">Initial Stock Quantity</label>
                        <input 
                            type="number" 
                            min="0" 
                            name="stock_quantity" 
                            id="prodStockQuantity" 
                            class="form-control" 
                            value="0"
                        >
                    </div>

                    <div class="form-group">
                        <label for="prodReorderLevel">Reorder Threshold Level *</label>
                        <input 
                            type="number" 
                            min="0" 
                            name="reorder_level" 
                            id="prodReorderLevel" 
                            class="form-control" 
                            value="10" 
                            required
                        >
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn" id="saveProductBtn">Save Product</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toastMessage"></div>

<script>
const modal = document.getElementById('productModal');
const productForm = document.getElementById('productForm');
const modalTitle = document.getElementById('modalTitle');
const prodId = document.getElementById('prodId');
const prodAction = document.getElementById('prodAction');
const initialStockGroup = document.getElementById('initialStockGroup');

function openCreateModal() {
    productForm.reset();
    prodId.value = '';
    prodAction.value = 'create_product';
    modalTitle.textContent = 'Add New Product';
    initialStockGroup.style.display = 'block';
    modal.classList.add('active');
}

function openEditModal(product) {
    productForm.reset();
    prodId.value = product.id;
    prodAction.value = 'update_product';
    modalTitle.textContent = 'Edit Product: ' + product.sku;
    
    document.getElementById('prodSku').value = product.sku;
    document.getElementById('prodName').value = product.name;
    document.getElementById('prodDescription').value = product.description || '';
    document.getElementById('prodUnitCost').value = parseFloat(product.unit_cost).toFixed(2);
    document.getElementById('prodSellingPrice').value = parseFloat(product.selling_price).toFixed(2);
    document.getElementById('prodReorderLevel').value = product.reorder_level;
    document.getElementById('prodStatus').value = product.status;

    // Stock adjustments must happen through dedicated Stock-In or sales to preserve the ledger
    initialStockGroup.style.display = 'none';

    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

window.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});

async function handleProductSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProductBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new FormData(productForm);

    try {
        const response = await fetch('/inventory/api.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || 'Product saved successfully!', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || 'Failed to save product.', 'error');
        }
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Product';
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toastMessage');
    toast.textContent = message;
    toast.className = 'toast toast-' + type;
    toast.style.display = 'block';
    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}
</script>

</body>
</html>

