<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session and role
$validation = validateSession(['seller', 'manager', 'stakeholder']);
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    // Get PUT data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['property_id'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    $propertyId = intval($data['property_id']);
    $userId = $validation['user_id'];
    
    // Check if user has permission to update this property
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
    
    // Only allow property owner or manager/stakeholder to update
    if ($property['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        sendResponse(['success' => false, 'message' => 'You do not have permission to update this property']);
    }
    
    // Build update query dynamically based on provided fields
    $updateFields = [];
    $types = "";
    $values = [];
    
    $allowedFields = [
        'title' => 's',
        'description' => 's',
        'property_type' => 's',
        'price' => 'd',
        'location' => 's',
        'address' => 's',
        'bedrooms' => 'i',
        'bathrooms' => 'i',
        'area' => 'd',
        'status' => 's'
    ];
    
    foreach ($allowedFields as $field => $type) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $types .= $type;
            $values[] = $type === 'd' ? floatval($data[$field]) : 
                       ($type === 'i' ? intval($data[$field]) : 
                       sanitizeInput($data[$field]));
        }
    }
    
    if (empty($updateFields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update']);
    }
    
    // Add property_id to values array and types
    $types .= "i";
    $values[] = $propertyId;
    
    // Prepare and execute update query
    $query = "UPDATE properties SET " . implode(", ", $updateFields) . " WHERE property_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        // Handle new image uploads if any
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
                    $imageUrl = $upload['path'];
                    $stmt = $conn->prepare("
                        INSERT INTO property_images (property_id, image_url)
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param("is", $propertyId, $imageUrl);
                    $stmt->execute();
                }
            }
        }
        
        // Delete images if specified
        if (isset($data['delete_images']) && is_array($data['delete_images'])) {
            $stmt = $conn->prepare("
                DELETE FROM property_images 
                WHERE property_id = ? AND image_id = ?
            ");
            
            foreach ($data['delete_images'] as $imageId) {
                $stmt->bind_param("ii", $propertyId, $imageId);
                $stmt->execute();
            }
        }
        
        // Log activity
        logActivity($userId, 'update_property', "Updated property ID: $propertyId");
        
        // Create notification for property owner if updated by manager/stakeholder
        if ($property['user_id'] !== $userId) {
            createNotification(
                $property['user_id'],
                'Property Updated',
                "Your property (ID: $propertyId) has been updated by a " . $_SESSION['role'],
                'info'
            );
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Property updated successfully'
        ]);
    } else {
        throw new Exception('Error updating property');
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 