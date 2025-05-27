/**
 * Displays a success alert message.
 * @param {string} message The message to display.
 * @param {string} alertElementId The ID of the alert element. Defaults to 'successAlert'.
 */
function showSuccessAlert(message, alertElementId = 'successAlert') {
    const alertEl = document.getElementById(alertElementId);
    if (alertEl) {
        alertEl.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Ensure dismiss button is there
        alertEl.classList.add('show');
        alertEl.classList.remove('hide'); // Bootstrap 5 uses 'hide' class to remove, ensure it's not set.
        
        // Automatically hide after a few seconds
        setTimeout(() => {
            if (alertEl) {
                alertEl.classList.remove('show');
                // Bootstrap's JS will handle removing it from DOM if it's a dismissible alert.
                // If not, you might need to manually hide it or set display:none.
            }
        }, 5000); // Increased to 5s for better readability
    } else {
        console.warn(`Alert element with ID '${alertElementId}' not found.`);
    }
}

/**
 * Displays an error message in a specified element.
 * @param {string} elementId The ID of the HTML element where the error should be displayed.
 * @param {string} message The error message to display.
 */
function displayError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    } else {
        console.warn(`Error display element with ID '${elementId}' not found.`);
    }
}

/**
 * Clears an error message from a specified element.
 * @param {string} elementId The ID of the HTML element where the error is displayed.
 */
function clearError(elementId) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

/**
 * Generates a Bootstrap badge class string based on item status.
 * @param {string} status The status string.
 * @returns {string} HTML string for the badge.
 */
function getStatusBadge(status) {
    let badgeClass = 'bg-secondary'; // Default badge
    switch (status) {
        case 'Pending': badgeClass = 'bg-warning text-dark'; break;
        case 'Approved': badgeClass = 'bg-success'; break;
        case 'Rejected': badgeClass = 'bg-danger'; break;
        case 'Returned': badgeClass = 'bg-info text-dark'; break;
        case 'Cancelled': badgeClass = 'bg-light text-dark border'; break;
        case 'Overdue': badgeClass = 'bg-danger-subtle text-danger-emphasis border border-danger'; break;
    }
    return `<span class="badge ${badgeClass}">${status}</span>`;
}

/**
 * A wrapper for the fetch API to standardize requests and error handling.
 * @param {string} url The URL to fetch.
 * @param {object} options Fetch options (method, headers, body, etc.).
 * @returns {Promise<object>} A promise that resolves with the JSON response data.
 * @throws {Error} Throws an error for network issues or non-OK HTTP responses.
 */
async function fetchAPI(url, options = {}) {
    // Default headers
    const defaultHeaders = {
        'Accept': 'application/json',
    };
    if (options.method && (options.method.toUpperCase() === 'POST' || options.method.toUpperCase() === 'PUT')) {
        defaultHeaders['Content-Type'] = 'application/json';
    }

    options.headers = { ...defaultHeaders, ...options.headers };

    // If body is an object and Content-Type is application/json, stringify it
    if (options.body && typeof options.body === 'object' && options.headers['Content-Type'] === 'application/json') {
        options.body = JSON.stringify(options.body);
    }
    
    const originalButtonText = options.buttonElement ? options.buttonElement.innerHTML : null;
    if (options.buttonElement) {
        options.buttonElement.disabled = true;
        options.buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;
    }

    try {
        const response = await fetch(url, options);

        if (!response.ok) {
            let errorData;
            try {
                errorData = await response.json();
            } catch (e) {
                // If response is not JSON, use status text
                errorData = { message: response.statusText || `HTTP error! Status: ${response.status}` };
            }
            // Ensure errorData has a message property
            const errorMessage = errorData.message || `HTTP error! Status: ${response.status}`;
            throw new Error(errorMessage);
        }
        
        // Handle cases where response might be empty but OK (e.g., 204 No Content)
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return await response.json();
        } else {
            return { success: true, message: "Operation successful with no JSON response." }; // Or handle as appropriate
        }

    } catch (error) {
        // Re-throw the error to be caught by the caller's catch block
        // This allows for specific error handling in the component if needed
        console.error('Fetch API Error:', error.message);
        throw error; 
    } finally {
        if (options.buttonElement && originalButtonText) {
            options.buttonElement.disabled = false;
            options.buttonElement.innerHTML = originalButtonText;
        }
    }
}

/**
 * Disables a submit button and shows loading state.
 * @param {HTMLButtonElement} buttonElement The button to disable.
 * @param {string} loadingText The text to display while loading. Defaults to "Loading...".
 */
function disableSubmitButton(buttonElement, loadingText = "Loading...") {
    if (buttonElement) {
        buttonElement.dataset.originalText = buttonElement.innerHTML; // Store original text
        buttonElement.disabled = true;
        buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${loadingText}`;
    }
}

/**
 * Enables a submit button and restores its original text.
 * @param {HTMLButtonElement} buttonElement The button to enable.
 */
function enableSubmitButton(buttonElement) {
    if (buttonElement) {
        buttonElement.disabled = false;
        if (buttonElement.dataset.originalText) {
            buttonElement.innerHTML = buttonElement.dataset.originalText;
        }
        // Optional: remove data-original-text attribute
        // delete buttonElement.dataset.originalText; 
    }
}
