<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'arrems_realestate_db');

try {
    // First try to connect without database
    $pdo = new PDO("mysql:host=" . DB_SERVER, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    
    // Connect to the specific database
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
        DB_USERNAME,
        DB_PASSWORD,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        )
    );
    
    // For mysqli compatibility (used in some files)
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
} catch(Exception $e) {
    error_log("General Error: " . $e->getMessage());
    die(json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]));
}
?> 