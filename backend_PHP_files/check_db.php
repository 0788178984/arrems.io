<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    // 1. Check database connection
    echo "Database connection status: Connected\n";
    echo "Database name: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n\n";

    // 2. Show all tables
    echo "Tables in database:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);

    // 3. Drop and recreate users table
    echo "\nRecreating users table...\n";
    
    // Drop table if exists
    $pdo->exec("DROP TABLE IF EXISTS users");
    
    // Create table
    $sql = "CREATE TABLE users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'seller', 'client', 'stakeholder') NOT NULL DEFAULT 'client',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
    )";
    
    $pdo->exec($sql);
    echo "Users table created successfully\n\n";
    
    // 4. Verify table structure
    echo "New table structure:\n";
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
    
    // 5. Insert test user
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $password = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->execute(['Test', 'User', 'test@example.com', $password, 'seller', 'active']);
    
    echo "\nTest user created successfully!\n";
    echo "Email: test@example.com\n";
    echo "Password: test123\n";
    
    // 6. Verify user data
    echo "\nVerifying user data:\n";
    $user = $pdo->query("SELECT * FROM users")->fetch(PDO::FETCH_ASSOC);
    print_r($user);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    
    // If database doesn't exist, create it
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO("mysql:host=" . DB_SERVER, DB_USERNAME, DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
            echo "Database created successfully\n";
            
            // Use the database
            $pdo->exec("USE " . DB_NAME);
            
            // Rerun the table creation
            echo "Retrying table creation...\n";
            $sql = "CREATE TABLE users (
                user_id INT PRIMARY KEY AUTO_INCREMENT,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'manager', 'seller', 'client', 'stakeholder') NOT NULL DEFAULT 'client',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
            )";
            
            $pdo->exec($sql);
            echo "Users table created successfully\n";
            
        } catch(PDOException $e2) {
            echo "Error creating database: " . $e2->getMessage() . "\n";
        }
    }
}
?> 