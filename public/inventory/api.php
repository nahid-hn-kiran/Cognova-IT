<?php
/**
 * Inventory Management & Stock-In AJAX API Endpoint
 *
 * Restricted to roles: Admin, Store Manager
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\ProductService;
use App\Services\InventoryService;

// Enforce role guard: Admin or Store Manager
requireRole(['Admin', 'Store Manager']);

header('Content-Type: application/json; charset=utf-8');

$productService = new ProductService();
$inventoryService = new InventoryService();
$currentUser = currentUser();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'get_product':
                $id = (int)($_GET['id'] ?? 0);
                $product = $productService->getById($id);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }
                echo json_encode(['success' => true, 'product' => $product]);
                exit;

            case 'list_products':
                $q = $_GET['q'] ?? '';
                $lowStock = !empty($_GET['low_stock']);
                $products = $productService->getAll(['q' => $q, 'low_stock' => $lowStock]);
                echo json_encode(['success' => true, 'products' => $products]);
                exit;

            case 'inbound_history':
                $productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
                $history = $inventoryService->getInboundHistory(50, $productId);
                echo json_encode(['success' => true, 'history' => $history]);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Invalid GET action: '{$action}'"]);
                exit;
        }
    }

    if ($method === 'POST') {
        // Read JSON body or form post
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = [];
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?: [];
        } else {
            $data = $_POST;
        }

        // Verify CSRF Token
        $csrfToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!verifyCsrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF security token mismatch or missing.']);
            exit;
        }

        $postAction = $data['action'] ?? $action;

        switch ($postAction) {
            case 'create_product':
                $data['user_id'] = $currentUser['id'];
                $product = $productService->create($data);
                echo json_encode([
                    'success' => true,
                    'message' => "Product '{$product['name']}' created successfully.",
                    'product' => $product,
                ]);
                exit;

            case 'update_product':
                $id = (int)($data['id'] ?? 0);
                if ($id <= 0) {
                    throw new InvalidArgumentException('Valid product ID required for update.');
                }
                $product = $productService->update($id, $data);
                echo json_encode([
                    'success' => true,
                    'message' => "Product '{$product['name']}' updated successfully.",
                    'product' => $product,
                ]);
                exit;

            case 'stock_in':
                $productId = (int)($data['product_id'] ?? 0);
                $quantity = (int)($data['quantity'] ?? 0);
                $referenceNo = !empty($data['reference_no']) ? trim($data['reference_no']) : null;
                $notes = !empty($data['notes']) ? trim($data['notes']) : null;
                $unitCost = (isset($data['unit_cost']) && $data['unit_cost'] !== '') ? (float)$data['unit_cost'] : null;
                $supplierName = !empty($data['supplier_name']) ? trim($data['supplier_name']) : null;
                $batchNo = !empty($data['batch_no']) ? trim($data['batch_no']) : null;

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

                echo json_encode($result);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Invalid POST action: '{$postAction}'"]);
                exit;
        }
    }

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

