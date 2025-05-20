<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session and role
$validation = validateSession(['seller', 'manager', 'stakeholder']);
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    // Get DELETE data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['property_id'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    $propertyId = intval($data['property_id']);
    $userId = $validation['user_id'];
    
    // Check if user has permission to delete this property
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
    
    // Only allow property owner or manager/stakeholder to delete
    if ($property['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        sendResponse(['success' => false, 'message' => 'You do not have permission to delete this property']);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete property images
        $stmt = $conn->prepare("
            SELECT image_url FROM property_images WHERE property_id = ?
        ");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $imagePath = '../uploads/properties/' . $row['image_url'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Delete from property_images table
        $stmt = $conn->prepare("DELETE FROM property_images WHERE property_id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        
        // Delete from virtual_tours table
        $stmt = $conn->prepare("DELETE FROM virtual_tours WHERE property_id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        
        // Delete from favorites table
        $stmt = $conn->prepare("DELETE FROM favorites WHERE property_id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        
        // Delete from analytics table
        $stmt = $conn->prepare("DELETE FROM analytics WHERE property_id = ?");
        $stmt->bind_param("i", $propertyId);
        $stmt->execute();
        
        // Finally, delete the property
        $stmt = $conn->prepare("DELETE FROM properties WHERE property_id = ?");
        $stmt->bind_param("i", $propertyId);
        
        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            
            // Log activity
            logActivity($userId, 'delete_property', "Deleted property: {$property['title']}");
            
            // Create notification for property owner if deleted by manager/stakeholder
            if ($property['user_id'] !== $userId) {
                createNotification(
                    $property['user_id'],
                    'Property Deleted',
                    "Your property '{$property['title']}' has been deleted by a " . $_SESSION['role'],
                    'warning'
                );
            }
            
            sendResponse([
                'success' => true,
                'message' => 'Property deleted successfully'
            ]);
        } else {
            throw new Exception('Error deleting property');
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 