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

// Get tour ID from query parameter
$tourId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$tourId) {
    http_response_code(400);
    echo json_encode(['error' => 'Tour ID is required']);
    exit;
}

try {
    // Get tour details and all its media
    $stmt = $pdo->prepare("SELECT 
                            p.id as property_id,
                            p.title,
                            p.description,
                            pm.id as media_id,
                            pm.file_path,
                            pm.title as media_title,
                            pm.description as media_description,
                            pm.created_at
                        FROM property_media pm
                        JOIN properties p ON p.id = pm.property_id
                        WHERE pm.media_type = '3d_model'
                        AND JSON_EXTRACT(pm.description, '$.tourId') = :tourId
                        ORDER BY JSON_EXTRACT(pm.description, '$.sceneIndex')");

    $stmt->execute(['tourId' => $tourId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        http_response_code(404);
        echo json_encode(['error' => 'Tour not found']);
        exit;
    }

    // Format the response
    $tour = [
        'id' => $tourId,
        'property_id' => $results[0]['property_id'],
        'title' => $results[0]['title'],
        'description' => $results[0]['description'],
        'created_at' => $results[0]['created_at'],
        'media' => array_map(function($item) {
            return [
                'id' => $item['media_id'],
                'file_path' => $item['file_path'],
                'title' => $item['media_title'],
                'description' => $item['media_description']
            ];
        }, $results)
    ];

    // Return the tour data as JSON
    header('Content-Type: application/json');
    echo json_encode($tour);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch tour: ' . $e->getMessage()]);
}
?> 