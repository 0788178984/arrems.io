// Authentication related functions
const API_BASE_URL = 'backend_PHP_files';

// Available roles for registration
const AVAILABLE_ROLES = {
    BUYER: 'buyer',
    SELLER: 'seller',
    MANAGER: 'manager',
    STAKEHOLDER: 'stakeholder'
};

// Function to handle login
async function login(email, password) {
    try {
        if (!email || !password) {
            return { success: false, message: 'Please provide both email and password' };
        }

        if (!isValidEmail(email)) {
            return { success: false, message: 'Please enter a valid email address' };
        }

        const response = await fetch(`${API_BASE_URL}/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ email: email.trim(), password })
        });

        console.log('Login response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Login response:', data); // Debug log
        
        if (data.success) {
            // Store user data in localStorage
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Dispatch auth state change event
            const event = new Event('authStateChanged');
            document.dispatchEvent(event);
            
            return { success: true, message: data.message, user: data.user };
        } else {
            return { success: false, message: data.message || 'Login failed' };
        }
    } catch (error) {
        console.error('Login error:', error);
        return { 
            success: false, 
            message: `An error occurred during login: ${error.message}`,
            error: error 
        };
    }
}

// Function to handle signup
async function signup(firstName, lastName, email, password, role) {
    console.log('Signup function called with:', { firstName, lastName, email, role }); // Debug log

    try {
        // Client-side validation
        if (!firstName || !lastName || !email || !password || !role) {
            return { success: false, message: 'Please fill in all required fields including role' };
        }

        if (!isValidEmail(email)) {
            return { success: false, message: 'Please enter a valid email address' };
        }

        if (!isValidPassword(password)) {
            return { success: false, message: 'Password must be at least 8 characters long' };
        }

        if (!isValidRole(role)) {
            return { 
                success: false, 
                message: 'Invalid role selected. Please choose from: ' + Object.values(AVAILABLE_ROLES).join(', ')
            };
        }

        console.log('Making signup request to:', `${API_BASE_URL}/signup.php`); // Debug log
        
        const response = await fetch(`${API_BASE_URL}/signup.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ 
                first_name: firstName.trim(),
                last_name: lastName.trim(),
                email: email.trim(),
                password,
                role: role.toLowerCase()
            })
        });

        console.log('Signup response status:', response.status); // Debug log

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Signup response data:', data);
        
        if (data.success) {
            // Store user data in localStorage
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Dispatch auth state change event
            const event = new Event('authStateChanged');
            document.dispatchEvent(event);
            
            return { success: true, message: data.message, user: data.user };
        } else {
            return { success: false, message: data.message || 'Registration failed' };
        }
    } catch (error) {
        console.error('Signup error:', error);
        return { 
            success: false, 
            message: `An error occurred during signup: ${error.message}`,
            error: error 
        };
    }
}

// Function to check authentication status with server
async function checkAuthWithServer() {
    try {
        const response = await fetch(`${API_BASE_URL}/check_auth.php`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Auth check response:', data);
        
        if (data.success && data.authenticated) {
            // Update localStorage with latest user data
            localStorage.setItem('user', JSON.stringify(data.user));
            return true;
        } else {
            // Clear local storage if not authenticated
            localStorage.removeItem('user');
            return false;
        }
    } catch (error) {
        console.error('Auth check error:', error);
        return false;
    }
}

// Validation functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPassword(password) {
    return password && password.length >= 8;
}

function isValidRole(role) {
    const validRoles = Object.values(AVAILABLE_ROLES).map(r => r.toLowerCase());
    return validRoles.includes(role.toLowerCase());
}

// Function to check if user is logged in
function isLoggedIn() {
    const user = localStorage.getItem('user');
    return !!user;
}

// Function to get current user data
function getCurrentUser() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

// Function to get user's full name
function getUserFullName() {
    const user = getCurrentUser();
    return user ? `${user.first_name} ${user.last_name}` : '';
}

// Function to get user's role
function getUserRole() {
    const user = getCurrentUser();
    return user ? user.role : null;
}

// Function to get available roles
function getAvailableRoles() {
    return AVAILABLE_ROLES;
}

// Function to handle logout
async function logout() {
    try {
        // Call logout endpoint
        await fetch(`${API_BASE_URL}/logout.php`, {
            method: 'POST',
            credentials: 'include'
        });
        
        // Clear local storage
        localStorage.removeItem('user');
        
        // Dispatch auth state change event
        const event = new Event('authStateChanged');
        document.dispatchEvent(event);
        
        // Redirect to home page
        window.location.href = 'index.html';
    } catch (error) {
        console.error('Logout error:', error);
        // Still clear local storage and redirect on error
        localStorage.removeItem('user');
        window.location.href = 'index.html';
    }
}

// Check authentication with server on page load
document.addEventListener('DOMContentLoaded', async function() {
    // Check auth with server to ensure session is valid
    const isAuthenticated = await checkAuthWithServer();
    
    // Update UI based on authentication status
    if (typeof updateAuthUI === 'function') {
        updateAuthUI(isAuthenticated);
    }
});

// Export functions
window.auth = {
    login,
    signup,
    isLoggedIn,
    getCurrentUser,
    getUserFullName,
    getUserRole,
    getAvailableRoles,
    logout,
    checkAuth: checkAuthWithServer
}; 