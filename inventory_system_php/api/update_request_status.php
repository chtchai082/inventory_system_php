<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/borrow_functions.php';
// item_functions.php is included by borrow_functions.php

requireAdmin(); // Ensure only admins can access

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = $_SESSION['user_id'];
    
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $requestId = $input['request_id'] ?? null;
    $newStatus = $input['new_status'] ?? null;
    $adminRemarks = isset($input['admin_remarks']) ? trim($input['admin_remarks']) : null; // Trim if set, allow null
    $actualReturnDate = $input['actual_return_date'] ?? null; // Required if new_status is 'Returned'
    if ($actualReturnDate !== null) {
        $actualReturnDate = trim($actualReturnDate);
        if (empty($actualReturnDate)) {
            $actualReturnDate = null;
        }
    }
    $currentDate = date('Y-m-d');
    $validStatuses = ['Pending', 'Approved', 'Rejected', 'Returned', 'Cancelled', 'Overdue'];

    // Enhanced validation
    if ($requestId === null || !is_int($requestId) || $requestId <= 0) {
        http_response_code(400);
        $response['message'] = 'Request ID must be a positive integer.';
    } elseif (empty($newStatus) || !in_array($newStatus, $validStatuses)) {
        http_response_code(400);
        $response['message'] = 'Invalid new status provided. Allowed values: ' . implode(', ', $validStatuses) . '.';
    } elseif ($adminRemarks !== null && strlen($adminRemarks) > 1000) {
        http_response_code(400);
        $response['message'] = 'Admin remarks must be 1000 characters or less.';
    } elseif ($newStatus === 'Returned' && ($actualReturnDate === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actualReturnDate))) {
        http_response_code(400);
        $response['message'] = 'Actual return date (YYYY-MM-DD) is required and must be valid when status is "Returned".';
    } elseif ($newStatus === 'Returned' && $actualReturnDate !== null && $actualReturnDate > $currentDate) {
        http_response_code(400);
        $response['message'] = 'Actual return date cannot be in the future.';
    }
     else {
        if ($pdo) {
            // $requestId is validated as int. $newStatus is from a fixed set.
            // Sanitize adminRemarks for XSS if it were to be displayed directly, though it's for DB.
            $safeAdminRemarks = ($adminRemarks !== null) ? htmlspecialchars($adminRemarks, ENT_QUOTES, 'UTF-8') : null;

            $result = updateBorrowRequestStatus($pdo, $requestId, $newStatus, $adminId, $safeAdminRemarks, $actualReturnDate);
            
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = $result['message'];
            } else {
                // Determine appropriate HTTP status code based on message
                if (strpos($result['message'], 'insufficient') !== false || strpos($result['message'], 'Failed to update item quantity') !== false) {
                    http_response_code(409); // Conflict - stock issue
                } elseif (strpos($result['message'], 'not found') !== false) {
                    http_response_code(404); // Not found
                } elseif (strpos($result['message'], 'Invalid status') !== false || strpos($result['message'], 'already marked as') !== false || strpos($result['message'], 'Cannot change status') !== false) {
                    http_response_code(400); // Bad request due to business logic / state
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
