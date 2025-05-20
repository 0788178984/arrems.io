<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['event_type', 'property_id'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    $eventType = sanitizeInput($data['event_type']);
    $propertyId = intval($data['property_id']);
    $tourId = isset($data['tour_id']) ? intval($data['tour_id']) : null;
    $userId = null;
    
    // Get user ID if logged in
    session_start();
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    // Validate event type
    $allowedEvents = ['view', 'favorite', 'inquiry', 'share'];
    if (!in_array($eventType, $allowedEvents)) {
        sendResponse(['success' => false, 'message' => 'Invalid event type']);
    }
    
    // Check if property exists
    $stmt = $conn->prepare("SELECT property_id FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $propertyId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        sendResponse(['success' => false, 'message' => 'Property not found']);
    }
    
    // Check if tour exists if tour_id is provided
    if ($tourId) {
        $stmt = $conn->prepare("
            SELECT tour_id FROM virtual_tours 
            WHERE tour_id = ? AND property_id = ?
        ");
        $stmt->bind_param("ii", $tourId, $propertyId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            sendResponse(['success' => false, 'message' => 'Tour not found or does not belong to the specified property']);
        }
    }
    
    // Prevent duplicate events within a short time window (e.g., 5 minutes)
    if ($userId) {
        $stmt = $conn->prepare("
            SELECT analytics_id 
            FROM analytics 
            WHERE user_id = ? 
            AND property_id = ? 
            AND event_type = ? 
            AND event_date > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->bind_param("iis", $userId, $propertyId, $eventType);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            sendResponse(['success' => true, 'message' => 'Event already recorded']);
        }
    }
    
    // Record the event
    $stmt = $conn->prepare("
        INSERT INTO analytics (
            property_id, tour_id, user_id, event_type, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt->bind_param(
        "iiisss",
        $propertyId,
        $tourId,
        $userId,
        $eventType,
        $ipAddress,
        $userAgent
    );
    
    if ($stmt->execute()) {
        // Create notification for property owner if it's an inquiry
        if ($eventType === 'inquiry') {
            $stmt = $conn->prepare("
                SELECT p.user_id, p.title, u.first_name, u.last_name 
                FROM properties p
                LEFT JOIN users u ON u.user_id = ?
                WHERE p.property_id = ?
            ");
            $stmt->bind_param("ii", $userId, $propertyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            if ($data) {
                $inquirerName = $userId ? "{$data['first_name']} {$data['last_name']}" : "A guest";
                createNotification(
                    $data['user_id'],
                    'New Property Inquiry',
                    "$inquirerName has inquired about your property '{$data['title']}'",
                    'info'
                );
            }
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Event recorded successfully'
        ]);
    } else {
        throw new Exception('Error recording event');
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 