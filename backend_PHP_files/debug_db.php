<?php
require_once 'config.php';

try {
    // Show all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:\n";
    print_r($tables);
    
    // Show users table structure if it exists
    if (in_array('users', $tables)) {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nUsers table structure:\n";
        print_r($columns);
    } else {
        echo "\nUsers table does not exist!\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 