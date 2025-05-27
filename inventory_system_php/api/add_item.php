<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/item_functions.php';

requireAdmin(); // Ensure only admins can access

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assuming JSON input
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $name = $input['name'] ?? null;
    $description = $input['description'] ?? ''; // Default to empty string if not provided
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $quantity = $input['quantity'] ?? null;
    $imageUrl = trim($input['image_url'] ?? '');

    // Enhanced validation
    if (empty($name)) {
        http_response_code(400);
        $response['message'] = 'Item name is required.';
    } elseif (strlen($name) > 255) {
        http_response_code(400);
        $response['message'] = 'Item name must be 255 characters or less.';
    } elseif (!empty($description) && strlen($description) > 1000) { // Description is optional
        http_response_code(400);
        $response['message'] = 'Description must be 1000 characters or less.';
    } elseif ($quantity === null || !is_int($quantity) || $quantity <= 0) { // Ensure it's an integer
        http_response_code(400);
        $response['message'] = 'Quantity must be a positive integer.';
    } elseif (!empty($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        $response['message'] = 'Invalid image URL format.';
    } elseif (!empty($imageUrl) && strlen($imageUrl) > 255) {
        http_response_code(400);
        $response['message'] = 'Image URL must be 255 characters or less.';
    } else {
        // $quantity is already (int) from the is_int check or will be cast if it was string "123"
        // but since we check is_int, it should be fine.
        // For safety, ensure $quantity is int if previous checks were different.
        $quantity = (int)$quantity;


        if ($pdo) {
            // Sanitize inputs before passing to function (though PDO handles SQL injection)
            // htmlspecialchars is for XSS prevention if data is directly echoed to HTML,
            // which is not the case here but good practice for data that might be.
            // For DB storage, direct values are fine with PDO.
            $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            $imageUrl = !empty($imageUrl) ? htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') : null;


            $newItemId = addItem($pdo, $name, $description, $quantity, $imageUrl);
            if ($newItemId) {
                $response['success'] = true;
                $response['message'] = 'Item added successfully.';
                $response['item_id'] = $newItemId;
            } else {
                $response['message'] = 'Failed to add item to the database.';
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
