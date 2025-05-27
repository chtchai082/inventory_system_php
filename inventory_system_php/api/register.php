<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/user_functions.php';
require_once '../includes/auth_middleware.php'; // Added for requireAdmin

requireAdmin(); // Protect this endpoint

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    // Using filter_input to sanitize inputs is a good practice, 
    // but for simplicity with JSON POST data, we'll directly access php://input
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Validate presence of required fields first
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['full_name'])) {
        http_response_code(400);
        $response['message'] = 'Username, password, and full name are required fields.';
    } else {
        $username = trim($input['username']);
        $password = $input['password']; // Password is not trimmed, to allow leading/trailing spaces if desired
        $fullName = trim($input['full_name']);
        $role = $input['role'] ?? 'Employee'; // Default role

        // Enhanced Validation
        if (empty($username)) {
            http_response_code(400);
            $response['message'] = 'Username cannot be empty.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            http_response_code(400);
            $response['message'] = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            $response['message'] = 'Username can only contain alphanumeric characters and underscores.';
        } elseif (empty($password)) {
            http_response_code(400);
            $response['message'] = 'Password cannot be empty.';
        } elseif (strlen($password) < 8) {
            http_response_code(400);
            $response['message'] = 'Password must be at least 8 characters long.';
        } elseif (empty($fullName)) {
            http_response_code(400);
            $response['message'] = 'Full name cannot be empty.';
        } elseif (strlen($fullName) > 255) {
            http_response_code(400);
            $response['message'] = 'Full name must be 255 characters or less.';
        } elseif (!in_array($role, ['Admin', 'Employee'])) {
            http_response_code(400);
            $response['message'] = 'Invalid role specified. Must be "Admin" or "Employee".';
        } else {
            // Attempt to create user
            // The $pdo variable comes from db.php (already checked for connection in real app)
            // Sanitize for XSS before passing to createUser (though user_functions should handle DB safety)
            $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $fullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            // Role is from a fixed set, no need to htmlspecialchars. Password is hashed.

            if ($pdo) { // $pdo comes from db.php
                $creationSuccess = createUser($pdo, $username, $password, $fullName, $role);
                if ($creationSuccess) {
                    $response['success'] = true;
                    $response['message'] = 'User registered successfully.';
                } else {
                    // Check if user exists (createUser might fail for other reasons too)
                    if (getUserByUsername($pdo, $username)) { // Assuming createUser returns false if user exists
                        http_response_code(409); // Conflict
                        $response['message'] = 'Username already exists.';
                    } else {
                        http_response_code(500);
                        $response['message'] = 'Failed to register user. Please try again or contact support.';
                    }
                }
            } else {
                http_response_code(500);
                $response['message'] = 'Database connection error.';
            }
        }
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

echo json_encode($response);
?>
