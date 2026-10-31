<?php

namespace App\Services;

use App\Config\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Audit Ledger Review & Transactional Reversal Service
 *
 * Implements immutable ledger queries, financial summaries, and ACID reversal
 * workflows (refunds/cancellations) restoring stock and logging compensating ledger entries.
 */
class LedgerService
{
    /**
     * Retrieve filterable, paginated audit ledger events
     *
     * @param array{
     *     transaction_type?: string,
     *     entry_category?: string,
     *     entity_type?: string,
     *     product_id?: int,
     *     user_id?: int,
     *     date_from?: string,
     *     date_to?: string,
     *     q?: string
     * } $filters
     * @param int $page
     * @param int $perPage
     * @return array{items: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $pdo = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ' WHERE 1=1';
        $params = [];

        if (!empty($filters['transaction_type']) && $filters['transaction_type'] !== 'ALL') {
            $where .= ' AND al.transaction_type = :tx_type';
            $params[':tx_type'] = $filters['transaction_type'];
        }

        if (!empty($filters['entry_category']) && $filters['entry_category'] !== 'ALL') {
            $where .= ' AND al.entry_category = :entry_cat';
            $params[':entry_cat'] = $filters['entry_category'];
        }

        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'ALL') {
            $where .= ' AND al.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['product_id'])) {
            $where .= ' AND al.product_id = :product_id';
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['user_id'])) {
            $where .= ' AND al.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where .= ' AND al.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND al.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['q'])) {
            $where .= ' AND (al.notes LIKE :query OR p.sku LIKE :query OR p.name LIKE :query OR u.name LIKE :query)';
            $params[':query'] = '%' . trim($filters['q']) . '%';
        }

        // Count total matching rows
        $countSql = "
            SELECT COUNT(*) AS total
            FROM audit_ledger al
            LEFT JOIN products p ON al.product_id = p.id
            LEFT JOIN users u ON al.user_id = u.id
            {$where}
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetch()['total'] ?? 0);

        // Fetch paginated slice
        $sql = "
            SELECT 
                al.id,
                al.transaction_type,
                al.entry_category,
                al.entity_type,
                al.entity_id,
                al.product_id,
                al.user_id,
                al.quantity_delta,
                al.unit_cost,
                al.total_amount,
                al.stock_balance_after,
                al.notes,
                al.created_at,
                p.sku AS product_sku,
                p.name AS product_name,
                u.name AS user_name,
                u.email AS user_email,
                r.name AS user_role
            FROM audit_ledger al
            LEFT JOIN products p ON al.product_id = p.id
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            {$where}
            ORDER BY al.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Compute aggregate metrics across audit entries
     */
    public function getMetrics(): array
    {
        $pdo = Database::getConnection();

        $sql = '
            SELECT 
                COUNT(*) AS total_events,
                SUM(CASE WHEN transaction_type = "STOCK_IN" THEN 1 ELSE 0 END) AS inbound_events,
                SUM(CASE WHEN transaction_type = "SALE" THEN 1 ELSE 0 END) AS sale_events,
                SUM(CASE WHEN transaction_type = "REFUND" THEN 1 ELSE 0 END) AS refund_events,
                SUM(CASE WHEN transaction_type = "ADJUSTMENT" THEN 1 ELSE 0 END) AS adjustment_events,
                SUM(CASE WHEN entry_category = "FINANCIAL" AND transaction_type = "SALE" THEN total_amount ELSE 0 END) AS gross_revenue_recognized,
                SUM(CASE WHEN entry_category = "FINANCIAL" AND transaction_type = "REFUND" THEN total_amount ELSE 0 END) AS total_refunded_amount
            FROM audit_ledger
        ';
        $stmt = $pdo->query($sql);
        return $stmt->fetch() ?: [];
    }

    /**
     * Perform an ACID Transactional Reversal of a Sale / Invoice
     *
     * 1. Validates invoice can be reversed (e.g. not already refunded/cancelled).
     * 2. Locks invoice and affected product rows via SELECT FOR UPDATE in sorted order.
     * 3. Restores stock quantities for each product item.
     * 4. Updates invoice status to 'refunded'.
     * 5. Logs compensating double-entry records in audit_ledger (REFUND category).
     *
     * @param int $invoiceId
     * @param string $reason
     * @param int $adminUserId
     * @return array
     */
    public function reverseInvoice(int $invoiceId, string $reason, int $adminUserId): array
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw new InvalidArgumentException('A reversal justification reason is required.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // 1. Lock and fetch invoice
            $invStmt = $pdo->prepare('
                SELECT id, invoice_number, total_amount, status 
                FROM invoices 
                WHERE id = :id 
                FOR UPDATE
            ');
            $invStmt->execute([':id' => $invoiceId]);
            $invoice = $invStmt->fetch();

            if (!$invoice) {
                throw new InvalidArgumentException("Invoice ID #{$invoiceId} does not exist.");
            }

            if ($invoice['status'] === 'refunded' || $invoice['status'] === 'cancelled') {
                throw new InvalidArgumentException("Invoice '{$invoice['invoice_number']}' has already been marked as {$invoice['status']}.");
            }

            // 2. Fetch line items
            $itemStmt = $pdo->prepare('
                SELECT product_id, quantity, unit_price, subtotal 
                FROM invoice_items 
                WHERE invoice_id = :invoice_id
            ');
            $itemStmt->execute([':invoice_id' => $invoiceId]);
            $items = $itemStmt->fetchAll();

            if (empty($items)) {
                throw new InvalidArgumentException("Invoice '{$invoice['invoice_number']}' contains no line items to reverse.");
            }

            // 3. Sort product IDs and lock them in order
            $productIds = array_column($items, 'product_id');
            sort($productIds, SORT_NUMERIC);

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $lockProdStmt = $pdo->prepare("
                SELECT id, sku, name, unit_cost, stock_quantity 
                FROM products 
                WHERE id IN ({$placeholders}) 
                ORDER BY id ASC 
                FOR UPDATE
            ");
            $lockProdStmt->execute($productIds);
            $lockedProducts = [];
            while ($p = $lockProdStmt->fetch()) {
                $lockedProducts[(int)$p['id']] = $p;
            }

            // 4. Update invoice status to 'refunded'
            $updateInvStmt = $pdo->prepare('
                UPDATE invoices SET 
                    status = "refunded", 
                    notes = CONCAT(COALESCE(notes, ""), "\n[REVERSAL] ", :reason),
                    updated_at = NOW() 
                WHERE id = :id
            ');
            $updateInvStmt->execute([
                ':reason' => $reason,
                ':id' => $invoiceId,
            ]);

            // 5. Restore stock quantities and log audit ledger entries
            $restoreStmt = $pdo->prepare('
                UPDATE products SET 
                    stock_quantity = stock_quantity + :qty, 
                    updated_at = NOW() 
                WHERE id = :id
            ');

            $ledgerStmt = $pdo->prepare('
                INSERT INTO audit_ledger (
                    transaction_type, entry_category, entity_type, entity_id,
                    product_id, user_id, quantity_delta, unit_cost, total_amount,
                    stock_balance_after, notes, created_at
                ) VALUES (
                    "REFUND", :category, "invoices", :entity_id,
                    :product_id, :user_id, :quantity_delta, :unit_cost, :total_amount,
                    :balance_after, :notes, NOW()
                )
            ');

            foreach ($items as $item) {
                $pId = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $prod = $lockedProducts[$pId] ?? null;

                if ($prod) {
                    // Increment product stock back
                    $restoreStmt->execute([':qty' => $qty, ':id' => $pId]);
                    $newBalance = (int)$prod['stock_quantity'] + $qty;

                    // Log inventory restoration audit entry
                    $ledgerStmt->execute([
                        ':category' => 'INVENTORY',
                        ':entity_id' => $invoiceId,
                        ':product_id' => $pId,
                        ':user_id' => $adminUserId,
                        ':quantity_delta' => $qty,
                        ':unit_cost' => $prod['unit_cost'],
                        ':total_amount' => $item['subtotal'],
                        ':balance_after' => $newBalance,
                        ':notes' => "Stock restored for reversed invoice {$invoice['invoice_number']} ({$qty}x {$prod['sku']}): {$reason}",
                    ]);
                }
            }

            // 6. Log financial reversal entry
            $ledgerStmt->execute([
                ':category' => 'FINANCIAL',
                ':entity_id' => $invoiceId,
                ':product_id' => null,
                ':user_id' => $adminUserId,
                ':quantity_delta' => 0,
                ':unit_cost' => 0.0,
                ':total_amount' => (float)$invoice['total_amount'],
                ':balance_after' => null,
                ':notes' => "Financial refund recorded for invoice {$invoice['invoice_number']}: {$reason}",
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'refunded_amount' => (float)$invoice['total_amount'],
                'items_restored' => count($items),
                'message' => "Invoice '{$invoice['invoice_number']}' successfully reversed. Stock quantities restored and audit entry registered.",
            ];

        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new RuntimeException('Failed to process invoice reversal: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Perform an ACID Transactional Reversal of an Inbound Shipment
     *
     * @param int $inboundId
     * @param string $reason
     * @param int $adminUserId
     * @return array
     */
    public function reverseInbound(int $inboundId, string $reason, int $adminUserId): array
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw new InvalidArgumentException('A reversal reason is required.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Lock and fetch inbound shipment
            $inbStmt = $pdo->prepare('
                SELECT id, product_id, quantity, unit_cost, total_cost, reference_no 
                FROM stock_inbound 
                WHERE id = :id 
                FOR UPDATE
            ');
            $inbStmt->execute([':id' => $inboundId]);
            $inbound = $inbStmt->fetch();

            if (!$inbound) {
                throw new InvalidArgumentException("Inbound receipt #{$inboundId} not found.");
            }

            $productId = (int)$inbound['product_id'];
            $qty = (int)$inbound['quantity'];

            // Lock product row
            $prodStmt = $pdo->prepare('
                SELECT id, sku, name, stock_quantity, unit_cost 
                FROM products 
                WHERE id = :id 
                FOR UPDATE
            ');
            $prodStmt->execute([':id' => $productId]);
            $product = $prodStmt->fetch();

            if (!$product) {
                throw new InvalidArgumentException("Product #{$productId} not found.");
            }

            $currentStock = (int)$product['stock_quantity'];
            if ($qty > $currentStock) {
                throw new InvalidArgumentException(sprintf(
                    "Cannot reverse inbound shipment '%s'. Items have already been depleted (Current on-hand: %d, Reversal requires: %d).",
                    $inbound['reference_no'],
                    $currentStock,
                    $qty
                ));
            }

            $newStock = $currentStock - $qty;

            // Decrement product stock back
            $updateProdStmt = $pdo->prepare('
                UPDATE products SET 
                    stock_quantity = stock_quantity - :qty, 
                    updated_at = NOW() 
                WHERE id = :id
            ');
            $updateProdStmt->execute([':qty' => $qty, ':id' => $productId]);

            // Mark inbound notes with cancellation
            $updateInbStmt = $pdo->prepare('
                UPDATE stock_inbound SET 
                    notes = CONCAT(COALESCE(notes, ""), "\n[REVERSED] ", :reason),
                    updated_at = NOW() 
                WHERE id = :id
            ');
            $updateInbStmt->execute([':reason' => $reason, ':id' => $inboundId]);

            // Insert adjustment audit entry
            $ledgerStmt = $pdo->prepare('
                INSERT INTO audit_ledger (
                    transaction_type, entry_category, entity_type, entity_id,
                    product_id, user_id, quantity_delta, unit_cost, total_amount,
                    stock_balance_after, notes, created_at
                ) VALUES (
                    "ADJUSTMENT", "INVENTORY", "stock_inbound", :entity_id,
                    :product_id, :user_id, :quantity_delta, :unit_cost, :total_amount,
                    :balance_after, :notes, NOW()
                )
            ');
            $ledgerStmt->execute([
                ':entity_id' => $inboundId,
                ':product_id' => $productId,
                ':user_id' => $adminUserId,
                ':quantity_delta' => -1 * $qty,
                ':unit_cost' => $inbound['unit_cost'],
                ':total_amount' => $inbound['total_cost'],
                ':balance_after' => $newStock,
                ':notes' => "Inbound shipment reversed ({$inbound['reference_no']}): {$reason}",
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'inbound_id' => $inboundId,
                'reference_no' => $inbound['reference_no'],
                'quantity_reversed' => $qty,
                'new_stock' => $newStock,
                'message' => "Inbound receipt '{$inbound['reference_no']}' reversed. {$qty} units deducted from '{$product['sku']}'.",
            ];

        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new RuntimeException('Failed to process inbound reversal: ' . $e->getMessage(), 0, $e);
        }
    }
}

