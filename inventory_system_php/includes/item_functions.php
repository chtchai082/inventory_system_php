<?php
require_once __DIR__ . '/../config/db.php'; // Ensures db.php is included relative to this file's location

/**
 * Adds a new item to the inventory.
 *
 * @param PDO $pdo PDO database connection object.
 * @param string $name Name of the item.
 * @param string $description Description of the item.
 * @param int $quantity Total quantity of the item.
 * @param string|null $imageUrl URL of the item's image.
 * @return string|false The ID of the newly inserted item on success, false on failure.
 */
function addItem(PDO $pdo, string $name, string $description, int $quantity, ?string $imageUrl) {
    $sql = "INSERT INTO items (name, description, quantity, available_quantity, image_url) 
            VALUES (:name, :description, :quantity, :available_quantity, :image_url)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindParam(':available_quantity', $quantity, PDO::PARAM_INT); // Initially, available_quantity is same as quantity
    $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);

    try {
        if ($stmt->execute()) {
            return $pdo->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        // Log error (e.g., error_log($e->getMessage());)
        return false;
    }
}

/**
 * Fetches an item by its ID.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $itemId The ID of the item to fetch.
 * @return array|false Item data as an associative array or false if not found.
 */
function getItemById(PDO $pdo, int $itemId) {
    $sql = "SELECT id, name, description, quantity, available_quantity, image_url, created_at, updated_at 
            FROM items WHERE id = :item_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches all items from the inventory.
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of associative arrays, each representing an item.
 */
function getAllItems(PDO $pdo) {
    $sql = "SELECT id, name, description, quantity, available_quantity, image_url, created_at, updated_at 
            FROM items ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Updates an existing item.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $itemId ID of the item to update.
 * @param string $name New name for the item.
 * @param string $description New description for the item.
 * @param int $quantity New total quantity.
 * @param int $availableQuantity New available quantity.
 * @param string|null $imageUrl New image URL.
 * @return bool True on success, false on failure or if validation fails.
 */
function updateItem(PDO $pdo, int $itemId, string $name, string $description, int $quantity, int $availableQuantity, ?string $imageUrl) {
    if ($availableQuantity > $quantity) {
        // error_log("Validation failed: available_quantity cannot exceed quantity.");
        return false; // Validation failed
    }
    if ($availableQuantity < 0 || $quantity < 0) {
        // error_log("Validation failed: quantity and available_quantity cannot be negative.");
        return false;
    }

    $sql = "UPDATE items SET 
                name = :name, 
                description = :description, 
                quantity = :quantity, 
                available_quantity = :available_quantity, 
                image_url = :image_url 
            WHERE id = :item_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindParam(':available_quantity', $availableQuantity, PDO::PARAM_INT);
    $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);

    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // Log error
        return false;
    }
}

/**
 * Deletes an item from the inventory.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $itemId The ID of the item to delete.
 * @return bool True on success, false on failure (e.g., item not found or constraint violation).
 */
function deleteItem(PDO $pdo, int $itemId) {
    // First, check if the item exists
    $item = getItemById($pdo, $itemId);
    if (!$item) {
        return false; // Item not found
    }

    $sql = "DELETE FROM items WHERE id = :item_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
    try {
        return $stmt->execute();
    } catch (PDOException $e) {
        // This will catch errors like foreign key constraint violations
        // error_log("Error deleting item $itemId: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates the available quantity of an item.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $itemId The ID of the item.
 * @param int $quantityChange The amount to change the available quantity by (positive or negative).
 * @return bool True on success, false on failure or if constraints are violated.
 */
function updateItemAvailableQuantity(PDO $pdo, int $itemId, int $quantityChange) {
    $pdo->beginTransaction(); // Start transaction for atomicity

    try {
        // Get current item details
        $sqlSelect = "SELECT quantity, available_quantity FROM items WHERE id = :item_id FOR UPDATE"; // Lock row
        $stmtSelect = $pdo->prepare($sqlSelect);
        $stmtSelect->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $stmtSelect->execute();
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $pdo->rollBack();
            return false; // Item not found
        }

        $currentTotalQuantity = $item['quantity'];
        $currentAvailableQuantity = $item['available_quantity'];
        $newAvailableQuantity = $currentAvailableQuantity + $quantityChange;

        // Validate new available quantity
        if ($newAvailableQuantity < 0 || $newAvailableQuantity > $currentTotalQuantity) {
            $pdo->rollBack();
            return false; // Constraint violation
        }

        // Update available quantity
        $sqlUpdate = "UPDATE items SET available_quantity = :available_quantity WHERE id = :item_id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':available_quantity', $newAvailableQuantity, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        
        if ($stmtUpdate->execute()) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log error
        return false;
    }
}

?>
