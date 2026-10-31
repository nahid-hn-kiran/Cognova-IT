<?php

namespace App\Services;

use App\Config\Database;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Product Management Service
 *
 * Handles catalog creation, updates, querying, and low-stock threshold detection.
 */
class ProductService
{
    /**
     * Retrieve all products matching optional filter criteria
     *
     * @param array{q?: string, low_stock?: bool, status?: string} $filters
     * @return array<array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $pdo = Database::getConnection();

        $sql = '
            SELECT 
                p.id,
                p.sku,
                p.name,
                p.description,
                p.unit_cost,
                p.selling_price,
                p.stock_quantity,
                p.reorder_level,
                p.status,
                p.created_at,
                p.updated_at,
                (p.stock_quantity <= p.reorder_level) AS is_low_stock,
                CASE 
                    WHEN p.selling_price > 0 
                    THEN ROUND(((p.selling_price - p.unit_cost) / p.selling_price) * 100, 2)
                    ELSE 0 
                END AS margin_percentage
            FROM products p
            WHERE 1=1
        ';

        $params = [];

        // Search filter (SKU or Name)
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.sku LIKE :query OR p.name LIKE :query)';
            $params[':query'] = '%' . trim($filters['q']) . '%';
        }

        // Status filter
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params[':status'] = $filters['status'];
        } else {
            // Default: show active products unless specified
            if (!isset($filters['include_archived']) || !$filters['include_archived']) {
                $sql .= ' AND p.status != "archived"';
            }
        }

        // Low stock filter
        if (!empty($filters['low_stock'])) {
            $sql .= ' AND p.stock_quantity <= p.reorder_level';
        }

        $sql .= ' ORDER BY (p.stock_quantity <= p.reorder_level) DESC, p.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get a single product by ID
     */
    public function getById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT 
                p.*,
                (p.stock_quantity <= p.reorder_level) AS is_low_stock,
                CASE 
                    WHEN p.selling_price > 0 
                    THEN ROUND(((p.selling_price - p.unit_cost) / p.selling_price) * 100, 2)
                    ELSE 0 
                END AS margin_percentage
            FROM products p 
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    /**
     * Get a single product by SKU
     */
    public function getBySku(string $sku): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE sku = :sku LIMIT 1');
        $stmt->execute([':sku' => trim($sku)]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    /**
     * Count products currently at or below their reorder level
     */
    public function getLowStockCount(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('
            SELECT COUNT(*) AS total 
            FROM products 
            WHERE stock_quantity <= reorder_level 
              AND status = "active"
        ');
        return (int)($stmt->fetch()['total'] ?? 0);
    }

    /**
     * Create a new product in the catalog
     *
     * @param array{
     *     sku: string,
     *     name: string,
     *     description?: string,
     *     unit_cost: float,
     *     selling_price: float,
     *     stock_quantity?: int,
     *     reorder_level?: int,
     *     status?: string,
     *     user_id?: int
     * } $data
     * @return array
     */
    public function create(array $data): array
    {
        $pdo = Database::getConnection();

        $sku = strtoupper(trim($data['sku'] ?? ''));
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $unitCost = (float)($data['unit_cost'] ?? 0.0);
        $sellingPrice = (float)($data['selling_price'] ?? 0.0);
        $stockQuantity = max(0, (int)($data['stock_quantity'] ?? 0));
        $reorderLevel = max(0, (int)($data['reorder_level'] ?? 10));
        $status = in_array($data['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $data['status'] : 'active';
        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;

        if (empty($sku)) {
            throw new InvalidArgumentException('Product SKU is required.');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Product name is required.');
        }
        if ($unitCost < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }
        if ($sellingPrice < 0) {
            throw new InvalidArgumentException('Selling price cannot be negative.');
        }

        // Check SKU uniqueness
        if ($this->getBySku($sku)) {
            throw new InvalidArgumentException("A product with SKU '{$sku}' already exists.");
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO products (
                    sku, name, description, unit_cost, selling_price, 
                    stock_quantity, reorder_level, status, created_at, updated_at
                ) VALUES (
                    :sku, :name, :description, :unit_cost, :selling_price, 
                    :stock_quantity, :reorder_level, :status, NOW(), NOW()
                )
            ');

            $stmt->execute([
                ':sku' => $sku,
                ':name' => $name,
                ':description' => $description ?: null,
                ':unit_cost' => $unitCost,
                ':selling_price' => $sellingPrice,
                ':stock_quantity' => $stockQuantity,
                ':reorder_level' => $reorderLevel,
                ':status' => $status,
            ]);

            $productId = (int)$pdo->lastInsertId();

            // If an initial stock quantity was entered, record an initial ledger entry
            if ($stockQuantity > 0) {
                $totalValuation = $stockQuantity * $unitCost;
                $ledgerStmt = $pdo->prepare('
                    INSERT INTO audit_ledger (
                        transaction_type, entry_category, entity_type, entity_id,
                        product_id, user_id, quantity_delta, unit_cost, total_amount,
                        stock_balance_after, notes, created_at
                    ) VALUES (
                        "STOCK_IN", "INVENTORY", "products", :entity_id,
                        :product_id, :user_id, :quantity_delta, :unit_cost, :total_amount,
                        :balance_after, :notes, NOW()
                    )
                ');
                $ledgerStmt->execute([
                    ':entity_id' => $productId,
                    ':product_id' => $productId,
                    ':user_id' => $userId,
                    ':quantity_delta' => $stockQuantity,
                    ':unit_cost' => $unitCost,
                    ':total_amount' => $totalValuation,
                    ':balance_after' => $stockQuantity,
                    ':notes' => 'Initial catalog creation stock count',
                ]);
            }

            $pdo->commit();
            return $this->getById($productId);

        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to create product: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing product in the catalog
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update(int $id, array $data): array
    {
        $pdo = Database::getConnection();
        $existing = $this->getById($id);

        if (!$existing) {
            throw new InvalidArgumentException("Product with ID {$id} not found.");
        }

        $sku = strtoupper(trim($data['sku'] ?? $existing['sku']));
        $name = trim($data['name'] ?? $existing['name']);
        $description = array_key_exists('description', $data) ? trim((string)$data['description']) : $existing['description'];
        $unitCost = array_key_exists('unit_cost', $data) ? (float)$data['unit_cost'] : (float)$existing['unit_cost'];
        $sellingPrice = array_key_exists('selling_price', $data) ? (float)$data['selling_price'] : (float)$existing['selling_price'];
        $reorderLevel = array_key_exists('reorder_level', $data) ? max(0, (int)$data['reorder_level']) : (int)$existing['reorder_level'];
        $status = array_key_exists('status', $data) ? $data['status'] : $existing['status'];

        if (empty($sku)) {
            throw new InvalidArgumentException('Product SKU cannot be empty.');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }
        if ($unitCost < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }
        if ($sellingPrice < 0) {
            throw new InvalidArgumentException('Selling price cannot be negative.');
        }

        // Check SKU uniqueness if changed
        if ($sku !== $existing['sku']) {
            $duplicate = $this->getBySku($sku);
            if ($duplicate && (int)$duplicate['id'] !== $id) {
                throw new InvalidArgumentException("A product with SKU '{$sku}' already exists.");
            }
        }

        $stmt = $pdo->prepare('
            UPDATE products SET
                sku = :sku,
                name = :name,
                description = :description,
                unit_cost = :unit_cost,
                selling_price = :selling_price,
                reorder_level = :reorder_level,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute([
            ':sku' => $sku,
            ':name' => $name,
            ':description' => $description ?: null,
            ':unit_cost' => $unitCost,
            ':selling_price' => $sellingPrice,
            ':reorder_level' => $reorderLevel,
            ':status' => $status,
            ':id' => $id,
        ]);

        return $this->getById($id);
    }
}

