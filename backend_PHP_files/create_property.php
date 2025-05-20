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
    $required = ['title', 'property_type', 'price', 'location'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    // Sanitize inputs
    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description'] ?? '');
    $propertyType = sanitizeInput($data['property_type']);
    $price = floatval($data['price']);
    $location = sanitizeInput($data['location']);
    $address = sanitizeInput($data['address'] ?? '');
    $bedrooms = intval($data['bedrooms'] ?? 0);
    $bathrooms = intval($data['bathrooms'] ?? 0);
    $area = floatval($data['area'] ?? 0);
    $userId = $validation['user_id'];
    
    // Insert property
    $stmt = $conn->prepare("
        INSERT INTO properties (
            title, description, property_type, price, location, 
            address, bedrooms, bathrooms, area, user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "sssdssiiid",
        $title, $description, $propertyType, $price, $location,
        $address, $bedrooms, $bathrooms, $area, $userId
    );
    
    if ($stmt->execute()) {
        $propertyId = $conn->insert_id;
        
        // Handle image uploads if any
        if (isset($_FILES['images'])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $file = [
                    'name' => $_FILES['images']['name'][$key],
                    'type' => $_FILES['images']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['images']['error'][$key],
                    'size' => $_FILES['images']['size'][$key]
                ];
                
                $upload = handleFileUpload($file, '../uploads/properties/');
                if ($upload['success']) {
                    // Save image record
                    $isPrimary = $key === 0; // First image is primary
                    $imageUrl = $upload['path'];
                    $stmt = $conn->prepare("
                        INSERT INTO property_images (property_id, image_url, is_primary)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("isi", $propertyId, $imageUrl, $isPrimary);
                    $stmt->execute();
                }
            }
        }
        
        // Log activity
        logActivity($userId, 'create_property', "Created property: $title");
        
        // Create notification for managers
        $stmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE role = 'manager' OR role = 'stakeholder'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            createNotification(
                $row['user_id'],
                'New Property Listed',
                "A new property '$title' has been listed for review.",
                'info'
            );
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Property created successfully',
            'property_id' => $propertyId
        ]);
    } else {
        throw new Exception('Error creating property');
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 