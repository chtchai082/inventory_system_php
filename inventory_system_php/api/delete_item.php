<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/item_functions.php';

requireAdmin(); // Ensure only admins can access

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expecting JSON input, but item_id could also be sent as form-data.
    // For consistency with other POST endpoints like add_item and update_item, we'll expect JSON.
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $itemId = $input['item_id'] ?? null;

    if ($itemId === null || !filter_var($itemId, FILTER_VALIDATE_INT) || (int)$itemId <= 0) {
        http_response_code(400); // Bad Request
        $response['message'] = 'Valid Item ID is required.';
    } else {
        $itemId = (int)$itemId;

        if ($pdo) {
            // Check if item exists before attempting delete to provide a more specific message
            // although deleteItem itself also implicitly handles non-existent items by returning false.
            $itemExists = getItemById($pdo, $itemId);
            if (!$itemExists) {
                http_response_code(404); // Not Found
                $response['message'] = 'Item not found.';
            } else {
                $deleteSuccess = deleteItem($pdo, $itemId);
                if ($deleteSuccess) {
                    $response['success'] = true;
                    $response['message'] = 'Item deleted successfully.';
                } else {
                    // This could be due to the item being in use (foreign key constraint) or another DB error.
                    http_response_code(409); // Conflict (or 500 for general DB error)
                    $response['message'] = 'Failed to delete item. It might be in use (active borrow requests) or another error occurred.';
                }
            }
        } else {
            http_response_code(500);
            $response['message'] = 'Database connection error.';
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

echo json_encode($response);
?>
