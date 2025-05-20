<?php
require_once 'config.php';

try {
    // Create a test user
    $sql = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    $password = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->execute(['Test', 'User', 'test@example.com', $password, 'seller']);
    
    echo "Test user created successfully!\n";
    echo "Email: test@example.com\n";
    echo "Password: test123\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 