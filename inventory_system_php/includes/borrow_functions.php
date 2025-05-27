<?php
require_once __DIR__ . '/../config/db.php'; // Provides $pdo
require_once __DIR__ . '/item_functions.php'; // Provides item-related functions like getItemById and updateItemAvailableQuantity

/**
 * Creates a new borrow request.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $userId ID of the user making the request.
 * @param int $itemId ID of the item being requested.
 * @param int $quantityRequested Quantity of the item requested.
 * @param string|null $expectedReturnDate Expected return date (YYYY-MM-DD).
 * @return array Associative array indicating success or failure.
 */
function createBorrowRequest(PDO $pdo, int $userId, int $itemId, int $quantityRequested, ?string $expectedReturnDate) {
    if ($quantityRequested <= 0) {
        return ['success' => false, 'message' => 'Quantity requested must be positive.'];
    }

    $item = getItemById($pdo, $itemId);
    if (!$item) {
        return ['success' => false, 'message' => 'Item not found.'];
    }

    if ($item['available_quantity'] < $quantityRequested) {
        return ['success' => false, 'message' => 'Insufficient stock available. Requested: ' . $quantityRequested . ', Available: ' . $item['available_quantity']];
    }

    $sql = "INSERT INTO borrow_requests (user_id, item_id, quantity_requested, expected_return_date, status) 
            VALUES (:user_id, :item_id, :quantity_requested, :expected_return_date, 'Pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
    $stmt->bindParam(':quantity_requested', $quantityRequested, PDO::PARAM_INT);
    $stmt->bindParam(':expected_return_date', $expectedReturnDate, PDO::PARAM_STR);

    try {
        if ($stmt->execute()) {
            return ['success' => true, 'request_id' => $pdo->lastInsertId()];
        }
        return ['success' => false, 'message' => 'Database error occurred while creating request.'];
    } catch (PDOException $e) {
        // Log error: error_log("Error creating borrow request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Fetches a single borrow request by its ID, joining with user and item names.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $requestId The ID of the borrow request.
 * @return array|false Request data or false if not found.
 */
function getBorrowRequestById(PDO $pdo, int $requestId) {
    $sql = "SELECT 
                br.*, 
                u.full_name AS user_name, 
                i.name AS item_name,
                i.image_url AS item_image_url
            FROM borrow_requests br
            JOIN users u ON br.user_id = u.id
            JOIN items i ON br.item_id = i.id
            WHERE br.id = :request_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches all borrow requests, optionally filtered by status.
 *
 * @param PDO $pdo PDO database connection object.
 * @param string|null $status Optional status to filter by.
 * @return array An array of borrow requests.
 */
function getAllBorrowRequests(PDO $pdo, ?string $status = null) {
    $sql = "SELECT 
                br.*, 
                u.full_name AS user_name, 
                i.name AS item_name,
                i.image_url AS item_image_url,
                admin.full_name AS admin_name
            FROM borrow_requests br
            JOIN users u ON br.user_id = u.id
            JOIN items i ON br.item_id = i.id
            LEFT JOIN users admin ON br.last_updated_by_admin_id = admin.id";
    
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= " WHERE br.status = :status";
        $params[':status'] = $status;
    }
    $sql .= " ORDER BY br.request_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches borrow requests for a specific user, optionally filtered by status.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $userId ID of the user.
 * @param string|null $status Optional status to filter by.
 * @return array An array of borrow requests.
 */
function getBorrowRequestsByUserId(PDO $pdo, int $userId, ?string $status = null) {
    $sql = "SELECT 
                br.*, 
                i.name AS item_name,
                i.image_url AS item_image_url,
                admin.full_name AS admin_name 
            FROM borrow_requests br
            JOIN items i ON br.item_id = i.id
            LEFT JOIN users admin ON br.last_updated_by_admin_id = admin.id
            WHERE br.user_id = :user_id";

    $params = [':user_id' => $userId];
    if ($status !== null && $status !== '') {
        $sql .= " AND br.status = :status";
        $params[':status'] = $status;
    }
    $sql .= " ORDER BY br.request_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Updates the status of a borrow request.
 *
 * @param PDO $pdo PDO database connection object.
 * @param int $requestId ID of the request to update.
 * @param string $newStatus New status for the request.
 * @param int $adminId ID of the admin performing the update.
 * @param string|null $adminRemarks Remarks from the admin.
 * @param string|null $actualReturnDate Actual return date (YYYY-MM-DD), required if newStatus is 'Returned'.
 * @return array Associative array indicating success or failure.
 */
function updateBorrowRequestStatus(PDO $pdo, int $requestId, string $newStatus, int $adminId, ?string $adminRemarks, ?string $actualReturnDate = null) {
    $validStatuses = ['Pending', 'Approved', 'Rejected', 'Returned', 'Cancelled', 'Overdue'];
    if (!in_array($newStatus, $validStatuses)) {
        return ['success' => false, 'message' => 'Invalid status value provided.'];
    }

    $request = getBorrowRequestById($pdo, $requestId);
    if (!$request) {
        return ['success' => false, 'message' => 'Borrow request not found.'];
    }

    if ($request['status'] === $newStatus) {
        return ['success' => false, 'message' => "Request is already marked as {$newStatus}."];
    }
    
    // Prevent certain transitions, e.g., from Returned to Approved
    if (in_array($request['status'], ['Returned', 'Rejected', 'Cancelled']) && in_array($newStatus, ['Approved', 'Pending'])) {
        return ['success' => false, 'message' => "Cannot change status from {$request['status']} to {$newStatus}."];
    }
    if ($request['status'] === 'Approved' && $newStatus === 'Pending') {
         return ['success' => false, 'message' => "Cannot change status from Approved to Pending."];
    }


    $pdo->beginTransaction();
    try {
        // Handle item quantity adjustments
        if ($newStatus === 'Approved' && $request['status'] !== 'Approved') {
            $updateQtySuccess = updateItemAvailableQuantity($pdo, $request['item_id'], -$request['quantity_requested']);
            if (!$updateQtySuccess) {
                $pdo->rollBack();
                $item = getItemById($pdo, $request['item_id']); // Get current stock
                return ['success' => false, 'message' => 'Failed to update item quantity or stock became insufficient. Available: '.($item ? $item['available_quantity'] : 'N/A')];
            }
        } elseif ($newStatus === 'Returned') {
            if (empty($actualReturnDate)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Actual return date is required when status is "Returned".'];
            }
            // Only update quantity if it was previously approved and quantity deducted
            if ($request['status'] === 'Approved' || $request['status'] === 'Overdue') {
                 $updateQtySuccess = updateItemAvailableQuantity($pdo, $request['item_id'], +$request['quantity_requested']);
                if (!$updateQtySuccess) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Failed to update item quantity upon return.'];
                }
            }
        } elseif ($newStatus === 'Cancelled' || $newStatus === 'Rejected') {
            // If request was 'Approved' and is now being 'Cancelled' or 'Rejected', restore item quantity
            if ($request['status'] === 'Approved') {
                $updateQtySuccess = updateItemAvailableQuantity($pdo, $request['item_id'], +$request['quantity_requested']);
                if (!$updateQtySuccess) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Failed to restore item quantity upon cancellation/rejection.'];
                }
            }
        }


        $sql = "UPDATE borrow_requests SET 
                    status = :new_status, 
                    admin_remarks = :admin_remarks, 
                    last_updated_by_admin_id = :admin_id, 
                    actual_return_date = :actual_return_date,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :request_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':new_status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':admin_remarks', $adminRemarks, PDO::PARAM_STR);
        $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
        // Bind actual_return_date only if status is 'Returned', otherwise keep existing or set to NULL
        $effectiveReturnDate = ($newStatus === 'Returned') ? $actualReturnDate : $request['actual_return_date']; 
        if ($newStatus === 'Returned' && empty($actualReturnDate)) {
             // If we are here, it means $actualReturnDate was not provided for 'Returned' status,
             // but this should be caught earlier. As a fallback, set to current date.
            $effectiveReturnDate = date('Y-m-d H:i:s');
        }

        $stmt->bindParam(':actual_return_date', $effectiveReturnDate, PDO::PARAM_STR);
        $stmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $pdo->commit();
            return ['success' => true, 'message' => 'Borrow request status updated successfully.'];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to update borrow request status in database.'];
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log error: error_log("Error updating borrow request status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error during status update: ' . $e->getMessage()];
    }
}

?>
