<?php
// Error handling to prevent any HTML in output
ob_start(); // Start output buffering

require_once 'config.php';
require_once 'session_config.php';

// Ensure only JSON is output, even if errors occur
function outputJSON($data) {
    // Clear any previous output
    ob_end_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Output JSON data
    echo json_encode($data);
    exit;
}

// Log error without displaying it in the response
function logError($message, $details = '') {
    error_log("Signup error: $message " . ($details ? "Details: $details" : ""));
}

// Enable error reporting for debugging (log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
logError("Signup request received"); // Debug log

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    outputJSON(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']); // Debug log
    outputJSON(['success' => false, 'message' => 'Invalid request method']);
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    logError("Received JSON data: " . $json); // Debug log

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON decode error: " . json_last_error_msg()); // Debug log
        outputJSON(['success' => false, 'message' => 'Invalid JSON data']);
    }

    // Validate input
    if (!$data || 
        !isset($data['first_name']) || 
        !isset($data['last_name']) || 
        !isset($data['email']) || 
        !isset($data['role'])) {
        logError("Missing required fields in data: " . print_r($data, true)); // Debug log
        outputJSON(['success' => false, 'message' => 'Missing required fields']);
    }

    // Sanitize input
    $first_name = filter_var(trim($data['first_name']), FILTER_SANITIZE_STRING);
    $last_name = filter_var(trim($data['last_name']), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    // Generate a random password if not provided
    $password = isset($data['password']) ? $data['password'] : bin2hex(random_bytes(8));
    $role = strtolower(filter_var(trim($data['role']), FILTER_SANITIZE_STRING));

    logError("Sanitized data: " . print_r([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'role' => $role
    ], true)); // Debug log

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logError("Invalid email format: " . $email); // Debug log
        outputJSON(['success' => false, 'message' => 'Invalid email format']);
    }

    // Validate role
    $valid_roles = ['admin', 'agent', 'client', 'manager', 'buyer', 'seller', 'stakeholder'];
    if (!in_array($role, $valid_roles)) {
        logError("Invalid role: " . $role); // Debug log
        outputJSON(['success' => false, 'message' => 'Invalid role selected']);
    }

    logError("Checking for existing email: " . $email); // Debug log
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        logError("Email already exists: " . $email); // Debug log
        outputJSON(['success' => false, 'message' => 'Email already exists']);
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Begin transaction
    $pdo->beginTransaction();
    logError("Starting transaction"); // Debug log

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            password, 
            role,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $hashed_password,
        $role
    ]);
    
    $userId = $pdo->lastInsertId();
    logError("New user created with ID: " . $userId); // Debug log

    // Commit transaction
    $pdo->commit();
    logError("Transaction committed"); // Debug log
    
    // Start session for the new user
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['logged_in'] = true;
    
    logError("Session started for user: " . $userId); // Debug log
    
    // Return success response with user data
    outputJSON([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'user_id' => $userId,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        ]
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logError("Transaction rolled back"); // Debug log
    }
    
    $errorMessage = 'Registration failed';
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'Email already exists';
    }
    
    logError("Database error: " . $e->getMessage()); // Debug log
    outputJSON([
        'success' => false, 
        'message' => $errorMessage
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logError("Transaction rolled back"); // Debug log
    }
    
    logError("Registration error: " . $e->getMessage()); // Debug log
    outputJSON([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

// Make sure we catch any errors that might have been missed
ob_end_flush();
?> 