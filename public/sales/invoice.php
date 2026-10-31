<?php
/**
 * Sales Invoice & Receipt View
 *
 * Restricted to: Sales Operator, Admin, Store Manager
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\InvoiceService;

requireRole(['Admin', 'Sales Operator', 'Store Manager']);

$invoiceService = new InvoiceService();

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$number = !empty($_GET['number']) ? trim($_GET['number']) : '';

$invoice = null;
if ($id > 0) {
    $invoice = $invoiceService->getInvoice($id);
} elseif (!empty($number)) {
    $invoice = $invoiceService->getInvoiceByNumber($number);
}

if (!$invoice) {
    http_response_code(404);
    echo '<div style="font-family: sans-serif; padding: 2rem; background: #0f172a; color: #fff; min-height: 100vh;">'
       . '<h2>Invoice Not Found</h2><p>The requested invoice could not be located.</p>'
       . '<p><a href="/sales/index.php" style="color: #3b82f6;">&larr; Return to Sales Checkout</a></p></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?> - Cognova IT</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --success: #10b981;
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
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .no-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
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
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: #334155; }

        /* Invoice Sheet */
        .invoice-sheet {
            background: #fff;
            color: #1e293b;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            padding: 3rem;
            margin-bottom: 3rem;
        }
        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        .brand-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .brand-subtitle {
            font-size: 0.85rem;
            color: #64748b;
        }
        .invoice-title-block {
            text-align: right;
        }
        .invoice-h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 0.25rem;
        }
        .invoice-number-tag {
            font-family: ui-monospace, monospace;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .badge-paid {
            background: #dcfce7;
            color: #166534;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 0.35rem;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }
        .meta-box h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .meta-box p {
            font-size: 0.95rem;
            color: #334155;
            line-height: 1.4;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .items-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .items-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            color: #1e293b;
        }
        .mono { font-family: ui-monospace, monospace; }

        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }
        .totals-box {
            width: 320px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
            color: #475569;
        }
        .total-row.grand-total {
            border-top: 2px solid #e2e8f0;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
        }

        .sheet-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @media print {
            body { background: #fff; color: #000; }
            .no-print, .app-navbar { display: none !important; }
            .page-container { margin: 0; padding: 0; max-width: 100%; }
            .invoice-sheet { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="no-print">
        <div>
            <a href="/sales/index.php" class="btn btn-outline">&larr; New Sales Checkout</a>
            <a href="/sales/invoices.php" class="btn btn-outline" style="margin-left: 0.5rem;">All Invoices</a>
        </div>
        <button type="button" class="btn" onclick="window.print()">
            <span>&#128424;</span> Print Invoice / Receipt
        </button>
    </div>

    <div class="invoice-sheet">
        <div class="sheet-header">
            <div>
                <div class="brand-title">COGNOVA IT ENTERPRISE</div>
                <div class="brand-subtitle">Financial Ledger & Inventory Control Engine</div>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;">
                    100 Enterprise Way, Suite 400<br>
                    Global Tech District
                </div>
            </div>
            <div class="invoice-title-block">
                <div class="invoice-h1">SALES INVOICE</div>
                <div class="invoice-number-tag"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                <div class="badge-paid">Paid & Settled</div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <h4>Billed To (Client):</h4>
                <p><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></p>
                <?php if ($invoice['customer_email']): ?>
                    <p><?= htmlspecialchars($invoice['customer_email']) ?></p>
                <?php endif; ?>
                <?php if ($invoice['customer_phone']): ?>
                    <p><?= htmlspecialchars($invoice['customer_phone']) ?></p>
                <?php endif; ?>
            </div>

            <div class="meta-box" style="text-align: right;">
                <h4>Transaction Details:</h4>
                <p><strong>Date:</strong> <?= date('F d, Y - H:i:s', strtotime($invoice['created_at'])) ?></p>
                <p><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $invoice['payment_method'])) ?></p>
                <p><strong>Sales Operator:</strong> <?= htmlspecialchars($invoice['operator_name']) ?></p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>SKU</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                            <?php if ($item['product_description']): ?>
                                <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($item['product_description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?= htmlspecialchars($item['sku']) ?></td>
                        <td style="text-align: center; font-weight: 600;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align: right;" class="mono">$<?= number_format((float)$item['unit_price'], 2) ?></td>
                        <td style="text-align: right; font-weight: 700;" class="mono">$<?= number_format((float)$item['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span class="mono">$<?= number_format((float)$invoice['subtotal'], 2) ?></span>
                </div>
                <div class="total-row">
                    <span>Sales Tax:</span>
                    <span class="mono">$<?= number_format((float)$invoice['tax_amount'], 2) ?></span>
                </div>
                <?php if ((float)$invoice['discount_amount'] > 0): ?>
                    <div class="total-row" style="color: #dc2626;">
                        <span>Commercial Discount:</span>
                        <span class="mono">-$<?= number_format((float)$invoice['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row grand-total">
                    <span>Total Amount Paid:</span>
                    <span class="mono">$<?= number_format((float)$invoice['total_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($invoice['notes'])): ?>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 6px; margin-bottom: 2rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b; margin-bottom: 0.25rem;">Memo / Notes:</div>
                <div style="font-size: 0.9rem; color: #334155;"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
            </div>
        <?php endif; ?>

        <div class="sheet-footer">
            <div>
                &#10003; <strong>ACID Settled:</strong> Inventory deducted & ledger revenue registered.
            </div>
            <div>
                Generated by Cognova Enterprise Engine &bull; System Verified
            </div>
        </div>
    </div>
</div>

</body>
</html>

