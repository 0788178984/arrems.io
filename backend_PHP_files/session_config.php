<?php
// Make sure we enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Session config loaded"); // Debug log

// Set session garbage collection probability
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 86400); // 24 hours

// Ensure session directory exists and is writable
$sessionSavePath = session_save_path();
error_log("Session save path: " . $sessionSavePath);
if (!is_writable($sessionSavePath)) {
    error_log("WARNING: Session save path is not writable!");
}

// Check if we're running on HTTPS
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
error_log("HTTPS detection: " . ($secure ? 'true' : 'false'));

// Set session name to be consistent
session_name('ARREMS_SESSION');

// Get the current host
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$domain = $host ? preg_replace('/:\d+$/', '', $host) : '';

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => $domain,  // Use the current domain
    'secure' => $secure, // Use HTTPS when available
    'httponly' => true, // Prevent JavaScript access to session cookie
    'samesite' => 'Lax' // Allow cross-site requests for better usability
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $sessionStartResult = session_start();
    error_log("Session start result: " . ($sessionStartResult ? 'success' : 'failed'));
    
    if (!$sessionStartResult) {
        error_log("Failed to start session. PHP version: " . phpversion());
    }
}

// Set session security options
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_trans_sid', 0);
ini_set('session.cache_limiter', 'nocache');

// Session activity monitoring
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    // If last activity was more than 1 hour ago
    session_unset();
    session_destroy();
    error_log("Session destroyed due to inactivity");
} else {
    $_SESSION['last_activity'] = time();
}

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    error_log("Session ID regenerated (initial)");
} else if (time() - $_SESSION['last_regeneration'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    error_log("Session ID regenerated (hourly)");
}

// Function to check if user is logged in
function isLoggedIn() {
    $result = isset($_SESSION['user_id']) && 
             isset($_SESSION['logged_in']) && 
             $_SESSION['logged_in'] === true &&
             isset($_SESSION['last_activity']) &&
             (time() - $_SESSION['last_activity'] <= 3600);
             
    error_log("isLoggedIn check: " . ($result ? 'true' : 'false') . ", Session: " . print_r($_SESSION, true));
    return $result;
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }
}
?> 