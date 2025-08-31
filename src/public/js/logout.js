/**
 * Logout functionality for KrishiGhor
 * Handles proper logout by calling the API and cleaning up tokens
 */

function logout() {
    // Get the JWT token from localStorage
    const token = localStorage.getItem('auth_token');
    
    if (token) {
        // Call the logout API
        fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Logout successful:', data.message);
        })
        .catch(error => {
            console.error('Logout API error:', error);
        })
        .finally(() => {
            // Always clean up local storage and redirect
            cleanupAndRedirect();
        });
    } else {
        // No token found, just clean up and redirect
        cleanupAndRedirect();
    }
}

function cleanupAndRedirect() {
    // Remove all authentication-related data
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    localStorage.removeItem('user_role');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user_data');
    sessionStorage.removeItem('user_role');
    
    // Clear any cookies (if using them)
    document.cookie.split(";").forEach(function(c) { 
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
    });
    
    // Redirect to login page
    window.location.href = '/login.html';
}

// Add logout event listeners to all logout links
document.addEventListener('DOMContentLoaded', function() {
    const logoutLinks = document.querySelectorAll('a[href="/login.html"]');
    
    logoutLinks.forEach(link => {
        if (link.textContent.toLowerCase().includes('logout')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                logout();
            });
        }
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { logout, cleanupAndRedirect };
}
