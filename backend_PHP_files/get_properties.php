<?php
require_once 'config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Get query parameters with defaults
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $city = isset($_GET['city']) ? $_GET['city'] : null;
    $bedrooms = isset($_GET['bedrooms']) ? (int)$_GET['bedrooms'] : null;

    // Calculate offset
    $offset = ($page - 1) * $limit;

    // Build base query
    $query = "SELECT p.*, 
              u.first_name as agent_first_name, 
              u.last_name as agent_last_name,
              u.email as agent_email,
              u.phone as agent_phone,
              COUNT(DISTINCT pm.id) as total_media,
              COUNT(DISTINCT f.id) as favorite_count
              FROM properties p 
              LEFT JOIN users u ON p.agent_id = u.id
              LEFT JOIN property_media pm ON p.id = pm.property_id
              LEFT JOIN favorites f ON p.id = f.property_id";

    $conditions = [];
    $params = [];

    // Add filters
    if ($type) {
        $conditions[] = "p.type = ?";
        $params[] = $type;
    }
    if ($status) {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    }
    if ($minPrice) {
        $conditions[] = "p.price >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice) {
        $conditions[] = "p.price <= ?";
        $params[] = $maxPrice;
    }
    if ($city) {
        $conditions[] = "p.city LIKE ?";
        $params[] = "%$city%";
    }
    if ($bedrooms) {
        $conditions[] = "p.bedrooms = ?";
        $params[] = $bedrooms;
    }

    // Add WHERE clause if there are conditions
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    // Add GROUP BY
    $query .= " GROUP BY p.id";

    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT p.id) as total FROM properties p";
    if (!empty($conditions)) {
        $countQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Execute main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each property, fetch its media and features
    foreach ($properties as &$property) {
        // Fetch property media
        $mediaStmt = $pdo->prepare("
            SELECT id, media_type, file_path, title, is_primary 
            FROM property_media 
            WHERE property_id = ?
        ");
        $mediaStmt->execute([$property['id']]);
        $property['media'] = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch property features
        $featuresStmt = $pdo->prepare("
            SELECT feature_name, feature_value 
            FROM property_features 
            WHERE property_id = ?
        ");
        $featuresStmt->execute([$property['id']]);
        $property['features'] = $featuresStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'properties' => $properties,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_records' => $totalCount,
                'records_per_page' => $limit
            ]
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 