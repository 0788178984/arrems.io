<?php
require_once 'config.php';

function initializeDatabase() {
    global $pdo;
    
    try {
        // Check if database exists
        $dbName = DB_NAME;
        $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
        $dbExists = $result->fetch();

        if (!$dbExists) {
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbName");
            $pdo->exec("USE $dbName");
            
            // Run the setup script
            require_once '../database/setup_database.php';
            return true;
        }

        // Check if tables are empty
        $result = $pdo->query("SELECT COUNT(*) as count FROM properties");
        $rowCount = $result->fetch(PDO::FETCH_ASSOC)['count'];

        if ($rowCount == 0) {
            // If tables are empty, run the setup script
            require_once '../database/setup_database.php';
            return true;
        }

        return true;

    } catch (PDOException $e) {
        // If there's an error (like tables don't exist), run the setup script
        if ($e->getCode() == '42S02') { // Table doesn't exist
            require_once '../database/setup_database.php';
            return true;
        }
        
        error_log("Database initialization error: " . $e->getMessage());
        return false;
    }
}

// Initialize the database
$initialized = initializeDatabase();

if (!$initialized) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to initialize database'
    ]);
    exit; 