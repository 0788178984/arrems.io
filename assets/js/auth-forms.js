// Utility functions for showing messages
function showSuccess(message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add to container
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    container.appendChild(toast);
    
    // Show toast - ensure Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 5000
        });
        bsToast.show();
        
        // Remove after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
            if (container.children.length === 0) {
                container.remove();
            }
        });
    } else {
        // Fallback if Bootstrap is not loaded
        console.log('Success:', message);
        alert(message);
        toast.remove();
    }
}

function showError(message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-danger border-0';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add to container
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    container.appendChild(toast);
    
    // Show toast - ensure Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 5000
        });
        bsToast.show();
        
        // Remove after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
            if (container.children.length === 0) {
                container.remove();
            }
        });
    } else {
        // Fallback if Bootstrap is not loaded
        console.log('Error:', message);
        alert(message);
        toast.remove();
    }
}

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get the login form
    const loginForm = document.querySelector('#loginForm');
    const registerForm = document.querySelector('#registerForm');
    const forgotPasswordForm = document.querySelector('#forgotPasswordForm');
    console.log('Register form found:', registerForm); // Debug log
    
    // Registration form handler
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('Form submission started'); // Debug log
            
            // Get form values
            const fullName = document.querySelector('#registerName').value;
            const email = document.querySelector('#registerEmail').value;
            const password = document.querySelector('#registerPassword').value;
            const role = document.querySelector('#registerRole').value;
            const termsAccepted = document.querySelector('#termsCheck').checked;
            
            console.log('Form values:', { fullName, email, role, termsAccepted }); // Debug log
            
            // Client-side validation
            if (!termsAccepted) {
                showError('Please accept the Terms of Service and Privacy Policy');
                return;
            }

            if (!role) {
                showError('Please select your role');
                return;
            }
            
            // Split full name into first and last name
            const [firstName, ...lastNameParts] = fullName.trim().split(' ');
            const lastName = lastNameParts.join(' ') || '';
            
            if (!lastName) {
                showError('Please enter your full name (first and last name)');
                return;
            }

            try {
                // Show loading state
                const submitButton = registerForm.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = 'Creating Account...';

                console.log('Calling signup with:', { firstName, lastName, email, role }); // Debug log

                // Call the signup function from auth.js
                const result = await window.auth.signup(firstName, lastName, email, password, role);
                console.log('Signup result:', result); // Debug log
                
                if (result.success) {
                    // Show success message
                    showSuccess('Registration successful! Redirecting to login...');
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1500);
                } else {
                    showError(result.message || 'Registration failed. Please try again.');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showError('An error occurred during registration. Please try again.');
            } finally {
                // Restore button state
                const submitButton = registerForm.querySelector('button[type="submit"]');
                submitButton.disabled = false;
                submitButton.textContent = 'Create Account';
            }
        });
    }
    
    // Login form handler
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.querySelector('#loginEmail').value;
            const password = document.querySelector('#loginPassword').value;
            
            try {
                // Show loading state
                const submitButton = loginForm.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = 'Signing In...';

                // Call the login function from auth.js
                const result = await window.auth.login(email, password);
                
                if (result.success) {
                    // Show success message
                    showSuccess('Login successful! Redirecting...');
                    
                    // Close the login modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('signInModal'));
                    if (modal) {
                        modal.hide();
                    }

                    // Determine redirect based on user role
                    let redirectPath = 'dashboard.html';
                    if (result.user && result.user.role) {
                        switch(result.user.role.toLowerCase()) {
                            case 'admin':
                                redirectPath = 'admin/dashboard.html';
                                break;
                            case 'agent':
                                redirectPath = 'agent/dashboard.html';
                                break;
                            case 'manager':
                                redirectPath = 'manager/dashboard.html';
                                break;
                            case 'buyer':
                            case 'seller':
                                redirectPath = 'user/dashboard.html';
                                break;
                            case 'stakeholder':
                                redirectPath = 'stakeholder/dashboard.html';
                                break;
                            default:
                                redirectPath = 'dashboard.html';
                        }
                    }
                    
                    // Update UI before redirect
                    document.querySelector('.header-right').innerHTML = `
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i>
                                ${result.user.first_name}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                                <li><a class="dropdown-item" href="${redirectPath}"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.html"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="window.auth.logout()"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                            </ul>
                        </div>
                    `;
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = redirectPath;
                    }, 1500);
                } else {
                    showError(result.message || 'Login failed. Please try again.');
                }
            } catch (error) {
                console.error('Login error:', error);
                showError('An error occurred during login. Please try again.');
            } finally {
                // Restore button state
                const submitButton = loginForm.querySelector('button[type="submit"]');
                submitButton.disabled = false;
                submitButton.textContent = 'Sign In';
            }
        });
    }
    
    // Forgot password form handler
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.querySelector('#forgotEmail').value;
            
            try {
                const response = await fetch('backend_PHP_files/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    alert('Password reset instructions have been sent to your email.');
                    window.location.href = 'login.html';
                } else {
                    alert(data.message || 'Failed to process password reset request. Please try again.');
                }
            } catch (error) {
                console.error('Password reset error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    }

    // Function to update UI based on authentication state
    function updateAuthUI(isLoggedIn) {
        const headerRight = document.querySelector('.header-right');
        const mobileAuth = document.querySelector('.mobile-auth');
        
        if (isLoggedIn) {
            const user = auth.getCurrentUser();
            const userFullName = auth.getUserFullName();
            const userRole = auth.getUserRole();
            
            // Update desktop header
            if (headerRight) {
                headerRight.innerHTML = `
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-2"></i>${userFullName}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.html">
                                <i class="fas fa-user-circle me-2"></i>My Profile
                            </a></li>
                            ${!['buyer'].includes(userRole.toLowerCase()) ? `
                            <li><a class="dropdown-item" href="tours.html">
                                <i class="fas fa-vr-cardboard me-2"></i>My Tours
                            </a></li>
                            ` : ''}
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="auth.logout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </div>
                `;
            }
            
            // Update mobile menu
            if (mobileAuth) {
                mobileAuth.innerHTML = `
                    <div class="user-info mb-3">
                        <h6 class="mb-1">${userFullName}</h6>
                        <p class="mb-0 text-muted">${user.email}</p>
                        <small class="text-primary">${userRole}</small>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="profile.html" class="btn btn-outline-primary">
                            <i class="fas fa-user-circle me-2"></i>My Profile
                        </a>
                        <button onclick="auth.logout()" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </button>
                    </div>
                `;
            }
        } else {
            // Restore original login/register buttons
            if (headerRight) {
                headerRight.innerHTML = `
                    <a href="#" class="btn register-btn" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
                    <a href="#" class="btn signin-btn" data-bs-toggle="modal" data-bs-target="#signInModal">Sign In</a>
                `;
            }
            
            // Restore mobile auth buttons
            if (mobileAuth) {
                mobileAuth.innerHTML = `
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#signInModal">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                        <a href="#" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#registerModal">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                    </div>
                `;
            }
        }
    }

    // Check authentication state on page load
    function safeUpdateAuthUI() {
        // Check if auth object exists and has required functions
        if (window.auth && typeof window.auth.isLoggedIn === 'function') {
            updateAuthUI(auth.isLoggedIn());
        } else {
            console.warn('Auth object not properly initialized - retrying in 500ms');
            setTimeout(safeUpdateAuthUI, 500);
        }
    }
    
    // Use setTimeout to ensure auth.js has loaded
    setTimeout(safeUpdateAuthUI, 300);
}); 