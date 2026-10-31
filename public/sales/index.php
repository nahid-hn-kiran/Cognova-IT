<?php
/**
 * Sales Checkout & Point-of-Sale Portal
 *
 * Restricted to: Sales Operator, Admin
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\ProductService;
use App\Services\InvoiceService;

// Enforce role guard: Admin or Sales Operator
requireRole(['Admin', 'Sales Operator']);

$productService = new ProductService();
$invoiceService = new InvoiceService();
$currentUser = currentUser();
$csrfToken = csrfToken();

$products = $productService->getAll(['status' => 'active']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Checkout (POS) - Cognova IT</title>
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
            --success-hover: #059669;
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
            max-width: 1240px;
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
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: var(--success-hover); }
        .btn-danger { background: var(--danger); }
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

        /* Layout Grid */
        .pos-grid {
            display: grid;
            grid-template-columns: 1.65fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .pos-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            margin-bottom: 1.5rem;
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

        /* Form elements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.65rem 0.85rem;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        /* Cart Items Table */
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            margin-top: 0.75rem;
        }
        .cart-table th {
            background: #111827;
            padding: 0.75rem 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .cart-table td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .cart-table tr.has-stock-error {
            background: rgba(239, 68, 68, 0.12);
        }
        .cart-table tr.has-stock-error td {
            border-bottom-color: rgba(239, 68, 68, 0.4);
        }
        .stock-badge-pill {
            font-size: 0.725rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            display: inline-block;
        }
        .stock-ok { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .stock-warn { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }

        /* Summary panel */
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            position: sticky;
            top: 5rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
        }
        .summary-total {
            border-bottom: none;
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 2px solid var(--border);
            font-size: 1.35rem;
            font-weight: 700;
            color: #34d399;
        }
        .mono { font-family: ui-monospace, monospace; }

        .alert-box {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: none;
        }
        .alert-box-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }
        .alert-box-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #6ee7b7;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Sales Checkout & Point-of-Sale (POS)</h1>
            <p>Generate customer sales invoices with real-time stock reservation and double-entry ledger settlement.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="/sales/invoices.php" class="btn btn-outline">
                <span>&#128196;</span> Invoice History
            </a>
            <a href="/inventory/index.php" class="btn btn-outline">
                <span>&#9638;</span> Catalog
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <div class="alert-box alert-box-error" id="errorAlert"></div>
    <div class="alert-box alert-box-success" id="successAlert"></div>

    <form id="checkoutForm" onsubmit="handleCheckout(event)">
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="pos-grid">
            <!-- Left Column: Customer & Cart Line Items -->
            <div>
                <!-- Customer Details -->
                <div class="card">
                    <div class="card-title">
                        <span>1. Customer & Payment Information</span>
                        <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">
                            Cashier: <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                        </span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custName">Customer Full Name *</label>
                            <input 
                                type="text" 
                                id="custName" 
                                class="form-control" 
                                placeholder="e.g. Apex Data Systems" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="paymentMethod">Payment Method *</label>
                            <select id="paymentMethod" class="form-control" required>
                                <option value="cash">Cash Settlement</option>
                                <option value="bank_transfer" selected>Corporate Bank Wire</option>
                                <option value="credit_card">Corporate Credit Card</option>
                                <option value="check">Certified Check</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custEmail">Customer Email</label>
                            <input 
                                type="email" 
                                id="custEmail" 
                                class="form-control" 
                                placeholder="billing@apexdata.com"
                            >
                        </div>

                        <div class="form-group">
                            <label for="custPhone">Customer Phone</label>
                            <input 
                                type="text" 
                                id="custPhone" 
                                class="form-control" 
                                placeholder="+1 (555) 014-9922"
                            >
                        </div>
                    </div>
                </div>

                <!-- Line Items Cart -->
                <div class="card">
                    <div class="card-title">
                        <span>2. Order Line Items</span>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addCartRow()">
                            + Add Item Row
                        </button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th style="width: 44%;">Select Product</th>
                                    <th style="width: 18%;">Available</th>
                                    <th style="width: 14%;">Quantity</th>
                                    <th style="width: 14%;">Unit Price</th>
                                    <th style="width: 10%; text-align: right;">Subtotal</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody">
                                <!-- Cart rows dynamically rendered by JS -->
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1rem; text-align: right;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="addCartRow()">
                            + Add Another Product
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div class="card">
                    <div class="card-title">
                        <span>3. Invoice Memo & Special Instructions</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea 
                            id="orderNotes" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Special dispatch instructions, purchase order reference, or discount justifications..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Right Column: Financial Summary & Authorize Button -->
            <div>
                <div class="summary-card">
                    <div class="card-title" style="margin-bottom: 1rem;">
                        <span>Order Summary</span>
                        <span style="font-size: 0.75rem; color: #60a5fa;" class="mono">COGNOVA-POS</span>
                    </div>

                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Line Items Count</span>
                        <span id="summaryItemCount" class="mono">0 item(s)</span>
                    </div>

                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Gross Subtotal</span>
                        <span id="summarySubtotal" class="mono">$0.00</span>
                    </div>

                    <div class="summary-row" style="align-items: center;">
                        <span style="color: var(--text-muted);">Sales Tax (USD)</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0" 
                            id="taxAmountInput" 
                            class="form-control mono" 
                            style="width: 110px; padding: 0.35rem 0.5rem; text-align: right;" 
                            value="0.00" 
                            oninput="recalculateTotals()"
                        >
                    </div>

                    <div class="summary-row" style="align-items: center;">
                        <span style="color: var(--text-muted);">Commercial Discount</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0" 
                            id="discountAmountInput" 
                            class="form-control mono" 
                            style="width: 110px; padding: 0.35rem 0.5rem; text-align: right; color: #f87171;" 
                            value="0.00" 
                            oninput="recalculateTotals()"
                        >
                    </div>

                    <div class="summary-row summary-total">
                        <span>Net Total Due</span>
                        <span id="summaryGrandTotal">$0.00</span>
                    </div>

                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 6px; padding: 0.85rem; margin: 1.25rem 0; font-size: 0.8rem; color: #a7f3d0;">
                        &#128274; <strong>Atomic Guarantee:</strong> Product stock will be locked during checkout. If another operator checks out the last unit concurrently, this sale will fail safely without overselling.
                    </div>

                    <button 
                        type="submit" 
                        class="btn btn-success" 
                        id="checkoutSubmitBtn" 
                        style="width: 100%; justify-content: center; padding: 0.9rem; font-size: 1rem;"
                    >
                        <span>&#10003;</span> Authorize & Settle Invoice
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Product Data Payload for JS -->
<script>
const CATALOG = <?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'sku' => $p['sku'],
        'name' => $p['name'],
        'price' => (float)$p['selling_price'],
        'stock' => (int)$p['stock_quantity'],
    ];
}, $products), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

let rowCounter = 0;

function addCartRow(preselectedId = null) {
    rowCounter++;
    const tbody = document.getElementById('cartTableBody');
    const tr = document.createElement('tr');
    tr.id = 'cart-row-' + rowCounter;

    let optionsHtml = '<option value="">-- Select Product --</option>';
    CATALOG.forEach(p => {
        const isOutOfStock = p.stock <= 0;
        const selected = (preselectedId && preselectedId === p.id) ? 'selected' : '';
        const disabled = isOutOfStock ? 'disabled' : '';
        const stockLabel = isOutOfStock ? '[OUT OF STOCK]' : `(${p.stock} in stock)`;
        optionsHtml += `<option value="${p.id}" data-stock="${p.stock}" data-price="${p.price}" ${disabled} ${selected}>
            ${p.sku} - ${p.name} ${stockLabel}
        </option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-control row-product-select" onchange="handleRowProductChange(${rowCounter})" required>
                ${optionsHtml}
            </select>
        </td>
        <td>
            <span class="stock-badge-pill stock-ok" id="row-stock-${rowCounter}">--</span>
        </td>
        <td>
            <input 
                type="number" 
                class="form-control mono row-qty" 
                min="1" 
                value="1" 
                required 
                oninput="handleRowQtyChange(${rowCounter})"
            >
        </td>
        <td>
            <input 
                type="number" 
                step="0.0001" 
                min="0" 
                class="form-control mono row-price" 
                placeholder="0.00" 
                oninput="recalculateTotals()"
            >
        </td>
        <td style="text-align: right; font-weight: 600;" class="mono" id="row-subtotal-${rowCounter}">
            $0.00
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-outline btn-sm" style="color: #f87171; border-color: rgba(239,68,68,0.3);" onclick="removeCartRow(${rowCounter})">
                &times;
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    if (preselectedId) {
        handleRowProductChange(rowCounter);
    } else {
        recalculateTotals();
    }
}

function removeCartRow(id) {
    const row = document.getElementById('cart-row-' + id);
    if (row) {
        row.remove();
        recalculateTotals();
    }
}

function handleRowProductChange(rowId) {
    const row = document.getElementById('cart-row-' + rowId);
    if (!row) return;

    const select = row.querySelector('.row-product-select');
    const selectedOption = select.options[select.selectedIndex];
    const stockPill = document.getElementById('row-stock-' + rowId);
    const priceInput = row.querySelector('.row-price');
    const qtyInput = row.querySelector('.row-qty');

    if (!selectedOption || !selectedOption.value) {
        stockPill.textContent = '--';
        stockPill.className = 'stock-badge-pill stock-ok';
        priceInput.value = '';
        recalculateTotals();
        return;
    }

    const stock = parseInt(selectedOption.getAttribute('data-stock') || 0, 10);
    const price = parseFloat(selectedOption.getAttribute('data-price') || 0);

    priceInput.value = price.toFixed(2);

    if (stock <= 0) {
        stockPill.textContent = 'Out of Stock (0)';
        stockPill.className = 'stock-badge-pill stock-warn';
        row.classList.add('has-stock-error');
    } else {
        stockPill.textContent = stock + ' avail';
        stockPill.className = 'stock-badge-pill stock-ok';
        row.classList.remove('has-stock-error');
        qtyInput.max = stock;
    }

    handleRowQtyChange(rowId);
}

function handleRowQtyChange(rowId) {
    const row = document.getElementById('cart-row-' + rowId);
    if (!row) return;

    const select = row.querySelector('.row-product-select');
    const selectedOption = select.options[select.selectedIndex];
    const qtyInput = row.querySelector('.row-qty');
    const stockPill = document.getElementById('row-stock-' + rowId);

    if (selectedOption && selectedOption.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || 0, 10);
        const qty = parseInt(qtyInput.value || 0, 10);

        if (stock <= 0 || qty > stock) {
            row.classList.add('has-stock-error');
            stockPill.className = 'stock-badge-pill stock-warn';
            stockPill.textContent = (stock <= 0) ? '0 units left' : `Only ${stock} left!`;
        } else {
            row.classList.remove('has-stock-error');
            stockPill.className = 'stock-badge-pill stock-ok';
            stockPill.textContent = stock + ' avail';
        }
    }

    recalculateTotals();
}

function recalculateTotals() {
    let subtotal = 0;
    let itemCount = 0;
    let hasZeroOrExceededStock = false;

    const rows = document.querySelectorAll('#cartTableBody tr');

    rows.forEach(row => {
        const select = row.querySelector('.row-product-select');
        const selectedOption = select ? select.options[select.selectedIndex] : null;

        if (selectedOption && selectedOption.value) {
            const stock = parseInt(selectedOption.getAttribute('data-stock') || 0, 10);
            const qty = parseInt(row.querySelector('.row-qty').value || 0, 10);
            const price = parseFloat(row.querySelector('.row-price').value || 0);

            if (stock <= 0 || qty > stock) {
                hasZeroOrExceededStock = true;
            }

            const rowSubtotal = qty * price;
            subtotal += rowSubtotal;
            itemCount += qty;

            const subtotalCell = row.querySelector('td:nth-child(5)');
            if (subtotalCell) {
                subtotalCell.textContent = '$' + rowSubtotal.toFixed(2);
            }
        }
    });

    const taxInput = parseFloat(document.getElementById('taxAmountInput').value || 0);
    const discountInput = parseFloat(document.getElementById('discountAmountInput').value || 0);

    const grandTotal = Math.max(0, subtotal + taxInput - discountInput);

    document.getElementById('summaryItemCount').textContent = itemCount + ' item(s)';
    document.getElementById('summarySubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('summaryGrandTotal').textContent = '$' + grandTotal.toFixed(2);

    const checkoutBtn = document.getElementById('checkoutSubmitBtn');
    if (hasZeroOrExceededStock) {
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = '⚠ Correct Insufficient Stock to Proceed';
        checkoutBtn.style.background = '#ef4444';
    } else {
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = '✓ Authorize & Settle Invoice';
        checkoutBtn.style.background = 'var(--success)';
    }
}

async function handleCheckout(e) {
    e.preventDefault();

    const errorAlert = document.getElementById('errorAlert');
    const successAlert = document.getElementById('successAlert');
    errorAlert.style.display = 'none';
    successAlert.style.display = 'none';

    // Collect line items
    const rows = document.querySelectorAll('#cartTableBody tr');
    const items = [];

    rows.forEach(row => {
        const select = row.querySelector('.row-product-select');
        const pId = select ? parseInt(select.value || 0, 10) : 0;
        const qty = parseInt(row.querySelector('.row-qty').value || 0, 10);
        const price = parseFloat(row.querySelector('.row-price').value || 0);

        if (pId > 0 && qty > 0) {
            items.push({
                product_id: pId,
                quantity: qty,
                unit_price: price
            });
        }
    });

    if (items.length === 0) {
        showError('Please add at least one valid product line item to the order.');
        return;
    }

    const payload = {
        action: 'checkout',
        csrf_token: document.getElementById('csrfToken').value,
        customer_name: document.getElementById('custName').value,
        customer_email: document.getElementById('custEmail').value,
        customer_phone: document.getElementById('custPhone').value,
        payment_method: document.getElementById('paymentMethod').value,
        tax_amount: parseFloat(document.getElementById('taxAmountInput').value || 0),
        discount_amount: parseFloat(document.getElementById('discountAmountInput').value || 0),
        notes: document.getElementById('orderNotes').value,
        items: items
    };

    const submitBtn = document.getElementById('checkoutSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Acquiring Row Locks & Settle...';

    try {
        const response = await fetch('/sales/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            successAlert.textContent = `Invoice ${data.invoice_number} settled successfully! Total: $${parseFloat(data.total_amount).toFixed(2)}. Redirecting to receipt...`;
            successAlert.style.display = 'block';
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 800);
        } else {
            showError(data.message || 'Checkout failed.');
            submitBtn.disabled = false;
            submitBtn.textContent = '✓ Authorize & Settle Invoice';
        }
    } catch (err) {
        showError('Network communication error: ' + err.message);
        submitBtn.disabled = false;
        submitBtn.textContent = '✓ Authorize & Settle Invoice';
    }
}

function showError(msg) {
    const errorAlert = document.getElementById('errorAlert');
    errorAlert.textContent = '⚠ ' + msg;
    errorAlert.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Initialize with one empty line row
window.addEventListener('DOMContentLoaded', () => {
    addCartRow();
});
</script>

</body>
</html>

