<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Test data
$testUsers = [
    [
        'first_name' => 'Test',
        'last_name' => 'Buyer',
        'email' => 'test.buyer@example.com',
        'password' => 'password123',
        'role' => 'buyer'
    ],
    [
        'first_name' => 'Test',
        'last_name' => 'Seller',
        'email' => 'test.seller@example.com',
        'password' => 'password123',
        'role' => 'seller'
    ],
    [
        'first_name' => 'Test',
        'last_name' => 'Manager',
        'email' => 'test.manager@example.com',
        'password' => 'password123',
        'role' => 'manager'
    ],
    [
        'first_name' => 'Test',
        'last_name' => 'Stakeholder',
        'email' => 'test.stakeholder@example.com',
        'password' => 'password123',
        'role' => 'stakeholder'
    ]
];

$results = [];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, delete any existing test users
    $stmt = $pdo->prepare("DELETE FROM users WHERE email LIKE 'test.%@example.com'");
    $stmt->execute();
    
    // Test each user registration
    foreach ($testUsers as $user) {
        try {
            $password = password_hash($user['password'], PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $password,
                $user['role']
            ]);
            
            $results[] = [
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => 'success',
                'message' => 'User registered successfully'
            ];
            
        } catch (PDOException $e) {
            $results[] = [
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Verify the registrations
    $stmt = $pdo->query("SELECT email, role FROM users WHERE email LIKE 'test.%@example.com'");
    $verifiedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Test completed',
        'registration_results' => $results,
        'verified_users' => $verifiedUsers
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Test failed: ' . $e->getMessage()
    ]);
}
?> 