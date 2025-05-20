<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password length
    if (strlen($new_password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    // Validate passwords match
    if ($new_password !== $confirm_password) {
        throw new Exception('New passwords do not match');
    }

    // Get current user's password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $_SESSION['admin_id']);
    
    if ($stmt->execute()) {
        // Log the activity
        $action_type = 'password_changed';
        $description = "Admin password was changed";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iss", $_SESSION['admin_id'], $action_type, $description);
        $log_stmt->execute();
        $log_stmt->close();

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        throw new Exception('Error updating password: ' . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 