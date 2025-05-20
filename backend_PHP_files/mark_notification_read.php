<?php
require_once 'config.php';
require_once 'utils.php';

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate user session
$validation = validateSession();
if (!$validation['success']) {
    sendResponse($validation);
}

try {
    // Get PUT data
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $validation['user_id'];
    
    // Check if marking all as read
    if (isset($data['mark_all']) && $data['mark_all']) {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            
            if ($affectedRows > 0) {
                logActivity($userId, 'mark_notifications_read', "Marked $affectedRows notifications as read");
            }
            
            sendResponse([
                'success' => true,
                'message' => "$affectedRows notifications marked as read",
                'affected_rows' => $affectedRows
            ]);
        } else {
            throw new Exception('Error marking notifications as read');
        }
    } else {
        // Validate required fields for single notification
        $required = ['notification_id'];
        $validation = validateRequiredFields($data, $required);
        if (!$validation['success']) {
            sendResponse($validation);
        }
        
        $notificationId = intval($data['notification_id']);
        
        // Check if notification exists and belongs to user
        $stmt = $conn->prepare("
            SELECT notification_id, is_read 
            FROM notifications 
            WHERE notification_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $notification = $result->fetch_assoc();
        
        if (!$notification) {
            sendResponse(['success' => false, 'message' => 'Notification not found']);
        }
        
        if ($notification['is_read']) {
            sendResponse(['success' => true, 'message' => 'Notification already marked as read']);
        }
        
        // Mark notification as read
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notificationId, $userId);
        
        if ($stmt->execute()) {
            logActivity($userId, 'mark_notification_read', "Marked notification #$notificationId as read");
            
            sendResponse([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } else {
            throw new Exception('Error marking notification as read');
        }
    }
} catch (Exception $e) {
    sendResponse(handleError($e));
}
?> 