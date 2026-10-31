<?php

namespace App\Services;

use App\Config\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sales Invoicing & Atomic Concurrency Engine
 *
 * Implements ACID transactions with row-level locking (SELECT FOR UPDATE)
 * to guarantee stock integrity under high concurrency and write audit entries.
 */
class InvoiceService
{
    /**
     * Process sales checkout and generate invoice with atomic concurrency
     *
     * @param array{
     *     customer_name: string,
     *     customer_email?: string,
     *     customer_phone?: string,
     *     tax_amount?: float,
     *     discount_amount?: float,
     *     payment_method?: string,
     *     notes?: string
     * } $headerData
     * @param array<array{product_id: int, quantity: int, unit_price?: float}> $items
     * @param int $userId Sales Operator or Admin processing the transaction
     * @return array
     */
    public function createInvoice(array $headerData, array $items, int $userId): array
    {
        $customerName = trim($headerData['customer_name'] ?? '');
        $customerEmail = trim($headerData['customer_email'] ?? '') ?: null;
        $customerPhone = trim($headerData['customer_phone'] ?? '') ?: null;
        $paymentMethod = trim($headerData['payment_method'] ?? 'cash') ?: 'cash';
        $taxInput = max(0.0, (float)($headerData['tax_amount'] ?? 0.0));
        $discountInput = max(0.0, (float)($headerData['discount_amount'] ?? 0.0));
        $notes = trim($headerData['notes'] ?? '') ?: null;

        if (empty($customerName)) {
            throw new InvalidArgumentException('Customer name is required for sales checkout.');
        }

        if (empty($items)) {
            throw new InvalidArgumentException('At least one product line item is required.');
        }

        // Aggregate quantities per product to handle duplicate line items safely
        $consolidatedItems = [];
        foreach ($items as $idx => $item) {
            $pId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            $overridePrice = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float)$item['unit_price'] : null;

            if ($pId <= 0) {
                throw new InvalidArgumentException("Item #" . ($idx + 1) . " has an invalid product selection.");
            }
            if ($qty <= 0) {
                throw new InvalidArgumentException("Item #" . ($idx + 1) . " quantity must be at least 1 unit.");
            }

            if (!isset($consolidatedItems[$pId])) {
                $consolidatedItems[$pId] = [
                    'product_id' => $pId,
                    'quantity' => $qty,
                    'unit_price' => $overridePrice,
                ];
            } else {
                $consolidatedItems[$pId]['quantity'] += $qty;
                if ($overridePrice !== null) {
                    $consolidatedItems[$pId]['unit_price'] = $overridePrice;
                }
            }
        }

        $pdo = Database::getConnection();

        // Sort product IDs in ascending order to prevent SQL deadlocks under concurrent transactions
        $productIds = array_keys($consolidatedItems);
        sort($productIds, SORT_NUMERIC);

        $pdo->beginTransaction();

        try {
            // Lock all target product rows in sorted order using SELECT FOR UPDATE
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $lockSql = "
                SELECT id, sku, name, unit_cost, selling_price, stock_quantity, status 
                FROM products 
                WHERE id IN ({$placeholders})
                ORDER BY id ASC 
                FOR UPDATE
            ";
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute($productIds);
            $lockedProducts = [];
            while ($row = $lockStmt->fetch()) {
                $lockedProducts[(int)$row['id']] = $row;
            }

            // Validate that all products exist, are active, and have sufficient stock
            $lineItemsPrepared = [];
            $computedSubtotal = 0.0;

            foreach ($consolidatedItems as $pId => $item) {
                if (!isset($lockedProducts[$pId])) {
                    throw new InvalidArgumentException("Product with ID {$pId} not found in catalog.");
                }

                $prod = $lockedProducts[$pId];

                if ($prod['status'] !== 'active') {
                    throw new InvalidArgumentException("Product '{$prod['name']}' ({$prod['sku']}) is not active for sale.");
                }

                $availableStock = (int)$prod['stock_quantity'];
                $requestedQty = (int)$item['quantity'];

                // Zero-Stock check
                if ($availableStock <= 0) {
                    throw new InvalidArgumentException("Product '{$prod['name']}' (SKU: {$prod['sku']}) is completely out of stock (available: 0 units). Checkout halted.");
                }

                // Insufficient Stock check
                if ($requestedQty > $availableStock) {
                    throw new InvalidArgumentException(sprintf(
                        "Insufficient stock for '%s' (SKU: %s). Requested: %d unit(s), but only %d unit(s) available in inventory.",
                        $prod['name'],
                        $prod['sku'],
                        $requestedQty,
                        $availableStock
                    ));
                }

                $unitPrice = ($item['unit_price'] !== null && $item['unit_price'] >= 0) 
                    ? $item['unit_price'] 
                    : (float)$prod['selling_price'];

                $lineSubtotal = round($requestedQty * $unitPrice, 4);
                $computedSubtotal += $lineSubtotal;

                $lineItemsPrepared[] = [
                    'product_id' => $pId,
                    'sku' => $prod['sku'],
                    'name' => $prod['name'],
                    'unit_cost' => (float)$prod['unit_cost'],
                    'unit_price' => $unitPrice,
                    'quantity' => $requestedQty,
                    'subtotal' => $lineSubtotal,
                    'previous_stock' => $availableStock,
                    'new_stock' => $availableStock - $requestedQty,
                ];
            }

            // Calculate final financial amounts
            $subtotal = round($computedSubtotal, 4);
            $taxAmount = round($taxInput, 4);
            $discountAmount = min($subtotal + $taxAmount, round($discountInput, 4));
            $totalAmount = round($subtotal + $taxAmount - $discountAmount, 4);

            // Generate unique invoice number
            $invoiceNumber = $this->generateUniqueInvoiceNumber($pdo);

            // 1. Insert invoice header
            $headerStmt = $pdo->prepare('
                INSERT INTO invoices (
                    invoice_number, user_id, customer_name, customer_email,
                    customer_phone, subtotal, tax_amount, discount_amount,
                    total_amount, status, payment_method, notes,
                    created_at, updated_at
                ) VALUES (
                    :invoice_number, :user_id, :customer_name, :customer_email,
                    :customer_phone, :subtotal, :tax_amount, :discount_amount,
                    :total_amount, "paid", :payment_method, :notes,
                    NOW(), NOW()
                )
            ');

            $headerStmt->execute([
                ':invoice_number' => $invoiceNumber,
                ':user_id' => $userId,
                ':customer_name' => $customerName,
                ':customer_email' => $customerEmail,
                ':customer_phone' => $customerPhone,
                ':subtotal' => $subtotal,
                ':tax_amount' => $taxAmount,
                ':discount_amount' => $discountAmount,
                ':total_amount' => $totalAmount,
                ':payment_method' => $paymentMethod,
                ':notes' => $notes,
            ]);

            $invoiceId = (int)$pdo->lastInsertId();

            // 2. Insert line items, decrement inventory, and write audit entries
            $itemStmt = $pdo->prepare('
                INSERT INTO invoice_items (
                    invoice_id, product_id, quantity, unit_price, subtotal,
                    created_at, updated_at
                ) VALUES (
                    :invoice_id, :product_id, :quantity, :unit_price, :subtotal,
                    NOW(), NOW()
                )
            ');

            $decrementStmt = $pdo->prepare('
                UPDATE products SET
                    stock_quantity = stock_quantity - :quantity,
                    updated_at = NOW()
                WHERE id = :id
            ');

            $ledgerStmt = $pdo->prepare('
                INSERT INTO audit_ledger (
                    transaction_type, entry_category, entity_type, entity_id,
                    product_id, user_id, quantity_delta, unit_cost, total_amount,
                    stock_balance_after, notes, created_at
                ) VALUES (
                    "SALE", :category, "invoices", :entity_id,
                    :product_id, :user_id, :quantity_delta, :unit_cost, :total_amount,
                    :balance_after, :notes, NOW()
                )
            ');

            foreach ($lineItemsPrepared as $item) {
                // Insert line item
                $itemStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':subtotal' => $item['subtotal'],
                ]);

                // Atomically decrement stock
                $decrementStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':id' => $item['product_id'],
                ]);

                // Inventory reduction audit ledger entry
                $ledgerStmt->execute([
                    ':category' => 'INVENTORY',
                    ':entity_id' => $invoiceId,
                    ':product_id' => $item['product_id'],
                    ':user_id' => $userId,
                    ':quantity_delta' => -1 * $item['quantity'],
                    ':unit_cost' => $item['unit_cost'],
                    ':total_amount' => $item['subtotal'],
                    ':balance_after' => $item['new_stock'],
                    ':notes' => "Sale invoice {$invoiceNumber} line item deduction ({$item['quantity']}x {$item['sku']})",
                ]);
            }

            // 3. Insert financial revenue recognition audit entry
            $ledgerStmt->execute([
                ':category' => 'FINANCIAL',
                ':entity_id' => $invoiceId,
                ':product_id' => null,
                ':user_id' => $userId,
                ':quantity_delta' => 0,
                ':unit_cost' => 0.0,
                ':total_amount' => $totalAmount,
                ':balance_after' => null,
                ':notes' => "Gross revenue recognized for invoice {$invoiceNumber}",
            ]);

            // Commit atomic transaction
            $pdo->commit();

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $customerName,
                'total_amount' => $totalAmount,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'payment_method' => $paymentMethod,
                'items_count' => count($lineItemsPrepared),
                'items' => $lineItemsPrepared,
                'message' => "Invoice {$invoiceNumber} successfully authorized and processed.",
            ];

        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new RuntimeException('Checkout transaction failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve a complete invoice with line items, seller, and product data
     */
    public function getInvoice(int $id): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT 
                i.*,
                u.name AS operator_name,
                u.email AS operator_email
            FROM invoices i
            INNER JOIN users u ON i.user_id = u.id
            WHERE i.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return null;
        }

        // Fetch line items
        $itemStmt = $pdo->prepare('
            SELECT 
                ii.*,
                p.sku,
                p.name AS product_name,
                p.description AS product_description
            FROM invoice_items ii
            INNER JOIN products p ON ii.product_id = p.id
            WHERE ii.invoice_id = :invoice_id
            ORDER BY ii.id ASC
        ');
        $itemStmt->execute([':invoice_id' => $id]);
        $invoice['items'] = $itemStmt->fetchAll();

        return $invoice;
    }

    /**
     * Retrieve an invoice by its unique invoice number
     */
    public function getInvoiceByNumber(string $invoiceNumber): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM invoices WHERE invoice_number = :number LIMIT 1');
        $stmt->execute([':number' => trim($invoiceNumber)]);
        $row = $stmt->fetch();

        return $row ? $this->getInvoice((int)$row['id']) : null;
    }

    /**
     * Retrieve all invoices with filtering options
     *
     * @param array{q?: string, status?: string, user_id?: int, limit?: int} $filters
     * @return array
     */
    public function getAllInvoices(array $filters = []): array
    {
        $pdo = Database::getConnection();

        $sql = '
            SELECT 
                i.id,
                i.invoice_number,
                i.customer_name,
                i.customer_email,
                i.customer_phone,
                i.subtotal,
                i.tax_amount,
                i.discount_amount,
                i.total_amount,
                i.status,
                i.payment_method,
                i.created_at,
                u.name AS operator_name,
                (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) AS item_count
            FROM invoices i
            INNER JOIN users u ON i.user_id = u.id
            WHERE 1=1
        ';

        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (i.invoice_number LIKE :query OR i.customer_name LIKE :query OR i.customer_email LIKE :query)';
            $params[':query'] = '%' . trim($filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND i.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= ' AND i.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }

        $limit = isset($filters['limit']) ? max(1, min(200, (int)$filters['limit'])) : 50;
        $sql .= " ORDER BY i.id DESC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Compute total sales volume and revenue
     *
     * @return array{total_revenue: float, invoice_count: int}
     */
    public function getSalesSummary(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('
            SELECT 
                COALESCE(SUM(total_amount), 0.0) AS total_revenue,
                COUNT(*) AS invoice_count
            FROM invoices
            WHERE status = "paid"
        ');
        $row = $stmt->fetch();

        return [
            'total_revenue' => (float)($row['total_revenue'] ?? 0.0),
            'invoice_count' => (int)($row['invoice_count'] ?? 0),
        ];
    }

    /**
     * Helper to generate a collision-free sequential/random invoice number
     */
    private function generateUniqueInvoiceNumber(PDO $pdo): string
    {
        $year = date('Y');
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            // Count invoices this year to generate standard sequential-style number
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM invoices WHERE invoice_number LIKE 'INV-{$year}-%'");
            $seq = ((int)$stmt->fetch()['total']) + 1 + $i;
            $candidate = sprintf('INV-%s-%04d', $year, $seq);

            $checkStmt = $pdo->prepare('SELECT id FROM invoices WHERE invoice_number = :num LIMIT 1');
            $checkStmt->execute([':num' => $candidate]);
            if (!$checkStmt->fetch()) {
                return $candidate;
            }
        }

        // Fallback to random token if collisions persist
        return 'INV-' . $year . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
}

