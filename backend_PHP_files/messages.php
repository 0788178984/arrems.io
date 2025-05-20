<?php
require_once 'config.php';
require_once 'check_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure user is authenticated
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Send new message
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['receiver_id']) || !isset($data['message'])) {
                throw new Exception('Missing required parameters');
            }

            $receiverId = (int)$data['receiver_id'];
            $propertyId = isset($data['property_id']) ? (int)$data['property_id'] : null;
            $subject = $data['subject'] ?? null;
            $message = $data['message'];

            // Verify receiver exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$receiverId]);
            if (!$stmt->fetch()) {
                throw new Exception('Receiver not found');
            }

            // Send message
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, property_id, subject, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $receiverId, $propertyId, $subject, $message]);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'sent_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'GET':
            // Get messages
            $conversationWith = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
            $propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;

            $query = "
                SELECT m.*, 
                       CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                       CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                       p.title as property_title
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                LEFT JOIN properties p ON m.property_id = p.id
                WHERE (m.sender_id = ? OR m.receiver_id = ?)
            ";
            $params = [$user['id'], $user['id']];

            if ($conversationWith) {
                $query .= " AND (m.sender_id = ? OR m.receiver_id = ?)";
                $params[] = $conversationWith;
                $params[] = $conversationWith;
            }

            if ($unreadOnly) {
                $query .= " AND m.is_read = 0 AND m.receiver_id = ?";
                $params[] = $user['id'];
            }

            if ($propertyId) {
                $query .= " AND m.property_id = ?";
                $params[] = $propertyId;
            }

            $query .= " ORDER BY m.created_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $messages
            ]);
            break;

        case 'PUT':
            // Mark messages as read
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['message_ids']) || !is_array($data['message_ids'])) {
                throw new Exception('Missing or invalid message IDs');
            }

            $messageIds = array_map('intval', $data['message_ids']);
            $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';

            // Mark messages as read
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE id IN ($placeholders) 
                AND receiver_id = ?
            ");
            $params = array_merge($messageIds, [$user['id']]);
            $stmt->execute($params);

            echo json_encode([
                'status' => 'success',
                'message' => 'Messages marked as read'
            ]);
            break;

        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 