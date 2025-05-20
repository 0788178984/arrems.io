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
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Output JSON data
    echo json_encode($data);
    exit;
}

// Log error without displaying it in the response
function logError($message, $details = '') {
    error_log("Profile update error: $message " . ($details ? "Details: $details" : ""));
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    outputJSON(['success' => true]);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    logError("Updating profile for user: " . $userId);
    
    // Get JSON data from request
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        logError("Invalid JSON data");
        outputJSON(['success' => false, 'message' => 'Invalid data format']);
    }
    
    // Get profile data
    $firstName = $data['first_name'] ?? '';
    $lastName = $data['last_name'] ?? '';
    $phoneNumber = $data['phone_number'] ?? '';
    $address = $data['address'] ?? '';
    $bio = $data['bio'] ?? '';
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update user data
    $userStmt = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?
        WHERE user_id = ?
    ");
    
    $userStmt->execute([$firstName, $lastName, $userId]);
    
    // Check if profile exists
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_profiles WHERE user_id = ?
    ");
    
    $checkStmt->execute([$userId]);
    $profileExists = (int)$checkStmt->fetchColumn() > 0;
    
    if ($profileExists) {
        // Update existing profile
        $profileStmt = $pdo->prepare("
            UPDATE user_profiles 
            SET phone_number = ?, address = ?, bio = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        
        $profileStmt->execute([$phoneNumber, $address, $bio, $userId]);
    } else {
        // Create new profile
        $profileStmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, phone_number, address, bio, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        $profileStmt->execute([$userId, $phoneNumber, $address, $bio]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Update session data
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    
    outputJSON([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    logError("Error updating profile: " . $e->getMessage());
    outputJSON([
        'success' => false,
        'message' => 'An error occurred while updating profile: ' . $e->getMessage()
    ]);
}

// Make sure we catch any errors that might have been missed
ob_end_flush();
?> 