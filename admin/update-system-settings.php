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
    $site_title = trim($_POST['site_title']);
    $contact_email = filter_var(trim($_POST['contact_email']), FILTER_VALIDATE_EMAIL);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;

    // Validate required fields
    if (empty($site_title) || !$contact_email) {
        throw new Exception('Please fill in all required fields');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update or insert settings
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) 
                              VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        // Update site title
        $stmt->bind_param("ss", $key, $value);
        $key = 'site_title';
        $value = $site_title;
        $stmt->execute();

        // Update contact email
        $key = 'contact_email';
        $value = $contact_email;
        $stmt->execute();

        // Update maintenance mode
        $key = 'maintenance_mode';
        $value = $maintenance_mode;
        $stmt->execute();

        $stmt->close();

        // Log the activity
        $action_type = 'settings_updated';
        $description = "System settings were updated";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iss", $_SESSION['admin_id'], $action_type, $description);
        $log_stmt->execute();
        $log_stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 