<?php
session_start();

// Log the logout activity if admin is logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $db_host = 'localhost';
        $db_name = 'arrems_realestate_db';
        $db_user = 'root';
        $db_pass = '';

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, 'logout', 'Admin logout')");
        $logStmt->execute([$_SESSION['admin_id']]);
    } catch (PDOException $e) {
        // Continue with logout even if logging fails
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?> 