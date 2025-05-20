<?php
require_once 'config.php';

// Function to validate user session and role
function validateSession($allowedRoles = []) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'Unauthorized access'];
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        return ['success' => false, 'message' => 'Access denied for your role'];
    }
    
    return ['success' => true, 'user_id' => $_SESSION['user_id']];
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to log user activity
function logActivity($userId, $action, $details = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $userId, $action, $details, $ipAddress);
    return $stmt->execute();
}

// Function to create notification
function createNotification($userId, $title, $message, $type = 'info') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    return $stmt->execute();
}

// Function to handle file upload
function handleFileUpload($file, $targetDir = '../uploads/') {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    
    // Validate file type
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $fileName];
    }
    
    return ['success' => false, 'message' => 'Error uploading file'];
}

// Function to send response
function sendResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Function to validate required fields
function validateRequiredFields($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    return ['success' => true];
}

// Function to format property data
function formatPropertyData($row) {
    return [
        'property_id' => $row['property_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'property_type' => $row['property_type'],
        'price' => $row['price'],
        'location' => $row['location'],
        'address' => $row['address'],
        'bedrooms' => $row['bedrooms'],
        'bathrooms' => $row['bathrooms'],
        'area' => $row['area'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}

// Function to handle errors
function handleError($e) {
    error_log($e->getMessage());
    return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
}
?> 