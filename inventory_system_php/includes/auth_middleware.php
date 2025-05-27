<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode([
            "success" => false,
            "message" => "Authentication required. Please log in."
        ]);
        exit();
    }
}

function requireAdmin() {
    requireLogin(); // First, ensure the user is logged in.

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode([
            "success" => false,
            "message" => "Administrator access required."
        ]);
        exit();
    }
}

// Note: It's good practice to ensure this file doesn't output anything
// if it's included and functions are not called directly, to prevent
// "headers already sent" errors if JSON output is attempted later.
// The functions themselves handle setting the content type and exiting.
?>
