<?php

/**
 * Fetches a user by their username.
 *
 * @param PDO $pdo PDO database connection object.
 * @param string $username The username to search for.
 * @return array|false User data as an associative array or false if not found.
 */
function getUserByUsername(PDO $pdo, string $username) {
    $sql = "SELECT id, username, password_hash, full_name, role FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Creates a new user.
 *
 * @param PDO $pdo PDO database connection object.
 * @param string $username
 * @param string $password
 * @param string $fullName
 * @param string $role Role of the user (default: 'Employee').
 * @return bool True on success, false on failure.
 */
function createUser(PDO $pdo, string $username, string $password, string $fullName, string $role = 'Employee') {
    // Check if username already exists
    if (getUserByUsername($pdo, $username)) {
        return false; // Username already exists
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password_hash, full_name, role) VALUES (:username, :password_hash, :full_name, :role)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
    $stmt->bindParam(':full_name', $fullName, PDO::PARAM_STR);
    $stmt->bindParam(':role', $role, PDO::PARAM_STR);

    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // Log error or handle as needed
        // error_log("Error creating user: " . $e->getMessage()); // Example logging
        return false;
    }
}

?>
