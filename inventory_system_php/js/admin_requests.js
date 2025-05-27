document.addEventListener('DOMContentLoaded', function () {
    // --- Initial Auth Check ---
    if (localStorage.getItem('user_role') !== 'Admin') {
        window.location.href = 'login.html';
        return; // Stop script execution if not admin
    }

    // --- Global variables and selectors ---
    const allRequestsTableBody = document.getElementById('allRequestsTableBody');
    const statusFilter = document.getElementById('statusFilter');
    const actionRequestModalEl = document.getElementById('actionRequestModal');
    const actionRequestModal = new bootstrap.Modal(actionRequestModalEl);
    const actionModalTitle = document.getElementById('actionModalTitle');
    const actionRequestForm = document.getElementById('actionRequestForm');
    const modalRequestId = document.getElementById('modalRequestId'); // Hidden input in form
    const modalActionType = document.getElementById('modalActionType'); // Hidden input in form
    const adminRemarksInput = document.getElementById('adminRemarks');
    const actualReturnDateGroup = document.getElementById('actualReturnDateGroup');
    const actualReturnDateInput = document.getElementById('actualReturnDateInput');
    const confirmActionBtn = document.getElementById('confirmActionBtn');
    const actionRequestErrorDiv = document.getElementById('actionRequestError');
    const logoutBtn = document.getElementById('logoutBtnAdminRequests'); // Unique ID for this page's logout
    
    // const successAlertEl = document.getElementById('successAlert'); // Handled by global showSuccessAlert from utils.js
    // Local utility functions (showSuccessAlert, displayError, clearError, getStatusBadge) are removed as they are in utils.js

    // --- Load All Requests ---
    async function loadAllRequests(filterValue = '') {
        if (!allRequestsTableBody) return;
        let url = 'api/get_requests.php';
        if (filterValue) {
            url += `?status=${encodeURIComponent(filterValue)}`;
        }

        try {
            const data = await fetchAPI(url); // Use fetchAPI

            allRequestsTableBody.innerHTML = ''; // Clear existing rows
            if (data.requests) { // fetchAPI returns data directly, success is implied
                if (data.requests.length === 0) {
                    allRequestsTableBody.innerHTML = `<tr><td colspan="10" class="text-center">No requests found for this filter.</td></tr>`;
                    return;
                }
                data.requests.forEach(req => {
                    // getStatusBadge is now from utils.js
                    const row = allRequestsTableBody.insertRow();
                    let actionsHtml = '';
                    if (req.status === 'Pending') {
                        actionsHtml = `
                            <button class="btn btn-sm btn-success action-btn" data-action="Approved" data-request-id="${req.id}" title="Approve" aria-label="Approve request ${req.id}"><i class="bi bi-check-circle"></i></button>
                            <button class="btn btn-sm btn-danger action-btn ms-1" data-action="Rejected" data-request-id="${req.id}" title="Reject" aria-label="Reject request ${req.id}"><i class="bi bi-x-circle"></i></button>
                        `;
                    } else if (req.status === 'Approved') {
                        actionsHtml = `
                            <button class="btn btn-sm btn-info action-btn" data-action="Returned" data-request-id="${req.id}" title="Mark as Returned" aria-label="Mark request ${req.id} as Returned"><i class="bi bi-box-arrow-left"></i></button>
                        `;
                    } else if (req.status === 'Overdue') {
                         actionsHtml = `
                            <button class="btn btn-sm btn-info action-btn" data-action="Returned" data-request-id="${req.id}" title="Mark as Returned (Overdue)" aria-label="Mark request ${req.id} as Returned (Overdue)"><i class="bi bi-box-arrow-left"></i></button>
                        `;
                    }

                    row.innerHTML = `
                        <td>${req.id}</td>
                        <td>${req.user_name || 'N/A'} (ID: ${req.user_id})</td>
                        <td>${req.item_name || 'N/A'} (ID: ${req.item_id}) ${req.item_image_url ? `<a href="${req.item_image_url}" target="_blank" title="View item image"><i class="bi bi-image-alt small"></i></a>` : ''}</td>
                        <td>${req.quantity_requested}</td>
                        <td>${new Date(req.request_date).toLocaleDateString()}</td>
                        <td>${req.expected_return_date ? new Date(req.expected_return_date + 'T00:00:00').toLocaleDateString() : 'N/A'}</td>
                        <td>${req.actual_return_date ? new Date(req.actual_return_date).toLocaleDateString() : 'N/A'}</td>
                        <td>${getStatusBadge(req.status)}</td> 
                        <td>${req.admin_remarks || 'N/A'}</td>
                        <td>${actionsHtml || 'No actions'}</td>
                    `;
                });
            } else if (data.message) { // Handle case where data.requests might be undefined but a message exists
                 allRequestsTableBody.innerHTML = `<tr><td colspan="10" class="text-center">${data.message}</td></tr>`;
            } else { // Fallback for unexpected structure
                 allRequestsTableBody.innerHTML = `<tr><td colspan="10" class="text-center">Could not load requests or no requests found.</td></tr>`;
            }
        } catch (error) { // Error from fetchAPI
            console.error('Error fetching requests:', error.message);
            allRequestsTableBody.innerHTML = `<tr><td colspan="10" class="text-center">Error loading requests: ${error.message}</td></tr>`;
        }
    }

    // --- Event Listener for statusFilter change ---
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            loadAllRequests(this.value);
        });
    }

    // --- Event Delegation for Action Buttons ---
    if (allRequestsTableBody) {
        allRequestsTableBody.addEventListener('click', function (event) {
            const target = event.target.closest('.action-btn');
            if (!target) return;

            const requestId = target.dataset.requestId;
            const action = target.dataset.action; // 'Approved', 'Rejected', 'Returned'

            clearError('actionRequestError'); // Use ID
            actionRequestForm.reset(); // Reset form fields
            modalRequestId.value = requestId;
            modalActionType.value = action; // This is the new_status

            if (action === 'Approved') {
                actionModalTitle.textContent = `Approve Request ID: ${requestId}`;
                adminRemarksInput.placeholder = "Optional remarks for approval";
                actualReturnDateGroup.style.display = 'none';
            } else if (action === 'Rejected') {
                actionModalTitle.textContent = `Reject Request ID: ${requestId}`;
                adminRemarksInput.placeholder = "Reason for rejection (Required)";
                actualReturnDateGroup.style.display = 'none';
            } else if (action === 'Returned') {
                actionModalTitle.textContent = `Mark Request ID: ${requestId} as Returned`;
                adminRemarksInput.placeholder = "Optional remarks for return";
                actualReturnDateGroup.style.display = 'block';
                actualReturnDateInput.value = new Date().toISOString().split('T')[0]; // Prefill with today
            }
            actionRequestModal.show();
        });
    }

    // --- Event Listener for confirmActionBtn click ---
    if (actionRequestForm) {
        actionRequestForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            clearError('actionRequestError'); // Use ID
            // Button disabling is handled by fetchAPI by passing confirmActionBtn

            const reqId = modalRequestId.value;
            const newStatus = modalActionType.value;
            const remarks = adminRemarksInput.value.trim();
            const returnDate = actualReturnDateInput.value;

            // Validation
            if (newStatus === 'Rejected' && !remarks) {
                displayError('actionRequestError', 'Admin remarks are required for rejection.');
                return;
            }
            if (newStatus === 'Returned' && !returnDate) {
                displayError('actionRequestError', 'Actual return date is required for "Returned" status.');
                return;
            }

            const payload = {
                request_id: parseInt(reqId, 10),
                new_status: newStatus,
                admin_remarks: remarks
            };
            if (newStatus === 'Returned') {
                payload.actual_return_date = returnDate;
            }

            try {
                const result = await fetchAPI('api/update_request_status.php', {
                    method: 'POST',
                    body: payload, // fetchAPI will stringify
                    buttonElement: confirmActionBtn
                });
                
                // fetchAPI implies success if no error is thrown
                actionRequestModal.hide();
                loadAllRequests(statusFilter ? statusFilter.value : '');
                showSuccessAlert(result.message || `Request ID ${reqId} status updated to ${newStatus}.`);
            } catch (error) { // Error from fetchAPI
                displayError('actionRequestError', error.message || 'An unexpected error occurred.');
            }
        });
    }

    // --- Logout Functionality ---
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function () {
            try {
                // fetchAPI implies success if no error is thrown
                await fetchAPI('api/logout.php', { 
                    method: 'POST', 
                    buttonElement: logoutBtn 
                });
                localStorage.removeItem('user_id');
                localStorage.removeItem('username');
                localStorage.removeItem('user_full_name');
                localStorage.removeItem('user_role');
                window.location.href = 'login.html';
            } catch (error) { // Error from fetchAPI
                showSuccessAlert(error.message || 'An error occurred during logout.'); // Show in global alert
            }
        });
    }
    
    // Clear modal errors when hidden
    actionRequestModalEl.addEventListener('hidden.bs.modal', function () {
        clearError('actionRequestError'); // Use ID
        actionRequestForm.reset();
        actualReturnDateGroup.style.display = 'none'; // Ensure date field is hidden
    });

    // --- Initial call ---
    loadAllRequests(statusFilter ? statusFilter.value : '');
});
