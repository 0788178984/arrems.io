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
    $required = ['tour_id'];
    $validation = validateRequiredFields($data, $required);
    if (!$validation['success']) {
        sendResponse($validation);
    }
    
    $tourId = intval($data['tour_id']);
    $userId = $validation['user_id'];
    
    // Check if tour exists and user has permission
    $stmt = $conn->prepare("
        SELECT vt.*, p.user_id, p.title 
        FROM virtual_tours vt
        JOIN properties p ON vt.property_id = p.property_id
        WHERE vt.tour_id = ?
    ");
    $stmt->bind_param("i", $tourId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tour = $result->fetch_assoc();
    
    if (!$tour) {
        sendResponse(['success' => false, 'message' => 'Virtual tour not found']);
    }
    
    // Only allow property owner or manager/stakeholder to update tour
    if ($tour['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        sendResponse(['success' => false, 'message' => 'You do not have permission to update this tour']);
    }
    
    // Start building update query
    $updateFields = [];
    $types = "";
    $values = [];
    
    // Update tour URL if provided
    if (isset($data['tour_url'])) {
        $updateFields[] = "tour_url = ?";
        $types .= "s";
        $values[] = sanitizeInput($data['tour_url']);
    }
    
    // Update status if provided
    if (isset($data['status'])) {
        $updateFields[] = "status = ?";
        $types .= "s";
        $values[] = sanitizeInput($data['status']);
    }
    
    // Handle new thumbnail if provided
    if (isset($_FILES['thumbnail'])) {
        $upload = handleFileUpload($_FILES['thumbnail'], '../uploads/tours/thumbnails/');
        if ($upload['success']) {
            // Delete old thumbnail if exists
            if ($tour['thumbnail_url']) {
                $oldThumbPath = '../uploads/tours/thumbnails/' . $tour['thumbnail_url'];
                if (file_exists($oldThumbPath)) {
                    unlink($oldThumbPath);
                }
            }
            
            $updateFields[] = "thumbnail_url = ?";
            $types .= "s";
            $values[] = $upload['path'];
        }
    }
    
    if (empty($updateFields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update']);
    }
    
    // Add tour_id to values array and types
    $types .= "i";
    $values[] = $tourId;
    
    // Prepare and execute update query
    $query = "UPDATE virtual_tours SET " . implode(", ", $updateFields) . " WHERE tour_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        // Handle new media files if any
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
        
        // Delete media if specified
        if (isset($data['delete_media']) && is_array($data['delete_media'])) {
            $stmt = $conn->prepare("
                SELECT media_url FROM tour_media 
                WHERE tour_id = ? AND media_id = ?
            ");
            
            $deleteStmt = $conn->prepare("
                DELETE FROM tour_media 
                WHERE tour_id = ? AND media_id = ?
            ");
            
            foreach ($data['delete_media'] as $mediaId) {
                // Get media URL before deleting
                $stmt->bind_param("ii", $tourId, $mediaId);
                $stmt->execute();
                $result = $stmt->get_result();
                $media = $result->fetch_assoc();
                
                if ($media) {
                    // Delete file
                    $mediaPath = '../uploads/tours/media/' . $media['media_url'];
                    if (file_exists($mediaPath)) {
                        unlink($mediaPath);
                    }
                    
                    // Delete record
                    $deleteStmt->bind_param("ii", $tourId, $mediaId);
                    $deleteStmt->execute();
                }
            }
        }
        
        // Log activity
        logActivity($userId, 'update_tour', "Updated virtual tour for property: {$tour['title']}");
        
        // Create notification for property owner if updated by manager/stakeholder
        if ($tour['user_id'] !== $userId) {
            createNotification(
                $tour['user_id'],
                'Virtual Tour Updated',
                "The virtual tour for your property '{$tour['title']}' has been updated",
                'info'
            );
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Virtual tour updated successfully'
        ]);
    } else {
        throw new Exception('Error updating virtual tour');
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 