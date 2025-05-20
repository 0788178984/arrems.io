<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

try {
    // Get search parameters
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $propertyType = isset($_GET['property_type']) ? sanitizeInput($_GET['property_type']) : '';
    $location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
    $minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : PHP_FLOAT_MAX;
    $minBeds = isset($_GET['min_beds']) ? intval($_GET['min_beds']) : 0;
    $minBaths = isset($_GET['min_baths']) ? intval($_GET['min_baths']) : 0;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $sortBy = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'created_at';
    $sortOrder = isset($_GET['sort_order']) ? (strtoupper($_GET['sort_order']) === 'DESC' ? 'DESC' : 'ASC') : 'DESC';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    // Build base query
    $query = "
        SELECT 
            p.*,
            pi.image_url as primary_image,
            COALESCE(vt.tour_count, 0) as tour_count,
            COALESCE(f.favorite_count, 0) as favorite_count
        FROM properties p
        LEFT JOIN (
            SELECT property_id, image_url 
            FROM property_images 
            WHERE is_primary = 1
        ) pi ON p.property_id = pi.property_id
        LEFT JOIN (
            SELECT property_id, COUNT(*) as tour_count 
            FROM virtual_tours 
            GROUP BY property_id
        ) vt ON p.property_id = vt.property_id
        LEFT JOIN (
            SELECT property_id, COUNT(*) as favorite_count 
            FROM favorites 
            GROUP BY property_id
        ) f ON p.property_id = f.property_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = "";
    
    // Add search conditions
    if ($search) {
        $query .= " AND (
            p.title LIKE ? OR 
            p.description LIKE ? OR 
            p.location LIKE ? OR 
            p.address LIKE ?
        )";
        $searchTerm = "%$search%";
        $types .= "ssss";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Add filter conditions
    if ($propertyType) {
        $query .= " AND p.property_type = ?";
        $types .= "s";
        $params[] = $propertyType;
    }
    
    if ($location) {
        $query .= " AND p.location LIKE ?";
        $types .= "s";
        $params[] = "%$location%";
    }
    
    if ($minPrice > 0) {
        $query .= " AND p.price >= ?";
        $types .= "d";
        $params[] = $minPrice;
    }
    
    if ($maxPrice < PHP_FLOAT_MAX) {
        $query .= " AND p.price <= ?";
        $types .= "d";
        $params[] = $maxPrice;
    }
    
    if ($minBeds > 0) {
        $query .= " AND p.bedrooms >= ?";
        $types .= "i";
        $params[] = $minBeds;
    }
    
    if ($minBaths > 0) {
        $query .= " AND p.bathrooms >= ?";
        $types .= "i";
        $params[] = $minBaths;
    }
    
    if ($status) {
        $query .= " AND p.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    
    // Add sorting
    $allowedSortFields = ['price', 'created_at', 'favorite_count', 'tour_count'];
    $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
    $query .= " ORDER BY $sortBy $sortOrder";
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    
    // Get total count for pagination
    $countQuery = preg_replace('/SELECT.*FROM/', 'SELECT COUNT(*) as total FROM', $query);
    $countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);
    $countQuery = preg_replace('/LIMIT.*$/', '', $countQuery);
    
    $stmt = $conn->prepare($countQuery);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result()->fetch_assoc();
    $total = $totalResult['total'];
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $properties = [];
    while ($row = $result->fetch_assoc()) {
        $property = formatPropertyData($row);
        $property['primary_image'] = $row['primary_image'];
        $property['tour_count'] = $row['tour_count'];
        $property['favorite_count'] = $row['favorite_count'];
        
        // Get all images for the property
        $stmt = $conn->prepare("
            SELECT image_url, is_primary 
            FROM property_images 
            WHERE property_id = ?
        ");
        $stmt->bind_param("i", $row['property_id']);
        $stmt->execute();
        $imagesResult = $stmt->get_result();
        
        $property['images'] = [];
        while ($image = $imagesResult->fetch_assoc()) {
            $property['images'][] = [
                'url' => $image['image_url'],
                'is_primary' => $image['is_primary']
            ];
        }
        
        // Get virtual tours
        $stmt = $conn->prepare("
            SELECT tour_id, tour_url, thumbnail_url, status 
            FROM virtual_tours 
            WHERE property_id = ?
        ");
        $stmt->bind_param("i", $row['property_id']);
        $stmt->execute();
        $toursResult = $stmt->get_result();
        
        $property['virtual_tours'] = [];
        while ($tour = $toursResult->fetch_assoc()) {
            $property['virtual_tours'][] = $tour;
        }
        
        $properties[] = $property;
    }
    
    // Calculate pagination metadata
    $totalPages = ceil($total / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    sendResponse([
        'success' => true,
        'data' => [
            'properties' => $properties,
            'pagination' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage
            ]
        ]
    ]);
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 