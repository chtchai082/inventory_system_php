<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/item_functions.php';

requireAdmin(); // Ensure only admins can access

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $itemId = $input['item_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $quantity = $input['quantity'] ?? null;
    $availableQuantity = $input['available_quantity'] ?? null;
    $imageUrl = trim($input['image_url'] ?? ''); // Can be empty string, will be treated as null by item_functions if empty

    // Enhanced validation
    if ($itemId === null || !filter_var($itemId, FILTER_VALIDATE_INT) || (int)$itemId <= 0) {
        http_response_code(400);
        $response['message'] = 'Valid Item ID is required and must be a positive integer.';
    } elseif (empty($name)) {
        http_response_code(400);
        $response['message'] = 'Item name is required.';
    } elseif (strlen($name) > 255) {
        http_response_code(400);
        $response['message'] = 'Item name must be 255 characters or less.';
    } elseif (strlen($description) > 1000) { // Description can be empty, but if provided, check length
        http_response_code(400);
        $response['message'] = 'Description must be 1000 characters or less.';
    } elseif ($quantity === null || !is_int($quantity) || $quantity < 0) {
        http_response_code(400);
        $response['message'] = 'Total quantity must be a non-negative integer.';
    } elseif ($availableQuantity === null || !is_int($availableQuantity) || $availableQuantity < 0) {
        http_response_code(400);
        $response['message'] = 'Available quantity must be a non-negative integer.';
    } elseif ($availableQuantity > $quantity) {
        http_response_code(400);
        $response['message'] = 'Available quantity cannot exceed total quantity.';
    } elseif (!empty($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        $response['message'] = 'Invalid image URL format.';
    } elseif (!empty($imageUrl) && strlen($imageUrl) > 255) {
        http_response_code(400);
        $response['message'] = 'Image URL must be 255 characters or less.';
    } else {
        $itemId = (int)$itemId; // Already validated as int
        $quantity = (int)$quantity; // Already validated as int
        $availableQuantity = (int)$availableQuantity; // Already validated as int
        
        // Sanitize for XSS, though for DB storage PDO handles SQLi
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $imageUrl = !empty($imageUrl) ? htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') : null;


        if ($pdo) {
            // Check if item exists before attempting update (already good)
            $existingItem = getItemById($pdo, $itemId); // This is good to keep
            if (!$existingItem) {
                http_response_code(404);
                $response['message'] = 'Item not found with ID ' . $itemId . '.';
            } else {
                $updateSuccess = updateItem($pdo, $itemId, $name, $description, $quantity, $availableQuantity, $imageUrl);
                if ($updateSuccess) {
                    $response['success'] = true;
                    $response['message'] = 'Item updated successfully.';
                } else {
                    // updateItem function already includes validation for available_quantity vs quantity
                    // and for negative quantities. So if it returns false here, it's likely a DB error
                    // or the item_id was valid but became invalid (e.g. deleted between check and update).
                    // The function itself could also return more specific error messages.
                    // For now, a general message.
                    http_response_code(400); // Or 500 if we suspect DB connection issue not caught by $pdo check
                    $response['message'] = 'Failed to update item. Input may be invalid or a database error occurred.';
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
