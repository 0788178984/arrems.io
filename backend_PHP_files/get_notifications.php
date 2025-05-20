<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session
$validation = validateSession();
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    $userId = $validation['user_id'];
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    
    // Build query
    $query = "
        SELECT notification_id, title, message, type, is_read, created_at
        FROM notifications
        WHERE user_id = ?
    ";
    
    if ($unreadOnly) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE user_id = ?
    ";
    
    if ($unreadOnly) {
        $countQuery .= " AND is_read = 0";
    }
    
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $totalResult = $stmt->get_result()->fetch_assoc();
    $total = $totalResult['total'];
    
    // Get notifications
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['notification_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $unreadResult = $stmt->get_result()->fetch_assoc();
    $unreadCount = $unreadResult['unread_count'];
    
    // Calculate pagination metadata
    $totalPages = ceil($total / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    sendResponse([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
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