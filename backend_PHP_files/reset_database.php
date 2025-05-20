<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'arrems_realestate_db');

// Connect without database selected
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop and recreate database
$sql = "DROP DATABASE IF EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Database dropped successfully\n";
} else {
    echo "Error dropping database: " . $conn->error . "\n";
}

$sql = "CREATE DATABASE " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Select the database
$conn->select_db(DB_NAME);

// Create users table
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

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully\n";
} else {
    echo "Error creating users table: " . $conn->error . "\n";
}

// Create properties table
$sql = "CREATE TABLE properties (
    property_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('available', 'sold', 'rented', 'pending') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Properties table created successfully\n";
} else {
    echo "Error creating properties table: " . $conn->error . "\n";
}

// Create virtual_tours table
$sql = "CREATE TABLE virtual_tours (
    tour_id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    tour_url VARCHAR(255) NOT NULL,
    thumbnail_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Virtual tours table created successfully\n";
} else {
    echo "Error creating virtual tours table: " . $conn->error . "\n";
}

// Create tour_media table
$sql = "CREATE TABLE tour_media (
    media_id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    media_type ENUM('image', 'video', 'model') NOT NULL DEFAULT 'image',
    media_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES virtual_tours(tour_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tour media table created successfully\n";
} else {
    echo "Error creating tour media table: " . $conn->error . "\n";
}

// Create notifications table
$sql = "CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') NOT NULL DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully\n";
} else {
    echo "Error creating notifications table: " . $conn->error . "\n";
}

// Create activity_logs table
$sql = "CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Activity logs table created successfully\n";
} else {
    echo "Error creating activity logs table: " . $conn->error . "\n";
}

$conn->close();
echo "Database reset complete!\n";
?> 