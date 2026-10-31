<?php
/**
 * Administrative Controls & Reversal AJAX API Endpoint
 *
 * Restricted strictly to: Admin
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\LedgerService;
use App\Services\UserService;

// Strictly enforce Admin clearance
requireRole(['Admin']);

header('Content-Type: application/json; charset=utf-8');

$ledgerService = new LedgerService();
$userService = new UserService();
$currentUser = currentUser();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
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

        $action = $data['action'] ?? '';

        switch ($action) {
            case 'reverse_invoice':
                $invoiceId = (int)($data['invoice_id'] ?? 0);
                $reason = trim($data['reason'] ?? '');
                $result = $ledgerService->reverseInvoice($invoiceId, $reason, (int)$currentUser['id']);
                echo json_encode($result);
                exit;

            case 'reverse_inbound':
                $inboundId = (int)($data['inbound_id'] ?? 0);
                $reason = trim($data['reason'] ?? '');
                $result = $ledgerService->reverseInbound($inboundId, $reason, (int)$currentUser['id']);
                echo json_encode($result);
                exit;

            case 'create_user':
                $user = $userService->create($data);
                echo json_encode([
                    'success' => true,
                    'message' => "User account '{$user['name']}' created successfully.",
                    'user' => $user,
                ]);
                exit;

            case 'update_user':
                $id = (int)($data['id'] ?? 0);
                $user = $userService->update($id, $data, (int)$currentUser['id']);
                echo json_encode([
                    'success' => true,
                    'message' => "User account '{$user['name']}' updated successfully.",
                    'user' => $user,
                ]);
                exit;

            case 'toggle_user_status':
                $id = (int)($data['id'] ?? 0);
                $status = $data['status'] ?? 'active';
                $user = $userService->toggleStatus($id, $status, (int)$currentUser['id']);
                echo json_encode([
                    'success' => true,
                    'message' => "User '{$user['name']}' status set to '{$status}'.",
                    'user' => $user,
                ]);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Unknown action: '{$action}'"]);
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

