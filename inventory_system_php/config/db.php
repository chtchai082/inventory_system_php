<?php
$host = 'localhost';
$dbname = 'inventory_db';
$user = 'root';
$pass = ''; // Default empty password for XAMPP/WAMP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // For a real application, you would log this error and show a user-friendly message.
    // Never output detailed error messages directly to the user in a production environment.
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed. Please check server logs or contact administrator.",
        // "error_detail" => $e->getMessage() // Uncomment for debugging only, not for production
    ]);
    exit; // Stop script execution if connection fails
}

// The script should return the PDO connection object.
// However, scripts included with 'require' or 'include' make their variables available
// in the including script's scope. So, $pdo will be available.
// If you explicitly want to return it (e.g., from a function), you'd do:
// return $pdo;
// For now, just ensuring $pdo is set is sufficient for the inclusion model.
?>
