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

try {
    // Get all unique tours based on the tourId in the description
    $stmt = $pdo->query("SELECT DISTINCT 
                            pm.property_id,
                            p.title,
                            p.description,
                            MIN(pm.created_at) as created_at,
                            JSON_EXTRACT(pm.description, '$.tourId') as tour_id
                        FROM property_media pm
                        JOIN properties p ON p.id = pm.property_id
                        WHERE pm.media_type = '3d_model'
                        AND pm.description LIKE '%tourId%'
                        GROUP BY JSON_EXTRACT(pm.description, '$.tourId')
                        ORDER BY pm.created_at DESC");

    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the tours as JSON
    header('Content-Type: application/json');
    echo json_encode($tours);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch tours: ' . $e->getMessage()]);
}
?> 