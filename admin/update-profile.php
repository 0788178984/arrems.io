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
    // Get and validate input
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('Please fill in all required fields');
    }

    // Check if email exists for other users
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $_SESSION['admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Email address already exists');
    }
    $stmt->close();

    // Update profile
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $_SESSION['admin_id']);
    
    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['admin_name'] = $first_name . ' ' . $last_name;
        $_SESSION['admin_email'] = $email;

        // Log the activity
        $action_type = 'profile_updated';
        $description = "Admin profile was updated";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iss", $_SESSION['admin_id'], $action_type, $description);
        $log_stmt->execute();
        $log_stmt->close();

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        throw new Exception('Error updating profile: ' . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 