<?php
require_once 'config.php';

try {
    // Temporarily disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Check if the users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "Users table doesn't exist. Creating it now...\n";
        
        // Create the users table
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
        echo "Users table created successfully!\n";
    } else {
        echo "Users table exists. Verifying structure...\n";
        
        // Get the table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('user_id', $columns)) {
            echo "user_id column missing. Attempting to fix...\n";
            
            // Drop any existing backup table
            $pdo->exec("DROP TABLE IF EXISTS users_backup");
            echo "Cleaned up any existing backup table...\n";
            
            // Backup the existing table
            $pdo->exec("CREATE TABLE users_backup AS SELECT * FROM users");
            echo "Created backup of users table...\n";
            
            // Drop the existing table
            $pdo->exec("DROP TABLE IF EXISTS users");
            echo "Dropped existing users table...\n";
            
            // Recreate the table with correct structure
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
            echo "Recreated users table with correct structure...\n";
            
            // Restore the data
            $pdo->exec("INSERT INTO users (first_name, last_name, email, password, role, created_at, updated_at, status) 
                       SELECT first_name, last_name, email, password, role, created_at, updated_at, status 
                       FROM users_backup");
            echo "Restored data from backup...\n";
            
            // Drop the backup table
            $pdo->exec("DROP TABLE IF EXISTS users_backup");
            echo "Removed backup table...\n";
            
            echo "Table structure fixed successfully!\n";
        } else {
            echo "Table structure is correct.\n";
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Database verification complete!\n";
    
} catch (PDOException $e) {
    // Re-enable foreign key checks even if there's an error
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (PDOException $e2) {
        // Ignore any errors here
    }
    
    die("ERROR: " . $e->getMessage() . "\n");
}
?> 