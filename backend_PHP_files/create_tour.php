<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session and role
$validation = validateSession(['seller', 'manager', 'stakeholder']);
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['property_id', 'tour_url'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    $propertyId = intval($data['property_id']);
    $tourUrl = sanitizeInput($data['tour_url']);
    $userId = $validation['user_id'];
    
    // Check if property exists and user has permission
    $stmt = $conn->prepare("
        SELECT user_id, title FROM properties WHERE property_id = ?
    ");
    $stmt->bind_param("i", $propertyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    
    if (!$property) {
        sendResponse(['success' => false, 'message' => 'Property not found']);
    }
    
    // Only allow property owner or manager/stakeholder to add tour
    if ($property['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        sendResponse(['success' => false, 'message' => 'You do not have permission to add tour to this property']);
    }
    
    // Handle thumbnail upload if provided
    $thumbnailUrl = null;
    if (isset($_FILES['thumbnail'])) {
        $upload = handleFileUpload($_FILES['thumbnail'], '../uploads/tours/thumbnails/');
        if ($upload['success']) {
            $thumbnailUrl = $upload['path'];
        }
    }
    
    // Create virtual tour
    $stmt = $conn->prepare("
        INSERT INTO virtual_tours (property_id, tour_url, thumbnail_url)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $propertyId, $tourUrl, $thumbnailUrl);
    
    if ($stmt->execute()) {
        $tourId = $conn->insert_id;
        
        // Handle additional media files if any
        if (isset($_FILES['media'])) {
            foreach ($_FILES['media']['tmp_name'] as $key => $tmp_name) {
                $file = [
                    'name' => $_FILES['media']['name'][$key],
                    'type' => $_FILES['media']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['media']['error'][$key],
                    'size' => $_FILES['media']['size'][$key]
                ];
                
                $upload = handleFileUpload($file, '../uploads/tours/media/');
                if ($upload['success']) {
                    $mediaUrl = $upload['path'];
                    $mediaType = isset($data['media_types'][$key]) ? 
                                sanitizeInput($data['media_types'][$key]) : 'image';
                    
                    $stmt = $conn->prepare("
                        INSERT INTO tour_media (tour_id, media_type, media_url)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iss", $tourId, $mediaType, $mediaUrl);
                    $stmt->execute();
                }
            }
        }
        
        // Log activity
        logActivity($userId, 'create_tour', "Created virtual tour for property: {$property['title']}");
        
        // Create notification for property owner if tour added by manager/stakeholder
        if ($property['user_id'] !== $userId) {
            createNotification(
                $property['user_id'],
                'Virtual Tour Added',
                "A virtual tour has been added to your property '{$property['title']}'",
                'info'
            );
        }
        
        // Create notification for managers and stakeholders
        $stmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE role IN ('manager', 'stakeholder') 
            AND user_id != ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            createNotification(
                $row['user_id'],
                'New Virtual Tour',
                "A new virtual tour has been added to property '{$property['title']}'",
                'info'
            );
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Virtual tour created successfully',
            'tour_id' => $tourId
        ]);
    } else {
        throw new Exception('Error creating virtual tour');
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 