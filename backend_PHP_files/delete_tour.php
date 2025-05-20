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
    
    // Only allow property owner or manager/stakeholder to delete tour
    if ($tour['user_id'] !== $userId && !in_array($_SESSION['role'], ['manager', 'stakeholder'])) {
        sendResponse(['success' => false, 'message' => 'You do not have permission to delete this tour']);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete thumbnail if exists
        if ($tour['thumbnail_url']) {
            $thumbPath = '../uploads/tours/thumbnails/' . $tour['thumbnail_url'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        
        // Delete tour media files and records
        $stmt = $conn->prepare("
            SELECT media_url FROM tour_media WHERE tour_id = ?
        ");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($media = $result->fetch_assoc()) {
            $mediaPath = '../uploads/tours/media/' . $media['media_url'];
            if (file_exists($mediaPath)) {
                unlink($mediaPath);
            }
        }
        
        // Delete from tour_media table
        $stmt = $conn->prepare("DELETE FROM tour_media WHERE tour_id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        
        // Delete from analytics table
        $stmt = $conn->prepare("DELETE FROM analytics WHERE tour_id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        
        // Finally, delete the tour
        $stmt = $conn->prepare("DELETE FROM virtual_tours WHERE tour_id = ?");
        $stmt->bind_param("i", $tourId);
        
        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            
            // Log activity
            logActivity($userId, 'delete_tour', "Deleted virtual tour for property: {$tour['title']}");
            
            // Create notification for property owner if deleted by manager/stakeholder
            if ($tour['user_id'] !== $userId) {
                createNotification(
                    $tour['user_id'],
                    'Virtual Tour Deleted',
                    "The virtual tour for your property '{$tour['title']}' has been deleted",
                    'warning'
                );
            }
            
            sendResponse([
                'success' => true,
                'message' => 'Virtual tour deleted successfully'
            ]);
        } else {
            throw new Exception('Error deleting virtual tour');
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