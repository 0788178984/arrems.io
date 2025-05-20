<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/error.log');

$scriptDir = dirname(__FILE__);
$rootDir = dirname($scriptDir);
require_once $rootDir . '/backend_PHP_files/config.php';

try {
    echo "Starting database setup...\n";

    // Step 1: Drop existing tables if they exist (in correct order to respect foreign keys)
    $tables = [
        'reviews',
        'property_analytics',
        'property_features',
        'property_media',
        'ar_interactions',
        'appointments',
        'favorites',
        'messages',
        'properties',
        'users'
    ];

    // Disable foreign key checks temporarily
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    echo "Foreign key checks disabled.\n";

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "Dropped table if exists: $table\n";
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Foreign key checks re-enabled.\n";

    echo "Existing tables dropped successfully.\n";

    // Step 2: Create tables from SQL file
    echo "Reading SQL file...\n";
    $sqlFile = $scriptDir . '/arrems_realestate_db.sql';
    echo "SQL file path: $sqlFile\n";
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    echo "SQL file size: " . strlen($sql) . " bytes\n";
    echo "First 100 characters of SQL:\n" . substr($sql, 0, 100) . "\n";
    
    echo "Executing SQL schema...\n";
    $result = $pdo->exec($sql);
    
    if ($result === false) {
        $error = $pdo->errorInfo();
        throw new Exception("SQL execution failed: " . print_r($error, true));
    }
    
    echo "Database tables created successfully.\n";

    // Step 3: Run the seeding script
    echo "Starting data seeding...\n";
    require_once $scriptDir . '/seed_data.php';
    echo "Sample data inserted successfully.\n";

    // Step 4: Set up media directory and sample files
    $mediaDir = $rootDir . '/uploads/property_media/';
    if (!file_exists($mediaDir)) {
        mkdir($mediaDir, 0777, true);
        echo "Created media directory: $mediaDir\n";
    }

    // Create placeholder files instead of generating images
    $sampleFiles = [
        'sample_house_1.jpg' => 'Sample House 1 Image Placeholder',
        'sample_house_2.jpg' => 'Sample House 2 Image Placeholder',
        'house_model.glb' => 'Sample 3D Model Content',
        'property_tour.mp4' => 'Sample Video Content'
    ];

    foreach ($sampleFiles as $filename => $content) {
        file_put_contents($mediaDir . $filename, $content);
        echo "Created sample file: $filename\n";
    }

    echo "Sample media files created successfully.\n";

    echo json_encode([
        'status' => 'success',
        'message' => 'Database setup completed successfully!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo "Error stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    echo json_encode([
        'status' => 'error',
        'message' => 'Setup Error: ' . $e->getMessage()
    ]);
} 