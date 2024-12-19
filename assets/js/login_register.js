document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');
    
    // Function to show registration form
    function showRegistrationForm() {
        container.classList.add("active");
    }
    
    // Function to show login form
    function showLoginForm() {
        container.classList.remove("active");
    }
    
    // Event listeners for the toggle buttons
    registerBtn.addEventListener('click', showRegistrationForm);
    loginBtn.addEventListener('click', showLoginForm);
    
    // Form validation
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function validatePassword(password) {
        return password.length >= 8;
    }
    
    // Registration form validation
    const registrationForm = document.querySelector('.sign-up form');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            const firstName = this.querySelector('input[name="first_name"]').value;
            const lastName = this.querySelector('input[name="last_name"]').value;
            const email = this.querySelector('input[name="email"]').value;
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            const role = this.querySelector('select[name="role"]').value;
            
            let errors = [];
            
            // Validate fields
            if (!firstName || !lastName) {
                errors.push('Please enter your full name');
            }
            
            if (!validateEmail(email)) {
                errors.push('Please enter a valid email address');
            }
            
            if (!validatePassword(password)) {
                errors.push('Password must be at least 8 characters long');
            }
            
            if (password !== confirmPassword) {
                errors.push('Passwords do not match');
            }
            
            if (!['customer', 'pharmacy'].includes(role)) {
                errors.push('Please select a valid role');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = errors.join('\n');
                
                // Remove any existing error messages
                const existingError = registrationForm.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Insert error message at the top of the form
                registrationForm.insertBefore(errorDiv, registrationForm.firstChild);
            }
        });
    }
    
    // Login form validation
    const loginForm = document.querySelector('.log-in form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const password = this.querySelector('input[name="password"]').value;
            
            let errors = [];
            
            if (!validateEmail(email)) {
                errors.push('Please enter a valid email address');
            }
            
            if (!password) {
                errors.push('Please enter your password');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = errors.join('\n');
                
                // Remove any existing error messages
                const existingError = loginForm.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                // Insert error message at the top of the form
                loginForm.insertBefore(errorDiv, loginForm.firstChild);
            }
        });
    }
    
    // Check URL parameters for showing registration form
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('action') && urlParams.get('action') === 'register') {
        showRegistrationForm();
    }
    
    // Show registration form if there are registration errors
    if (document.querySelector('.sign-up .error-message')) {
        showRegistrationForm();
    }
    
    // Show success message in a modal if registration was successful
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.remove();
            }, 300);
        }, 5000);
    }
});