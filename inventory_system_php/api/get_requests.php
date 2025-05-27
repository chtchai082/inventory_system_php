<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Provides $pdo
require_once '../includes/auth_middleware.php';
require_once '../includes/borrow_functions.php';

requireLogin(); // Ensure user is logged in

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$pdo) {
        http_response_code(500);
        $response['message'] = 'Database connection error.';
        echo json_encode($response);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
    $requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
    $fetchUserId = filter_input(INPUT_GET, 'fetch_user_id', FILTER_VALIDATE_INT);

    $requests = [];

    if ($requestId) {
        $request = getBorrowRequestById($pdo, $requestId);
        if ($request) {
            // Security check: If user is not admin, they can only fetch their own specific request.
            if ($role !== 'Admin' && $request['user_id'] != $userId) {
                http_response_code(403); // Forbidden
                $response['message'] = 'Access denied. You can only view your own requests.';
            } else {
                $requests = [$request]; // Wrap in array for consistency
                $response['success'] = true;
                unset($response['message']);
            }
        } else {
            http_response_code(404);
            $response['message'] = 'Request not found.';
        }
    } else {
        if ($role === 'Admin') {
            if ($fetchUserId) {
                $requests = getBorrowRequestsByUserId($pdo, $fetchUserId, $status);
            } else {
                $requests = getAllBorrowRequests($pdo, $status);
            }
        } else { // Employee
            $requests = getBorrowRequestsByUserId($pdo, $userId, $status);
        }
        $response['success'] = true;
        unset($response['message']);
    }
    
    if ($response['success']) {
        $response['requests'] = $requests;
    }

} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only GET is allowed.';
}

echo json_encode($response);
?>
