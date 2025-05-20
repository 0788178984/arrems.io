<?php
// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add status column if it doesn't exist
    $pdo->exec("ALTER TABLE property_media 
                ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'draft',
                ADD COLUMN IF NOT EXISTS view_url TEXT,
                ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    echo "Schema updated successfully!\n";
    echo "Added columns: status, view_url, updated_at\n";

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?> 