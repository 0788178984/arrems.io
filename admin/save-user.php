<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
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
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    $password = $_POST['password'];

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        throw new Exception('Please fill in all required fields');
    }

    // Validate email
    if (!$email) {
        throw new Exception('Invalid email address');
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Email address already exists');
    }
    $stmt->close();

    // Validate role
    $valid_roles = ['agent', 'client', 'manager', 'buyer', 'seller', 'stakeholder'];
    if (!in_array($role, $valid_roles)) {
        throw new Exception('Invalid role selected');
    }

    // Validate status
    $valid_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status selected');
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute the insert statement
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $first_name, $last_name, $email, $phone, $hashed_password, $role, $status);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Log the activity
        $action_type = 'user_created';
        $description = "New user {$first_name} {$last_name} ({$role}) was created";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iss", $_SESSION['admin_id'], $action_type, $description);
        $log_stmt->execute();
        $log_stmt->close();

        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        throw new Exception('Error creating user: ' . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 