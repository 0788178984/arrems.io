<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a DELETE request
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if (!$user_id) {
        throw new Exception('Invalid user ID');
    }

    // Check if user exists and is not an admin
    $stmt = $conn->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }

    $user = $result->fetch_assoc();
    if ($user['role'] === 'admin') {
        throw new Exception('Cannot delete admin users');
    }
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete user's activities
        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete user's reviews
        $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Update properties to remove agent reference
        $stmt = $conn->prepare("UPDATE properties SET agent_id = NULL WHERE agent_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Log the activity
        $action_type = 'user_deleted';
        $description = "User {$user['first_name']} {$user['last_name']} was deleted";
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $_SESSION['admin_id'], $action_type, $description);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 