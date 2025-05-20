<?php
// Error handling to prevent any HTML in output
ob_start(); // Start output buffering

require_once 'session_config.php';
require_once 'config.php';

// Ensure only JSON is output, even if errors occur
function outputJSON($data) {
    // Clear any previous output
    ob_end_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Output JSON data
    echo json_encode($data);
    exit;
}

// Log error without displaying it in the response
function logError($message, $details = '') {
    error_log("Auth check error: $message " . ($details ? "Details: $details" : ""));
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    outputJSON(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    outputJSON(['success' => false, 'message' => 'Invalid request method']);
}

// Check if user is logged in
if (isLoggedIn()) {
    try {
        // Fetch user data from database
        $stmt = $pdo->prepare("
            SELECT user_id, email, first_name, last_name, role, status
            FROM users 
            WHERE user_id = ? AND status = 'active'
        ");
        
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Remove sensitive data
            unset($user['status']);
            
            outputJSON([
                'success' => true,
                'authenticated' => true,
                'message' => 'User is authenticated',
                'user' => $user
            ]);
        } else {
            // User found in session but not in database or not active
            session_destroy();
            outputJSON([
                'success' => true,
                'authenticated' => false,
                'message' => 'Session invalid'
            ]);
        }
    } catch (Exception $e) {
        logError("Database error: " . $e->getMessage());
        outputJSON([
            'success' => false,
            'authenticated' => false,
            'message' => 'Error checking authentication'
        ]);
    }
} else {
    outputJSON([
        'success' => true,
        'authenticated' => false,
        'message' => 'User not authenticated'
    ]);
}

// Make sure we catch any errors that might have been missed
ob_end_flush();
?> 