<?php
// Error handling to prevent any HTML in output
ob_start(); // Start output buffering

// Include session configuration
require_once 'session_config.php';
require_once 'config.php';

// Ensure only JSON is output, even if errors occur
function outputJSON($data) {
    // Clear any previous output
    ob_end_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    
    // Set CORS headers - use the actual origin instead of wildcard
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowedOrigins = [
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
        'http://127.0.0.1:3000'
    ];
    
    if ($origin && in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    // Output JSON data
    echo json_encode($data);
    exit;
}

// Log error without displaying it in the response
function logError($message, $details = '') {
    error_log("Login error: $message " . ($details ? "Details: $details" : ""));
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    outputJSON(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    outputJSON(['success' => false, 'message' => 'Invalid request method']);
}

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear any existing session data if user was previously logged in
    if (isset($_SESSION['user_id'])) {
        session_unset();
        session_regenerate_id(true);
    }

    // Get input data - try both JSON and form POST
    $email = null;
    $password = null;
    
    // Check if this is a form POST
    if (isset($_POST['email']) && isset($_POST['password'])) {
        logError("Received form POST data");
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
    } else {
        // Get JSON input
        $json = file_get_contents('php://input');
        logError("Received JSON data: " . $json);

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("JSON decode error: " . json_last_error_msg());
            outputJSON(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]);
        }

        // Validate input
        if (!isset($data['email']) || !isset($data['password'])) {
            logError("Missing required fields");
            outputJSON(['success' => false, 'message' => 'Please provide both email and password']);
        }

        $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
        $password = $data['password'];
    }
    
    logError("Attempting login for email: " . $email);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logError("Invalid email format: " . $email);
        outputJSON(['success' => false, 'message' => 'Invalid email format']);
    }

    // Get user from database
    $stmt = $pdo->prepare("
        SELECT id, email, password, first_name, last_name, role, status
        FROM users 
        WHERE email = ?
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logError("User not found: " . $email);
        outputJSON(['success' => false, 'message' => 'Invalid email or password']);
    }

    logError("User found: " . print_r($user, true));

    // Check if account is active
    if ($user['status'] !== 'active') {
        logError("Account not active: " . $email);
        outputJSON(['success' => false, 'message' => 'Your account is not active. Please contact support.']);
    }

    // Verify password
    $passwordVerified = password_verify($password, $user['password']);
    logError("Password verification result: " . ($passwordVerified ? 'true' : 'false'));
    
    if (!$passwordVerified) {
        logError("Invalid password for user: " . $email);
        outputJSON(['success' => false, 'message' => 'Invalid email or password']);
    }
    
    // Generate a new session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Set a session cookie that expires in 24 hours
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + 86400,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite']
    ]);
    
    logError("Session variables set: " . print_r($_SESSION, true));
    
    // Remove sensitive data before sending response
    unset($user['password']);
    unset($user['status']);
    
    logError("Login successful for user: " . $email);
    
    // Return success response with session ID and cookie settings
    outputJSON([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user,
        'session_id' => session_id(),
        'session_name' => session_name(),
        'cookie_params' => $params
    ]);

} catch (PDOException $e) {
    logError("Database error: " . $e->getMessage());
    outputJSON([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    logError("General error: " . $e->getMessage());
    outputJSON([
        'success' => false,
        'message' => 'An error occurred during login. Please try again.'
    ]);
}

// Make sure we catch any errors that might have been missed
ob_end_flush();
?> 