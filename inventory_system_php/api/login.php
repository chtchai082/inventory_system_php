<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../includes/user_functions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Validate presence of required fields first
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        $response['message'] = 'Username and password are required fields.';
    } else {
        $username = trim($input['username']);
        $password = $input['password']; // Password is not trimmed

        if (empty($username)) {
            http_response_code(400);
            $response['message'] = 'Username cannot be empty.';
        } elseif (empty($password)) {
            http_response_code(400);
            $response['message'] = 'Password cannot be empty.';
        } elseif (strlen($username) > 255) { // Basic length check
             http_response_code(400);
            $response['message'] = 'Username is too long.';
        }
        // Password length can be very long, so usually not checked here beyond empty
        else {
            // Sanitize username before using in SQL (getUserByUsername should use prepared statements)
            $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

            if ($pdo) {
                $user = getUserByUsername($pdo, $username);

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    session_regenerate_id(true); // Prevent session fixation

                    $response['success'] = true;
                    $response['message'] = 'Login successful.';
                    $response['user'] = [
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role']
                    ];
                } else {
                    http_response_code(401); // Unauthorized
                    $response['message'] = 'Invalid credentials.';
                }
            } else {
                http_response_code(500);
                $response['message'] = 'Database connection error.';
            }
        }
    }
} else {
    http_response_code(405);
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

echo json_encode($response);
?>
