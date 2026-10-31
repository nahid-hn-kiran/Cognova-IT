<?php

namespace App\Services;

use App\Config\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Inventory & Inbound Stock Management Service
 *
 * Implements atomic inbound stock increments, transactional ledger records,
 * and concurrency-safe inventory adjustments.
 */
class InventoryService
{
    /**
     * Record an inbound stock shipment (Stock-In)
     *
     * Atomically increments the product's stock_quantity, records the inbound shipment,
     * and logs the double-entry event into the audit_ledger.
     *
     * @param int $productId Target product ID
     * @param int $quantity Quantity received (must be positive)
     * @param string|null $referenceNo PO or shipment reference number
     * @param string|null $notes Additional notes or inspection remarks
     * @param float|null $unitCost Purchase unit cost (defaults to product current cost if null)
     * @param string|null $supplierName Supplier or distributor name
     * @param string|null $batchNo Batch or lot tracking number
     * @param int $userId ID of the Store Manager or Admin processing the receipt
     * @return array
     */
    public function recordStockIn(
        int $productId,
        int $quantity,
        ?string $referenceNo = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?string $supplierName = null,
        ?string $batchNo = null,
        int $userId = 1
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Inbound quantity must be a positive integer greater than zero.');
        }

        $pdo = Database::getConnection();

        // Generate default reference if not provided
        $referenceNo = trim((string)$referenceNo);
        if (empty($referenceNo)) {
            $referenceNo = 'INB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        }

        $supplierName = trim((string)$supplierName) ?: null;
        $batchNo = trim((string)$batchNo) ?: null;
        $notes = trim((string)$notes) ?: null;

        $pdo->beginTransaction();

        try {
            // Lock product record for update to prevent race conditions during concurrent stock-ins
            $lockStmt = $pdo->prepare('
                SELECT id, sku, name, unit_cost, selling_price, stock_quantity, status 
                FROM products 
                WHERE id = :id 
                FOR UPDATE
            ');
            $lockStmt->execute([':id' => $productId]);
            $product = $lockStmt->fetch();

            if (!$product) {
                throw new InvalidArgumentException("Product with ID {$productId} does not exist.");
            }

            if ($product['status'] === 'archived') {
                throw new InvalidArgumentException("Cannot receive stock for archived product '{$product['sku']}'.");
            }

            // Determine unit cost
            $costPerUnit = ($unitCost !== null && $unitCost >= 0) ? $unitCost : (float)$product['unit_cost'];
            $totalCost = $quantity * $costPerUnit;
            $previousStock = (int)$product['stock_quantity'];
            $newStock = $previousStock + $quantity;

            // 1. Insert record into stock_inbound
            $inboundStmt = $pdo->prepare('
                INSERT INTO stock_inbound (
                    product_id, user_id, reference_no, supplier_name, quantity,
                    unit_cost, total_cost, batch_no, received_at, notes,
                    created_at, updated_at
                ) VALUES (
                    :product_id, :user_id, :reference_no, :supplier_name, :quantity,
                    :unit_cost, :total_cost, :batch_no, NOW(), :notes,
                    NOW(), NOW()
                )
            ');

            $inboundStmt->execute([
                ':product_id' => $productId,
                ':user_id' => $userId,
                ':reference_no' => $referenceNo,
                ':supplier_name' => $supplierName,
                ':quantity' => $quantity,
                ':unit_cost' => $costPerUnit,
                ':total_cost' => $totalCost,
                ':batch_no' => $batchNo,
                ':notes' => $notes,
            ]);

            $inboundId = (int)$pdo->lastInsertId();

            // 2. Increment stock_quantity in products table
            $updateProductStmt = $pdo->prepare('
                UPDATE products SET 
                    stock_quantity = stock_quantity + :quantity,
                    unit_cost = :unit_cost,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $updateProductStmt->execute([
                ':quantity' => $quantity,
                ':unit_cost' => $costPerUnit,
                ':id' => $productId,
            ]);

            // 3. Insert immutable double-entry into audit_ledger
            $ledgerNote = $notes ? "Stock Inbound ({$referenceNo}): {$notes}" : "Stock Inbound ({$referenceNo})";
            $ledgerStmt = $pdo->prepare('
                INSERT INTO audit_ledger (
                    transaction_type, entry_category, entity_type, entity_id,
                    product_id, user_id, quantity_delta, unit_cost, total_amount,
                    stock_balance_after, notes, created_at
                ) VALUES (
                    "STOCK_IN", "INVENTORY", "stock_inbound", :entity_id,
                    :product_id, :user_id, :quantity_delta, :unit_cost, :total_amount,
                    :stock_balance_after, :notes, NOW()
                )
            ');

            $ledgerStmt->execute([
                ':entity_id' => $inboundId,
                ':product_id' => $productId,
                ':user_id' => $userId,
                ':quantity_delta' => $quantity,
                ':unit_cost' => $costPerUnit,
                ':total_amount' => $totalCost,
                ':stock_balance_after' => $newStock,
                ':notes' => $ledgerNote,
            ]);

            $ledgerId = (int)$pdo->lastInsertId();

            $pdo->commit();

            return [
                'success' => true,
                'inbound_id' => $inboundId,
                'ledger_id' => $ledgerId,
                'reference_no' => $referenceNo,
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'],
                'quantity_added' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'unit_cost' => $costPerUnit,
                'total_cost' => $totalCost,
                'message' => "Successfully received {$quantity} units of '{$product['name']}'. New stock balance: {$newStock}.",
            ];

        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to process inbound stock order: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve inbound shipment history
     *
     * @param int $limit
     * @param int|null $productId
     * @return array
     */
    public function getInboundHistory(int $limit = 50, ?int $productId = null): array
    {
        $pdo = Database::getConnection();

        $sql = '
            SELECT 
                si.id,
                si.reference_no,
                si.supplier_name,
                si.quantity,
                si.unit_cost,
                si.total_cost,
                si.batch_no,
                si.received_at,
                si.notes,
                p.id AS product_id,
                p.sku AS product_sku,
                p.name AS product_name,
                u.id AS user_id,
                u.name AS receiver_name,
                u.email AS receiver_email
            FROM stock_inbound si
            INNER JOIN products p ON si.product_id = p.id
            INNER JOIN users u ON si.user_id = u.id
        ';

        $params = [];
        if ($productId !== null) {
            $sql .= ' WHERE si.product_id = :product_id';
            $params[':product_id'] = $productId;
        }

        $sql .= ' ORDER BY si.received_at DESC, si.id DESC LIMIT ' . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Retrieve audit ledger history for inventory events
     *
     * @param int $limit
     * @param int|null $productId
     * @return array
     */
    public function getLedgerHistory(int $limit = 50, ?int $productId = null): array
    {
        $pdo = Database::getConnection();

        $sql = '
            SELECT 
                al.id,
                al.transaction_type,
                al.entry_category,
                al.entity_type,
                al.entity_id,
                al.quantity_delta,
                al.unit_cost,
                al.total_amount,
                al.stock_balance_after,
                al.notes,
                al.created_at,
                p.sku AS product_sku,
                p.name AS product_name,
                u.name AS user_name
            FROM audit_ledger al
            LEFT JOIN products p ON al.product_id = p.id
            LEFT JOIN users u ON al.user_id = u.id
        ';

        $params = [];
        if ($productId !== null) {
            $sql .= ' WHERE al.product_id = :product_id';
            $params[':product_id'] = $productId;
        }

        $sql .= ' ORDER BY al.id DESC LIMIT ' . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}

