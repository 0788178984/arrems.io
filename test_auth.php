<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>ARREMS Authentication Test</h1>";

// Test database connection
echo "<h2>Database Connection</h2>";
try {
    require_once 'backend_PHP_files/config.php';
    echo "<p style='color:green'>✓ Database connection successful</p>";
    
    // Test users table
    echo "<h2>Users Table Test</h2>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "<p>Found $count users in the database</p>";
    
    // Show first user details (without password)
    $stmt = $pdo->prepare("SELECT user_id, email, first_name, last_name, role, status FROM users LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "<p>Sample user: " . htmlspecialchars(print_r($user, true)) . "</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test PHP version
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Password hash functions: " . (function_exists('password_hash') ? "Available" : "Not Available") . "</p>";

// Test session functions
echo "<h2>Session Test</h2>";
require_once 'backend_PHP_files/session_config.php';
echo "<p>Session name: " . session_name() . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Not active") . "</p>";

// Test password functions
echo "<h2>Password Functions Test</h2>";
$testPassword = 'password123';
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
echo "<p>Generated hash: " . htmlspecialchars($hash) . "</p>";
echo "<p>Password verification test: " . (password_verify($testPassword, $hash) ? "Passed" : "Failed") . "</p>";

// Try to verify stored password for test user
echo "<h2>Stored Password Verification Test</h2>";
try {
    $email = 'test@example.com';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $storedHash = $stmt->fetchColumn();
    
    if ($storedHash) {
        echo "<p>Stored hash for test@example.com: " . htmlspecialchars($storedHash) . "</p>";
        $verifyResult = password_verify('password123', $storedHash);
        echo "<p>Verification with 'password123': " . ($verifyResult ? "Succeeds" : "Fails") . "</p>";
    } else {
        echo "<p>Test user test@example.com not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error testing stored password: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// File system checks
echo "<h2>File System Checks</h2>";
$authFilePath = 'assets/js/auth.js';
$loginFilePath = 'backend_PHP_files/login.php';
$sessionConfigPath = 'backend_PHP_files/session_config.php';

echo "<p>auth.js exists: " . (file_exists($authFilePath) ? "Yes" : "No") . "</p>";
echo "<p>login.php exists: " . (file_exists($loginFilePath) ? "Yes" : "No") . "</p>";
echo "<p>session_config.php exists: " . (file_exists($sessionConfigPath) ? "Yes" : "No") . "</p>";

echo "<h2>CORS Headers Test</h2>";
echo "<p>Current Origin: " . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'None/Unknown') . "</p>";
echo "<p>Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "</p>";
echo "<p>HTTP/HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'HTTPS' : 'HTTP') . "</p>";

// Test direct login
echo "<h2>Direct Login Test</h2>";
echo "<form method='post' action='backend_PHP_files/login.php'>
    <p><input type='email' name='email' value='test@example.com'></p>
    <p><input type='password' name='password' value='password123'></p>
    <p><button type='submit'>Test Direct Login</button></p>
</form>";

// Show PHP info for reference
echo "<h2>PHP Info Summary</h2>";
echo "<pre>";
ob_start();
phpinfo(INFO_MODULES);
$phpinfo = ob_get_clean();
// Extract session info
if (preg_match('/<h2>session<\/h2>.*?<table.*?>(.*?)<\/table>/s', $phpinfo, $matches)) {
    echo htmlspecialchars($matches[1]);
}
echo "</pre>";
?> 