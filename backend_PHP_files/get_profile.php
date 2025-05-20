<?php
// Error handling to prevent any HTML in output
ob_start(); // Start output buffering

// Include configuration files
require_once 'config.php';
require_once 'session_config.php';

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
    error_log("Profile error: $message " . ($details ? "Details: $details" : ""));
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    outputJSON(['success' => true]);
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    outputJSON(['success' => false, 'message' => 'Method not allowed']);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logError("Unauthorized access attempt");
    outputJSON(['success' => false, 'message' => 'Unauthorized access']);
}

try {
    $userId = $_SESSION['user_id'];
    logError("Fetching profile for user: " . $userId);
    
    // Get user data
    $userStmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, role 
        FROM users 
        WHERE user_id = ?
    ");
    
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        logError("User not found: " . $userId);
        outputJSON(['success' => false, 'message' => 'User not found']);
    }
    
    // Get profile data if it exists
    $profileStmt = $pdo->prepare("
        SELECT phone_number, profile_photo_url, address, bio, created_at, updated_at
        FROM user_profiles 
        WHERE user_id = ?
    ");
    
    $profileStmt->execute([$userId]);
    $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    // If profile doesn't exist yet, create a default empty one
    if (!$profileData) {
        logError("Profile not found, creating default");
        $profileData = [
            'phone_number' => '',
            'profile_photo_url' => '',
            'address' => '',
            'bio' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Insert default profile
        $insertStmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, created_at, updated_at)
            VALUES (?, NOW(), NOW())
        ");
        
        $insertStmt->execute([$userId]);
    }
    
    // Format response data
    $responseData = [
        'user_info' => $userData,
        'profile_info' => $profileData
    ];
    
    outputJSON([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    logError("Error fetching profile: " . $e->getMessage());
    outputJSON([
        'success' => false,
        'message' => 'An error occurred while retrieving profile data'
    ]);
}

// Make sure we catch any errors that might have been missed
ob_end_flush();
?> 