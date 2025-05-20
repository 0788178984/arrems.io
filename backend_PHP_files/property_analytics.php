<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session and role
$validation = validateSession(['seller', 'manager', 'stakeholder']);
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    $userId = $validation['user_id'];
    $propertyId = isset($_GET['property_id']) ? intval($_GET['property_id']) : null;
    $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
    
    // Validate property access if property_id is provided
    if ($propertyId) {
        $stmt = $conn->prepare("
            SELECT user_id FROM properties WHERE property_id = ?
        ");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $property = $result->fetch_assoc();
        
        if (!$property) {
            sendResponse(['success' => false, 'message' => 'Property not found']);
        }
        
        // Only allow property owner or manager/stakeholder to view analytics
        if ($property['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
            sendResponse(['success' => false, 'message' => 'You do not have permission to view this property\'s analytics']);
        }
    }
    
    // Build base query
    $baseQuery = "
        SELECT 
            DATE(event_date) as date,
            event_type,
            COUNT(*) as count
        FROM analytics
        WHERE event_date BETWEEN ? AND ?
    ";
    
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($propertyId) {
        $baseQuery .= " AND property_id = ?";
        $params[] = $propertyId;
        $types .= "i";
    } else if (!in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        // If not manager/stakeholder, only show own properties
        $baseQuery .= " AND property_id IN (SELECT property_id FROM properties WHERE user_id = ?)";
        $params[] = $userId;
        $types .= "i";
    }
    
    $baseQuery .= " GROUP BY DATE(event_date), event_type ORDER BY date ASC";
    
    // Get daily analytics
    $stmt = $conn->prepare($baseQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dailyAnalytics = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($dailyAnalytics[$date])) {
            $dailyAnalytics[$date] = [
                'date' => $date,
                'views' => 0,
                'favorites' => 0,
                'inquiries' => 0,
                'shares' => 0
            ];
        }
        $dailyAnalytics[$date][$row['event_type'] . 's'] = $row['count'];
    }
    
    // Get total counts
    $totalQuery = "
        SELECT 
            event_type,
            COUNT(*) as total
        FROM analytics
        WHERE event_date BETWEEN ? AND ?
    ";
    
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($propertyId) {
        $totalQuery .= " AND property_id = ?";
        $params[] = $propertyId;
        $types .= "i";
    } else if (!in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        $totalQuery .= " AND property_id IN (SELECT property_id FROM properties WHERE user_id = ?)";
        $params[] = $userId;
        $types .= "i";
    }
    
    $totalQuery .= " GROUP BY event_type";
    
    $stmt = $conn->prepare($totalQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totals = [
        'views' => 0,
        'favorites' => 0,
        'inquiries' => 0,
        'shares' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $totals[$row['event_type'] . 's'] = $row['total'];
    }
    
    // Get top properties if no specific property is selected
    $topProperties = [];
    if (!$propertyId) {
        $topQuery = "
            SELECT 
                p.property_id,
                p.title,
                COUNT(*) as total_events,
                SUM(CASE WHEN a.event_type = 'view' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN a.event_type = 'favorite' THEN 1 ELSE 0 END) as favorites,
                SUM(CASE WHEN a.event_type = 'inquiry' THEN 1 ELSE 0 END) as inquiries,
                SUM(CASE WHEN a.event_type = 'share' THEN 1 ELSE 0 END) as shares
            FROM analytics a
            JOIN properties p ON a.property_id = p.property_id
            WHERE event_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        $types = "ss";
        
        if (!in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
            $topQuery .= " AND p.user_id = ?";
            $params[] = $userId;
            $types .= "i";
        }
        
        $topQuery .= " GROUP BY p.property_id ORDER BY total_events DESC LIMIT 5";
        
        $stmt = $conn->prepare($topQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $topProperties[] = [
                'property_id' => $row['property_id'],
                'title' => $row['title'],
                'total_events' => $row['total_events'],
                'views' => $row['views'],
                'favorites' => $row['favorites'],
                'inquiries' => $row['inquiries'],
                'shares' => $row['shares']
            ];
        }
    }
    
    sendResponse([
        'success' => true,
        'data' => [
            'daily_analytics' => array_values($dailyAnalytics),
            'totals' => $totals,
            'top_properties' => $topProperties,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]
    ]);
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 