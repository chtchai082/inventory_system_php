<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/item_functions.php';

requireLogin(); // Ensure user is logged in

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($pdo) {
        if (isset($_GET['id'])) {
            $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($itemId === false || $itemId <= 0) {
                http_response_code(400); // Bad Request
                $response['message'] = 'Invalid item ID specified.';
            } else {
                $item = getItemById($pdo, $itemId);
                if ($item) {
                    $response['success'] = true;
                    $response['item'] = $item;
                    unset($response['message']); // Remove default error message
                } else {
                    http_response_code(404); // Not Found
                    $response['message'] = 'Item not found.';
                }
            }
        } else {
            $items = getAllItems($pdo);
            $response['success'] = true;
            $response['items'] = $items;
            unset($response['message']); // Remove default error message
        }
    } else {
        http_response_code(500);
        $response['message'] = 'Database connection error.';
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only GET is allowed.';
}

echo json_encode($response);
?>
