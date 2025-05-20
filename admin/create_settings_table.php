<?php
require_once '../config/database.php';

try {
    // Create settings table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "Settings table created successfully.<br>";
        
        // Insert default settings if they don't exist
        $default_settings = [
            'site_title' => 'ARREMS',
            'contact_email' => 'contact@arrems.com',
            'maintenance_mode' => '0'
        ];
        
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        
        foreach ($default_settings as $key => $value) {
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        
        echo "Default settings initialized.";
    } else {
        echo "Error creating settings table: " . $conn->error;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 