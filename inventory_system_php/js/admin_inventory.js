document.addEventListener('DOMContentLoaded', function () {
    // Initial Auth Check (Defense in depth)
    if (localStorage.getItem('user_role') !== 'Admin') {
        window.location.href = 'login.html';
        return; // Stop script execution if not admin
    }

    // --- Global variables and selectors ---
    const itemsTableBody = document.getElementById('itemsTableBody');
    const addItemModalEl = document.getElementById('addItemModal');
    const editItemModalEl = document.getElementById('editItemModal');
    const deleteItemModalEl = document.getElementById('deleteItemModal');

    const addItemModal = new bootstrap.Modal(addItemModalEl);
    const editItemModal = new bootstrap.Modal(editItemModalEl);
    const deleteItemModal = new bootstrap.Modal(deleteItemModalEl);

    const addItemForm = document.getElementById('addItemForm');
    const editItemForm = document.getElementById('editItemForm');

    const showAddItemModalBtn = document.getElementById('showAddItemModalBtn'); // Not strictly needed if using data-bs-toggle
    const saveItemBtn = document.getElementById('saveItemBtn');
    const updateItemBtn = document.getElementById('updateItemBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const logoutBtn = document.getElementById('logoutBtn');

    const addItemErrorDiv = document.getElementById('addItemError');
    const editItemErrorDiv = document.getElementById('editItemError');
    const deleteItemErrorDiv = document.getElementById('deleteItemError');
    // const successAlertEl = document.getElementById('successAlert'); // Handled by global showSuccessAlert
    // const successAlert = successAlertEl ? new bootstrap.Alert(successAlertEl) : null; // Not needed if using global

    // showSuccessAlert, displayError, clearError are now in utils.js

    // --- Fetch Items and Display ---
    async function fetchItemsAndDisplay() {
        try {
            const data = await fetchAPI('api/get_items.php'); // Use fetchAPI

            itemsTableBody.innerHTML = ''; // Clear existing rows

            if (data.items) { // fetchAPI returns data directly, success is implied if no error
                if (data.items.length > 0) {
                    data.items.forEach(item => {
                        const row = itemsTableBody.insertRow();
                        row.innerHTML = `
                            <td>${item.id}</td>
                            <td>${item.name}</td>
                            <td>${item.description ? item.description.substring(0, 50) + (item.description.length > 50 ? '...' : '') : ''}</td>
                            <td>${item.quantity}</td>
                            <td>${item.available_quantity}</td>
                            <td>${item.image_url ? `<a href="${item.image_url}" target="_blank">View</a>` : 'N/A'}</td>
                            <td>
                            <button class="btn btn-sm btn-warning edit-btn" data-item-id="${item.id}" title="Edit" aria-label="Edit item ${item.id}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            <button class="btn btn-sm btn-danger delete-btn" data-item-id="${item.id}" title="Delete" aria-label="Delete item ${item.id}">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </td>
                        `;
                    });
                } else {
                    itemsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">No items found.</td></tr>`;
                }
            } else if (data.message) { // Handle case where data.items might be undefined but a message exists
                 itemsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">${data.message}</td></tr>`;
            } else { // Fallback for unexpected structure
                 itemsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">Could not load items or no items found.</td></tr>`;
            }
        } catch (error) { // Error from fetchAPI
            console.error('Error fetching items:', error.message);
            itemsTableBody.innerHTML = `<tr><td colspan="7" class="text-center">Error loading items: ${error.message}</td></tr>`;
        }
    }

    // --- Event Listener for addItemForm submission ---
    addItemForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearError('addItemError'); // Use ID

        const name = document.getElementById('itemName').value.trim();
        const description = document.getElementById('itemDescription').value.trim();
        const quantityInput = document.getElementById('itemQuantity').value; // Get as string first
        const imageUrl = document.getElementById('itemImageUrl').value.trim();
        
        let quantity;
        if (quantityInput === '' || isNaN(quantityInput)) { // Check if empty or not a number string
             displayError('addItemError', 'Quantity must be a positive integer.');
             return;
        }
        quantity = parseInt(quantityInput, 10);


        if (!name || quantity <= 0) { // quantity already parsed or would have returned
            displayError('addItemError', 'Item name and a valid positive quantity are required.');
            return;
        }
        // Add more validation from HTML (e.g. max length for URL if any) or specific rules here if needed

        const itemData = { name, description, quantity, image_url: imageUrl };

        try {
            const result = await fetchAPI('api/add_item.php', {
                method: 'POST',
                body: itemData, // fetchAPI will stringify
                buttonElement: saveItemBtn
            });

            // fetchAPI throws error for non-ok, so success is implied by reaching here
            addItemModal.hide();
            addItemForm.reset();
            fetchItemsAndDisplay();
            showSuccessAlert(result.message || 'Item added successfully!');
        } catch (error) {
            displayError('addItemError', error.message || 'An unexpected error occurred.');
        }
    });

    // --- Event Delegation for "Edit" and "Delete" buttons ---
    itemsTableBody.addEventListener('click', async function (event) {
        const target = event.target.closest('button');
        if (!target) return;

        const itemId = target.dataset.itemId;

        if (target.classList.contains('edit-btn')) {
            clearError('editItemError');
            try {
                const data = await fetchAPI(`api/get_items.php?id=${itemId}`); // Use fetchAPI
                if (data.item) { // fetchAPI implies success
                    document.getElementById('editItemId').value = data.item.id;
                    document.getElementById('editItemName').value = data.item.name;
                    document.getElementById('editItemDescription').value = data.item.description || '';
                    document.getElementById('editItemQuantity').value = data.item.quantity;
                    document.getElementById('editItemAvailableQuantity').value = data.item.available_quantity;
                    document.getElementById('editItemImageUrl').value = data.item.image_url || '';
                    editItemModal.show();
                } else {
                    // This path might not be hit if fetchAPI throws error for item not found (404)
                    showSuccessAlert(data.message || 'Could not fetch item details.');
                }
            } catch (error) {
                showSuccessAlert(error.message || 'Error fetching item details.'); // Show in global alert
            }
        }

        if (target.classList.contains('delete-btn')) {
            clearError('deleteItemError');
            deleteItemModalEl.dataset.itemIdToDelete = itemId;
            deleteItemModal.show();
        }
    });

    // --- Event Listener for editItemForm submission ---
    editItemForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearError('editItemError');

        const itemId = parseInt(document.getElementById('editItemId').value, 10);
        const name = document.getElementById('editItemName').value.trim();
        const description = document.getElementById('editItemDescription').value.trim();
        const quantity = parseInt(document.getElementById('editItemQuantity').value, 10);
        const availableQuantity = parseInt(document.getElementById('editItemAvailableQuantity').value, 10);
        const imageUrl = document.getElementById('editItemImageUrl').value.trim();

        if (!name || isNaN(quantity) || quantity < 0 || isNaN(availableQuantity) || availableQuantity < 0) {
            displayError('editItemError', 'Item name, valid non-negative quantity, and valid non-negative available quantity are required.');
            return;
        }
        if (availableQuantity > quantity) {
            displayError('editItemError', 'Available quantity cannot exceed total quantity.');
            return;
        }

        const itemData = { item_id: itemId, name, description, quantity, available_quantity: availableQuantity, image_url: imageUrl };

        try {
            const result = await fetchAPI('api/update_item.php', {
                method: 'POST',
                body: itemData,
                buttonElement: updateItemBtn
            });
            
            editItemModal.hide();
            fetchItemsAndDisplay();
            showSuccessAlert(result.message || 'Item updated successfully!');
        } catch (error) {
            displayError('editItemError', error.message || 'An unexpected error occurred.');
        }
    });

    // --- Event Listener for confirmDeleteBtn in deleteItemModal ---
    confirmDeleteBtn.addEventListener('click', async function () {
        const itemIdToDelete = deleteItemModalEl.dataset.itemIdToDelete;
        if (!itemIdToDelete) return;
        clearError('deleteItemError');
        
        try {
            const result = await fetchAPI('api/delete_item.php', {
                method: 'POST',
                body: { item_id: parseInt(itemIdToDelete,10) },
                buttonElement: confirmDeleteBtn
            });

            deleteItemModal.hide();
            fetchItemsAndDisplay();
            showSuccessAlert(result.message || 'Item deleted successfully!');
        } catch (error) {
            displayError('deleteItemError', error.message || 'An unexpected error occurred.');
        }
    });

    // --- Logout Functionality ---
    if(logoutBtn) {
        logoutBtn.addEventListener('click', async function () {
            try {
                const result = await fetchAPI('api/logout.php', { 
                    method: 'POST',
                    buttonElement: logoutBtn // Assuming logoutBtn is defined
                });
                // fetchAPI implies success if no error is thrown
                localStorage.removeItem('user_id');
                localStorage.removeItem('username');
                localStorage.removeItem('user_full_name');
                localStorage.removeItem('user_role');
                window.location.href = 'login.html';
            } catch (error) {
                // Use global alert for logout error as user is likely navigating away
                showSuccessAlert(error.message || 'Logout failed. Please try again.');
            }
        });
    }

    // --- Initial call ---
    fetchItemsAndDisplay();

    // Clear modal errors when they are hidden using IDs
    addItemModalEl.addEventListener('hidden.bs.modal', function () {
        clearError('addItemError');
        addItemForm.reset();
    });
    editItemModalEl.addEventListener('hidden.bs.modal', function () {
        clearError('editItemError');
        editItemForm.reset();
    });
    deleteItemModalEl.addEventListener('hidden.bs.modal', function () {
        clearError('deleteItemError');
    });
});
