document.addEventListener('DOMContentLoaded', function () {
    // --- Initial Setup & Auth ---
    const userId = localStorage.getItem('user_id');
    const userRole = localStorage.getItem('user_role');
    const userFullName = localStorage.getItem('user_full_name');

    const userFullNameDisplay = document.getElementById('userFullNameDisplay');
    const logoutBtn = document.getElementById('logoutBtn');
    const mainContent = document.getElementById('mainContent');
    const adminDashboardView = document.getElementById('adminDashboardView');
    const employeeDashboardView = document.getElementById('employeeDashboardView');
    const availableItemsContainer = document.getElementById('availableItemsContainer');
    const myRequestsTableBody = document.getElementById('myRequestsTableBody');
    const navbarNav = document.querySelector('#navbarNav .navbar-nav.me-auto');

    const borrowItemModalEl = document.getElementById('borrowItemModal');
    const borrowItemModal = new bootstrap.Modal(borrowItemModalEl);
    const borrowItemForm = document.getElementById('borrowItemForm');
    const modalItemId = document.getElementById('modalItemId');
    const modalItemName = document.getElementById('modalItemName');
    const modalQuantityRequested = document.getElementById('modalQuantityRequested');
    const modalItemAvailableQuantityDisplay = document.getElementById('modalItemAvailableQuantityDisplay');
    const modalExpectedReturnDate = document.getElementById('modalExpectedReturnDate');
    const borrowItemErrorDiv = document.getElementById('borrowItemError');
    const submitBorrowRequestBtn = document.getElementById('submitBorrowRequestBtn');
    
    const successAlertEl = document.getElementById('successAlert');
    // const successAlert = successAlertEl ? new bootstrap.Alert(successAlertEl) : null; // Not needed if using global showSuccessAlert

    if (!userId) { // This check is also in HTML, but keep for defense in depth
        window.location.href = 'login.html';
        return; 
    }

    if (userFullNameDisplay) {
        userFullNameDisplay.textContent = userFullName || 'User';
    }

    // Local utility functions (showSuccessAlert, displayError, clearError, getStatusBadge) are removed. They are now in utils.js.

    // --- Role-Specific Setup ---
    if (userRole === 'Admin') {
        if (adminDashboardView) {
            adminDashboardView.style.display = 'block';
            const summaryDiv = document.createElement('div');
            summaryDiv.id = 'adminSummary';
            summaryDiv.className = 'mt-3 mb-3 p-3 bg-light border rounded';
            summaryDiv.innerHTML = `
                <h4>Quick Stats:</h4>
                <p>Pending Requests: <span id='pendingCountDisplay' class='fw-bold'>Loading...</span></p>
                <p>Approved Requests: <span id='approvedCountDisplay' class='fw-bold'>Loading...</span></p>
                <p>Total Items: <span id='totalItemCountDisplay' class='fw-bold'>Loading...</span></p>
                <hr>
                <p class="mb-0">Manage all borrow requests: <a href="admin_requests.html" class="btn btn-primary btn-sm">Go to Request Management</a></p>
            `;
            const h2Admin = adminDashboardView.querySelector('h2');
            if (h2Admin && h2Admin.nextElementSibling) {
                 h2Admin.nextElementSibling.insertAdjacentElement('afterend', summaryDiv);
            } else if (h2Admin) {
                 adminDashboardView.appendChild(summaryDiv);
            }
            loadAdminSummary();
        }
        if (employeeDashboardView) employeeDashboardView.style.display = 'none';
        if (navbarNav && !navbarNav.querySelector('a[href="admin_inventory.html"]')) {
            navbarNav.innerHTML = `
                <li class="nav-item">
                    <a class="nav-link" href="admin_inventory.html">Manage Inventory</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_requests.html">Manage Requests</a>
                </li>
            ` + navbarNav.innerHTML;
        }
    } else { 
        if (adminDashboardView) adminDashboardView.style.display = 'none';
        if (employeeDashboardView) employeeDashboardView.style.display = 'block';
        loadAvailableItems();
        loadMyBorrowRequests();
    }

    // --- Load Admin Summary Data ---
    async function loadAdminSummary() {
        const pendingCountDisplay = document.getElementById('pendingCountDisplay');
        const approvedCountDisplay = document.getElementById('approvedCountDisplay');
        const totalItemCountDisplay = document.getElementById('totalItemCountDisplay');

        try {
            const requestsData = await fetchAPI('api/get_requests.php');
            if (requestsData.requests) {
                const pendingCount = requestsData.requests.filter(r => r.status === 'Pending').length;
                const approvedCount = requestsData.requests.filter(r => r.status === 'Approved').length;
                if(pendingCountDisplay) pendingCountDisplay.textContent = pendingCount;
                if(approvedCountDisplay) approvedCountDisplay.textContent = approvedCount;
            } else {
                if(pendingCountDisplay) pendingCountDisplay.textContent = 'Error';
                if(approvedCountDisplay) approvedCountDisplay.textContent = 'Error';
            }
        } catch (error) {
            console.error('Error fetching requests for admin summary:', error.message);
            if(pendingCountDisplay) pendingCountDisplay.textContent = 'Error';
            if(approvedCountDisplay) approvedCountDisplay.textContent = 'Error';
        }

        try {
            const itemsData = await fetchAPI('api/get_items.php');
            if (itemsData.items) {
                if(totalItemCountDisplay) totalItemCountDisplay.textContent = itemsData.items.length;
            } else {
                if(totalItemCountDisplay) totalItemCountDisplay.textContent = 'Error';
            }
        } catch (error) {
            console.error('Error fetching items for admin summary:', error.message);
            if(totalItemCountDisplay) totalItemCountDisplay.textContent = 'Error';
        }
    }

    // --- Load Available Items (for Employees) ---
    async function loadAvailableItems() {
        if (!availableItemsContainer) return;
        try {
            const data = await fetchAPI('api/get_items.php');
            availableItemsContainer.innerHTML = ''; 
            if (data.items) {
                const available = data.items.filter(item => item.available_quantity > 0);
                if (available.length === 0) {
                    availableItemsContainer.innerHTML = '<p class="col">No items currently available for borrowing.</p>';
                    return;
                }
                available.forEach(item => {
                    // getStatusBadge is from utils.js
                    const card = `
                        <div class="col">
                            <div class="card item-card shadow-sm">
                                ${item.image_url ? `<img src="${item.image_url}" class="card-img-top p-2" alt="${item.name}">` : '<div class="text-center p-3"><i class="bi bi-box-seam fs-1 text-muted"></i></div>'}
                                <div class="card-body">
                                    <h5 class="card-title">${item.name}</h5>
                                    <p class="card-text small">${item.description ? item.description.substring(0,100) + (item.description.length > 100 ? '...' : '') : 'No description.'}</p>
                                    <div>
                                        <p class="card-text"><strong>Available:</strong> ${item.available_quantity}</p>
                                        <button class="btn btn-primary w-100 requestBorrowBtn" 
                                                data-item-id="${item.id}" 
                                                data-item-name="${item.name}" 
                                                data-item-available-quantity="${item.available_quantity}">
                                            Request to Borrow
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    availableItemsContainer.insertAdjacentHTML('beforeend', card);
                });
            } else {
                availableItemsContainer.innerHTML = `<p class="col">${data.message || 'Could not load items.'}</p>`;
            }
        } catch (error) {
            console.error('Error fetching available items:', error.message);
            availableItemsContainer.innerHTML = `<p class="col">Error loading items: ${error.message}</p>`;
        }
    }

    // --- Load My Borrow Requests (for Employees) ---
    async function loadMyBorrowRequests() {
        if (!myRequestsTableBody) return;
        try {
            const data = await fetchAPI('api/get_requests.php'); // Backend filters by logged-in user
            myRequestsTableBody.innerHTML = ''; 
            if (data.requests) {
                if (data.requests.length === 0) {
                    myRequestsTableBody.innerHTML = '<tr><td colspan="8" class="text-center">You have no borrow requests.</td></tr>';
                    return;
                }
                data.requests.forEach(req => {
                    const row = myRequestsTableBody.insertRow();
                    // getStatusBadge is from utils.js
                    row.innerHTML = `
                        <td>${req.id}</td>
                        <td>${req.item_name} ${req.item_image_url ? `<a href="${req.item_image_url}" target="_blank" title="View item image"><i class="bi bi-image-alt small"></i></a>` : ''}</td>
                        <td>${req.quantity_requested}</td>
                        <td>${new Date(req.request_date).toLocaleDateString()}</td>
                        <td>${req.expected_return_date ? new Date(req.expected_return_date + 'T00:00:00').toLocaleDateString() : 'N/A'}</td>
                        <td>${getStatusBadge(req.status)}</td>
                        <td>${req.admin_remarks || 'N/A'}</td>
                        <td>
                            ${req.status === 'Pending' ? `<button class="btn btn-sm btn-outline-danger cancelRequestBtn" data-request-id="${req.id}">Cancel</button>` : ''}
                        </td>
                    `;
                });
            } else {
                myRequestsTableBody.innerHTML = `<tr><td colspan="8" class="text-center">${data.message || 'Could not load your requests.'}</td></tr>`;
            }
        } catch (error) {
            console.error('Error fetching borrow requests:', error.message);
            myRequestsTableBody.innerHTML = `<tr><td colspan="8" class="text-center">Error loading requests: ${error.message}</td></tr>`;
        }
    }

    // --- Event Listener for "Request to Borrow" buttons ---
    if (availableItemsContainer) {
        availableItemsContainer.addEventListener('click', function (event) {
            const target = event.target.closest('.requestBorrowBtn');
            if (target) {
                clearError('borrowItemError'); // Use ID
                modalItemId.value = target.dataset.itemId;
                modalItemName.value = target.dataset.itemName; // Corrected: Use itemName
                modalItemAvailableQuantityDisplay.textContent = target.dataset.itemAvailableQuantity;
                modalQuantityRequested.max = target.dataset.itemAvailableQuantity;
                modalQuantityRequested.value = 1; // Default to 1
                modalExpectedReturnDate.value = ''; // Clear previous date
                
                // Set min date for expected return date to tomorrow
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                const minReturnDate = tomorrow.toISOString().split('T')[0];
                modalExpectedReturnDate.min = minReturnDate;

                borrowItemModal.show();
            }
        });
    }


    // --- Event Listener for borrowItemForm submission ---
    if (borrowItemForm) {
        borrowItemForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            clearError('borrowItemError'); // Use ID

            const itemId = modalItemId.value;
            const quantity = parseInt(modalQuantityRequested.value, 10);
            const availableQty = parseInt(modalQuantityRequested.max, 10);
            const expectedReturn = modalExpectedReturnDate.value || null; // Send null if empty

            if (isNaN(quantity) || quantity <= 0) {
                displayError('borrowItemError', 'Please enter a valid quantity greater than zero.');
                return;
            }
            if (quantity > availableQty) {
                displayError('borrowItemError', `Quantity requested (${quantity}) exceeds available stock (${availableQty}).`);
                return;
            }
             if (expectedReturn) {
                const selectedDate = new Date(expectedReturn + "T00:00:00");
                const today = new Date();
                today.setHours(0,0,0,0); 
                if (selectedDate < today) {
                    displayError('borrowItemError', 'Expected return date cannot be in the past.');
                    return;
                }
            }

            const requestData = {
                item_id: parseInt(itemId, 10),
                quantity_requested: quantity,
                expected_return_date: expectedReturn
            };

            try {
                const result = await fetchAPI('api/submit_request.php', {
                    method: 'POST',
                    body: requestData, // fetchAPI stringifies
                    buttonElement: submitBorrowRequestBtn
                });

                // fetchAPI implies success
                borrowItemModal.hide();
                borrowItemForm.reset();
                if (userRole !== 'Admin') { 
                    loadAvailableItems();
                    loadMyBorrowRequests();
                }
                showSuccessAlert(result.message || 'Borrow request submitted successfully!');
            } catch (error) { // Error from fetchAPI
                displayError('borrowItemError', error.message || 'An unexpected error occurred.');
            }
        });
    }

    // --- Event Listener for "Cancel" request buttons ---
    if (myRequestsTableBody) {
        myRequestsTableBody.addEventListener('click', async function (event) {
            const target = event.target.closest('.cancelRequestBtn');
            if (target) {
                const requestId = target.dataset.requestId;
                if (confirm(`Are you sure you want to cancel request ID ${requestId}?`)) {
                    // Manual button disabling for confirm-based actions can be tricky with fetchAPI's auto-re-enable
                    // For simplicity, let's disable it and re-enable manually if error.
                    disableSubmitButton(target, "Cancelling...");
                    try {
                        const result = await fetchAPI('api/update_request_status.php', {
                            method: 'POST',
                            body: {
                                request_id: parseInt(requestId, 10),
                                new_status: 'Cancelled',
                                // No admin_remarks from user cancel. Backend should handle this.
                            }
                            // Not passing target as buttonElement to fetchAPI to manage state manually here
                        });
                        // fetchAPI implies success
                        if (userRole !== 'Admin') loadMyBorrowRequests(); // Refresh list
                        showSuccessAlert(result.message || 'Request cancelled successfully.');
                        // Button is removed on refresh, no need to re-enable explicitly if successful
                    } catch (error) { // Error from fetchAPI
                        showSuccessAlert(error.message || 'Failed to cancel request.');
                        enableSubmitButton(target); // Re-enable on error
                    }
                }
            }
        });
    }

    // --- Logout Functionality ---
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function () {
            try {
                await fetchAPI('api/logout.php', { 
                    method: 'POST',
                    buttonElement: logoutBtn
                });
                // fetchAPI implies success
                localStorage.removeItem('user_id');
                localStorage.removeItem('username');
                localStorage.removeItem('user_full_name');
                localStorage.removeItem('user_role');
                window.location.href = 'login.html';
            } catch (error) { // Error from fetchAPI
                showSuccessAlert(error.message || 'An error occurred during logout.');
            }
        });
    }
    
    // Clear modal errors when hidden
    borrowItemModalEl.addEventListener('hidden.bs.modal', function () {
        clearError('borrowItemError'); // Use ID
        borrowItemForm.reset();
    });

});
