document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    // errorMessageDiv is handled by displayError/clearError using its ID 'errorMessage'
    const loginButton = document.getElementById('loginButton');

    // Function to check auth status using fetchAPI
    async function checkAuthStatus() {
        try {
            // fetchAPI uses GET by default and handles JSON parsing
            const data = await fetchAPI('api/auth_status.php'); 
            if (data.loggedIn === true) {
                if (data.user) {
                    localStorage.setItem('user_role', data.user.role);
                    localStorage.setItem('user_full_name', data.user.full_name);
                }
                window.location.href = 'dashboard.html';
            }
            // If not loggedIn, stay on the login page. fetchAPI would throw error for non-ok response.
        } catch (error) {
            // Error already logged by fetchAPI.
            // displayError('errorMessage', 'Authentication check failed. Please try refreshing.'); // Optional: show error to user
            console.error('Auth status check failed:', error.message); // Keep console log for debugging
        }
    }

    // Check auth status when the script loads
    checkAuthStatus();

    if (loginForm) {
        loginForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            clearError('errorMessage'); // Use utility function

            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();

            if (!username || !password) {
                displayError('errorMessage', 'Username and password are required.'); // Use utility function
                return;
            }

            // Encode data for application/x-www-form-urlencoded
            const formData = new URLSearchParams();
            formData.append('username', username);
            formData.append('password', password);

            try {
                const data = await fetchAPI('api/login.php', {
                    method: 'POST',
                    headers: { // fetchAPI defaults to application/json for POST, so override for x-www-form-urlencoded
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString(), // fetchAPI expects object for JSON, so pass string directly
                    buttonElement: loginButton // Pass button for automatic disabling/re-enabling
                });

                // fetchAPI throws an error for non-ok responses, so if we are here, response was ok.
                if (data.success === true) {
                    if (data.user) {
                        localStorage.setItem('user_role', data.user.role);
                        localStorage.setItem('user_full_name', data.user.full_name);
                    }
                    window.location.href = 'dashboard.html';
                } else {
                    // This case might be redundant if fetchAPI always throws for non-success,
                    // but backend might return {success: false} with a 200 OK.
                    displayError('errorMessage', data.message || 'Login failed. Please try again.');
                }
            } catch (error) {
                // Error message is from fetchAPI (already includes backend message if available)
                displayError('errorMessage', error.message || 'An error occurred during login.');
            }
            // Button state is handled by fetchAPI's finally block if buttonElement is passed
        });
    }
});
