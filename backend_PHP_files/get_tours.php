<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Set defaults
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $limit = 9; // Items per page
    $offset = ($page - 1) * $limit;
    
    try {
        // Build the query
        $query = "SELECT t.*, 
                        p.title, p.price, p.location, p.property_type,
                        p.bedrooms, p.bathrooms, p.area
                 FROM tours t
                 JOIN properties p ON t.property_id = p.id
                 WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($data['property_type'])) {
            $query .= " AND p.property_type = ?";
            $params[] = $data['property_type'];
        }

        if (!empty($data['location'])) {
            $query .= " AND p.location = ?";
            $params[] = $data['location'];
        }

        if (!empty($data['price_range'])) {
            list($min, $max) = explode('-', $data['price_range']);
            if ($max === '+') {
                $query .= " AND p.price >= ?";
                $params[] = $min;
            } else {
                $query .= " AND p.price BETWEEN ? AND ?";
                $params[] = $min;
                $params[] = $max;
            }
        }

        // Add pagination
        $query .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        // Execute query
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total for pagination
        $countQuery = str_replace("t.*, \n                        p.title, p.price, p.location, p.property_type,\n                        p.bedrooms, p.bathrooms, p.area", "COUNT(*) as total", explode(" LIMIT", $query)[0]);
        $stmtCount = $pdo->prepare($countQuery);
        array_pop($params); // Remove offset
        array_pop($params); // Remove limit
        $stmtCount->execute($params);
        $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'success' => true,
            'tours' => $tours,
            'has_more' => ($offset + $limit) < $total,
            'total' => $total
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch tours: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 