<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/borrow_functions.php';
// item_functions.php is included by borrow_functions.php if needed for getItemById

requireLogin(); // Ensure user is logged in

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $itemId = $input['item_id'] ?? null;
    $quantityRequested = $input['quantity_requested'] ?? null;
    $expectedReturnDate = $input['expected_return_date'] ?? null; // Can be null, if provided, trim
    if ($expectedReturnDate !== null) {
        $expectedReturnDate = trim($expectedReturnDate);
        if (empty($expectedReturnDate)) { // Treat empty string after trim as null
            $expectedReturnDate = null;
        }
    }
    
    $currentDate = date('Y-m-d');

    // Enhanced validation
    if ($itemId === null || !is_int($itemId) || $itemId <= 0) {
        http_response_code(400);
        $response['message'] = 'Item ID must be a positive integer.';
    } elseif ($quantityRequested === null || !is_int($quantityRequested) || $quantityRequested <= 0) {
        http_response_code(400);
        $response['message'] = 'Quantity requested must be a positive integer.';
    } elseif ($expectedReturnDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedReturnDate)) {
        http_response_code(400);
        $response['message'] = 'Invalid expected return date format. Please use YYYY-MM-DD.';
    } elseif ($expectedReturnDate !== null && $expectedReturnDate < $currentDate) {
        http_response_code(400);
        $response['message'] = 'Expected return date cannot be in the past.';
    }
     else {
        if ($pdo) {
            // $itemId and $quantityRequested are already validated as int and positive.
            // $expectedReturnDate is validated for format.
            // htmlspecialchars is not strictly needed for these fields when passed to createBorrowRequest,
            // as they are IDs, numbers, or dates, and PDO handles SQLi.

            $result = createBorrowRequest($pdo, $userId, $itemId, $quantityRequested, $expectedReturnDate);
            
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'Borrow request submitted successfully.';
                $response['request_id'] = $result['request_id'];
            } else {
                // Determine appropriate HTTP status code based on message
                if (strpos($result['message'], 'Insufficient stock') !== false) {
                    http_response_code(409); // Conflict - stock issue
                } elseif (strpos($result['message'], 'Item not found') !== false) {
                    http_response_code(404); // Not found
                } else {
                    http_response_code(500); // Internal server error for other DB issues
                }
                $response['message'] = $result['message'];
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
