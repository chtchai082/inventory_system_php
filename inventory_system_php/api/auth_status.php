<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    echo json_encode([
        'loggedIn' => true,
        'user' => [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null,
            'role' => isset($_SESSION['role']) ? $_SESSION['role'] : null
        ]
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>
