<?php
/**
 * Sales Invoicing & POS AJAX API Endpoint
 *
 * Restricted to roles: Admin, Sales Operator
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\InvoiceService;
use App\Services\ProductService;

// Enforce role guard: Admin or Sales Operator
requireRole(['Admin', 'Sales Operator']);

header('Content-Type: application/json; charset=utf-8');

$invoiceService = new InvoiceService();
$productService = new ProductService();
$currentUser = currentUser();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'catalog':
                $products = $productService->getAll(['status' => 'active']);
                $catalog = array_map(function ($p) {
                    return [
                        'id' => (int)$p['id'],
                        'sku' => $p['sku'],
                        'name' => $p['name'],
                        'selling_price' => (float)$p['selling_price'],
                        'stock_quantity' => (int)$p['stock_quantity'],
                        'is_out_of_stock' => ((int)$p['stock_quantity'] <= 0),
                    ];
                }, $products);

                echo json_encode(['success' => true, 'catalog' => $catalog]);
                exit;

            case 'get_invoice':
                $id = (int)($_GET['id'] ?? 0);
                $invoice = $invoiceService->getInvoice($id);
                if (!$invoice) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
                    exit;
                }
                echo json_encode(['success' => true, 'invoice' => $invoice]);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Invalid GET action: '{$action}'"]);
                exit;
        }
    }

    if ($method === 'POST') {
        // Parse JSON or form POST
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = [];
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?: [];
        } else {
            $data = $_POST;
        }

        // Verify CSRF
        $csrfToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!verifyCsrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF security token mismatch or missing.']);
            exit;
        }

        $postAction = $data['action'] ?? $action;

        if ($postAction === 'checkout') {
            $headerData = [
                'customer_name' => $data['customer_name'] ?? '',
                'customer_email' => $data['customer_email'] ?? '',
                'customer_phone' => $data['customer_phone'] ?? '',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'tax_amount' => $data['tax_amount'] ?? 0.0,
                'discount_amount' => $data['discount_amount'] ?? 0.0,
                'notes' => $data['notes'] ?? '',
            ];

            $items = $data['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Cart is empty. Please add items before checking out.']);
                exit;
            }

            $result = $invoiceService->createInvoice($headerData, $items, (int)$currentUser['id']);

            $result['redirect_url'] = '/sales/invoice.php?id=' . $result['invoice_id'];
            echo json_encode($result);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Unknown action: '{$postAction}'"]);
        exit;
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

