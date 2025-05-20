<?php
// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

try {
    // Prepare the SQL statement
    $stmt = $pdo->prepare("INSERT INTO property_media (property_id, media_type, file_path, title, description, is_primary, created_at) 
                          VALUES (:property_id, :media_type, :file_path, :title, :description, :is_primary, NOW())");

    // Execute with the data
    $stmt->execute([
        'property_id' => $data['property_id'],
        'media_type' => $data['media_type'],
        'file_path' => $data['file_path'],
        'title' => $data['title'],
        'description' => $data['description'],
        'is_primary' => $data['is_primary']
    ]);

    // Return success response
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId()
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save media: ' . $e->getMessage()]);
}
?> 