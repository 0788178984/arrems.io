<?php
require_once 'config.php';

try {
    // Get table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table structure:\n";
    print_r($columns);
    
    // Check if table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nNumber of users: " . $count['count'];
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 