/**
 * Forms Module
 * Handles form validations, AI typo hints, and form submissions
 */

const Forms = {
    // API base URL
    apiBase: '/api',
    
    // Initialize login form
    initLogin() {
        const form = document.getElementById('loginForm');
        if (!form) return;
        
        form.addEventListener('submit', this.handleLogin.bind(this));
        
        // Add real-time validation
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        
        if (email) {
            email.addEventListener('blur', () => this.validateEmail(email));
            email.addEventListener('input', () => this.clearError());
        }
        
        if (password) {
            password.addEventListener('input', () => this.clearError());
        }
    },
    
    // Initialize registration form
    initRegister() {
        const form = document.getElementById('registerForm');
        if (!form) return;
        
        form.addEventListener('submit', this.handleRegister.bind(this));
        
        // Add real-time validation
        const fields = ['email', 'password', 'confirm_password', 'first_name', 'last_name'];
        fields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('blur', () => this.validateField(field));
                field.addEventListener('input', () => this.clearError());
            }
        });
        
        // Password confirmation validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (password && confirmPassword) {
            confirmPassword.addEventListener('input', () => {
                this.validatePasswordMatch(password, confirmPassword);
            });
        }
        
        // AI typo detection for name fields
        this.initTypoDetection();
    },
    
    // Handle login form submission
    async handleLogin(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitButton = document.getElementById('loginButton');
        const spinner = document.getElementById('loginSpinner');
        
        // Show loading state
        this.setLoadingState(submitButton, spinner, true);
        this.clearError();
        
        const formData = new FormData(form);
        const data = {
            email: formData.get('email'),
            password: formData.get('password')
        };
        
        try {
            const response = await fetch(`${this.apiBase}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                // Store token and user info
                localStorage.setItem('authToken', result.token);
                localStorage.setItem('user', JSON.stringify(result.user));
                
                this.showSuccess(I18n.get('success.login'));
                
                // Redirect based on role
                const redirectUrl = this.getRedirectUrl(result.user.role);
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);
                
            } else {
                this.showError(result.error || I18n.get('error.login_failed'));
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showError(I18n.get('error.network_error'));
        } finally {
            this.setLoadingState(submitButton, spinner, false);
        }
    },
    
    // Handle registration form submission
    async handleRegister(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitButton = document.getElementById('registerButton');
        const spinner = document.getElementById('registerSpinner');
        
        // Validate form before submission
        if (!this.validateRegistrationForm(form)) {
            return;
        }
        
        // Show loading state
        this.setLoadingState(submitButton, spinner, true);
        this.clearError();
        this.clearSuccess();
        
        const formData = new FormData(form);
        const data = {
            email: formData.get('email'),
            password: formData.get('password'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            phone: formData.get('phone'),
            role: formData.get('role'),
            region: formData.get('region'),
            language: I18n.currentLanguage
        };
        
        try {
            const response = await fetch(`${this.apiBase}/auth/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.showSuccess(I18n.get('success.registration'));
                
                // Auto-login after successful registration
                localStorage.setItem('authToken', result.token);
                localStorage.setItem('user', JSON.stringify(result.user));
                
                setTimeout(() => {
                    const redirectUrl = this.getRedirectUrl(result.user.role);
                    window.location.href = redirectUrl;
                }, 2000);
                
            } else {
                this.showError(result.error || I18n.get('error.registration_failed'));
            }
            
        } catch (error) {
            console.error('Registration error:', error);
            this.showError(I18n.get('error.network_error'));
        } finally {
            this.setLoadingState(submitButton, spinner, false);
        }
    },
    
    // Validate email format
    validateEmail(emailField) {
        const email = emailField.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.showFieldError(emailField, I18n.get('error.invalid_email'));
            return false;
        }
        
        this.clearFieldError(emailField);
        return true;
    },
    
    // Validate individual field
    validateField(field) {
        const value = field.value.trim();
        
        if (field.hasAttribute('required') && !value) {
            this.showFieldError(field, I18n.get('error.required_field'));
            return false;
        }
        
        if (field.type === 'email') {
            return this.validateEmail(field);
        }
        
        if (field.type === 'password' && field.name === 'password') {
            return this.validatePassword(field);
        }
        
        this.clearFieldError(field);
        return true;
    },
    
    // Validate password strength
    validatePassword(passwordField) {
        const password = passwordField.value;
        
        if (password.length < 6) {
            this.showFieldError(passwordField, I18n.get('error.weak_password'));
            return false;
        }
        
        this.clearFieldError(passwordField);
        return true;
    },
    
    // Validate password confirmation
    validatePasswordMatch(passwordField, confirmField) {
        const password = passwordField.value;
        const confirm = confirmField.value;
        
        if (confirm && password !== confirm) {
            this.showFieldError(confirmField, I18n.get('error.password_mismatch'));
            return false;
        }
        
        this.clearFieldError(confirmField);
        return true;
    },
    
    // Validate entire registration form
    validateRegistrationForm(form) {
        const formData = new FormData(form);
        let isValid = true;
        
        // Required fields
        const requiredFields = ['email', 'password', 'first_name', 'last_name', 'role', 'region'];
        for (const fieldName of requiredFields) {
            if (!formData.get(fieldName)) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    this.showFieldError(field, I18n.get('error.required_field'));
                }
                isValid = false;
            }
        }
        
        // Email validation
        const emailField = form.querySelector('[name="email"]');
        if (emailField && !this.validateEmail(emailField)) {
            isValid = false;
        }
        
        // Password validation
        const passwordField = form.querySelector('[name="password"]');
        const confirmField = form.querySelector('[name="confirm_password"]');
        
        if (passwordField && !this.validatePassword(passwordField)) {
            isValid = false;
        }
        
        if (passwordField && confirmField && 
            !this.validatePasswordMatch(passwordField, confirmField)) {
            isValid = false;
        }
        
        // Terms agreement
        const agreeTerms = form.querySelector('[name="agree_terms"]');
        if (agreeTerms && !agreeTerms.checked) {
            this.showError(I18n.get('error.terms_required', 'You must agree to the terms'));
            isValid = false;
        }
        
        return isValid;
    },
    
    // Initialize AI typo detection
    initTypoDetection() {
        const nameFields = ['first_name', 'last_name'];
        
        nameFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('blur', () => {
                    this.checkTypos(field);
                });
            }
        });
    },
    
    // Check for typos using AI service
    async checkTypos(field) {
        const text = field.value.trim();
        if (text.length < 2) return;
        
        try {
            const response = await fetch(`${this.apiBase}/ai/detect-typos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    text: text,
                    language: I18n.currentLanguage
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.suggestions && result.suggestions.length > 0) {
                    this.showTypoSuggestions(field, result.suggestions);
                }
            }
        } catch (error) {
            // Silently fail typo detection
            console.debug('Typo detection error:', error);
        }
    },
    
    // Show typo suggestions
    showTypoSuggestions(field, suggestions) {
        // Remove existing suggestions
        this.clearTypoSuggestions(field);
        
        const container = document.createElement('div');
        container.className = 'typo-suggestions mt-1 p-2 bg-yellow-50 border border-yellow-200 rounded text-sm';
        container.innerHTML = `
            <div class="text-yellow-800 mb-1">
                <i class="fas fa-lightbulb"></i> ${I18n.get('typo.suggestion', 'Suggestion')}:
            </div>
        `;
        
        suggestions.forEach(suggestion => {
            const suggestionBtn = document.createElement('button');
            suggestionBtn.type = 'button';
            suggestionBtn.className = 'inline-block mr-2 mb-1 px-2 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded text-xs transition-colors';
            suggestionBtn.textContent = suggestion.suggestion;
            suggestionBtn.addEventListener('click', () => {
                field.value = field.value.replace(suggestion.original, suggestion.suggestion);
                this.clearTypoSuggestions(field);
            });
            container.appendChild(suggestionBtn);
        });
        
        field.parentNode.appendChild(container);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            this.clearTypoSuggestions(field);
        }, 10000);
    },
    
    // Clear typo suggestions
    clearTypoSuggestions(field) {
        const existing = field.parentNode.querySelector('.typo-suggestions');
        if (existing) {
            existing.remove();
        }
    },
    
    // Show form error
    showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
        }
    },
    
    // Clear form error
    clearError() {
        const errorDiv = document.getElementById('errorMessage');
        if (errorDiv) {
            errorDiv.classList.add('hidden');
        }
    },
    
    // Show success message
    showSuccess(message) {
        const successDiv = document.getElementById('successMessage');
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.classList.remove('hidden');
        }
    },
    
    // Clear success message
    clearSuccess() {
        const successDiv = document.getElementById('successMessage');
        if (successDiv) {
            successDiv.classList.add('hidden');
        }
    },
    
    // Show field-specific error
    showFieldError(field, message) {
        this.clearFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-red-300 text-xs mt-1';
        errorDiv.textContent = message;
        
        field.classList.add('border-red-500');
        field.parentNode.appendChild(errorDiv);
    },
    
    // Clear field-specific error
    clearFieldError(field) {
        field.classList.remove('border-red-500');
        const existing = field.parentNode.querySelector('.field-error');
        if (existing) {
            existing.remove();
        }
    },
    
    // Set loading state for buttons
    setLoadingState(button, spinner, loading) {
        if (button) {
            button.disabled = loading;
        }
        if (spinner) {
            if (loading) {
                spinner.classList.remove('hidden');
            } else {
                spinner.classList.add('hidden');
            }
        }
    },
    
    // Get redirect URL based on user role
    getRedirectUrl(role) {
        const roleUrls = {
            'farmer': '/dashboard/farmer',
            'buyer': '/dashboard/buyer',
            'admin': '/dashboard/admin'
        };
        
        return roleUrls[role] || '/dashboard/farmer';
    },
    
    // Check if user is authenticated
    isAuthenticated() {
        const token = localStorage.getItem('authToken');
        const user = localStorage.getItem('user');
        return !!(token && user);
    },
    
    // Get current user info
    getCurrentUser() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    },
    
    // Get auth token
    getAuthToken() {
        return localStorage.getItem('authToken');
    },
    
    // Logout user
    logout() {
        localStorage.removeItem('authToken');
        localStorage.removeItem('user');
        window.location.href = '/login';
    },
    
    // Make authenticated API request
    async apiRequest(endpoint, options = {}) {
        const token = this.getAuthToken();
        
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, config);
            
            if (response.status === 401) {
                // Unauthorized - redirect to login
                this.logout();
                return null;
            }
            
            return response;
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    }
};

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize based on current page
        if (document.getElementById('loginForm')) {
            Forms.initLogin();
        }
        if (document.getElementById('registerForm')) {
            Forms.initRegister();
        }
    });
} else {
    // DOM is already ready
    if (document.getElementById('loginForm')) {
        Forms.initLogin();
    }
    if (document.getElementById('registerForm')) {
        Forms.initRegister();
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Forms;
}
