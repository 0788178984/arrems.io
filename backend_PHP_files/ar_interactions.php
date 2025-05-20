<?php
require_once 'config.php';
require_once 'check_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
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
            // Record new AR interaction
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['property_id']) || !isset($data['interaction_type'])) {
                throw new Exception('Missing required parameters');
            }

            $propertyId = (int)$data['property_id'];
            $interactionType = $data['interaction_type'];
            $duration = isset($data['duration']) ? (int)$data['duration'] : null;
            $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;

            // Verify property exists
            $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            if (!$stmt->fetch()) {
                throw new Exception('Property not found');
            }

            // Record interaction
            $stmt = $pdo->prepare("
                INSERT INTO ar_interactions (property_id, user_id, interaction_type, duration, metadata)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$propertyId, $user['id'], $interactionType, $duration, $metadata]);

            // Update property analytics
            $stmt = $pdo->prepare("
                INSERT INTO property_analytics (property_id, ar_views_count)
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE 
                ar_views_count = ar_views_count + 1,
                last_viewed_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$propertyId]);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'property_id' => $propertyId,
                    'interaction_type' => $interactionType,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'GET':
            // Get AR interactions
            $propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $type = $_GET['type'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            // Build query
            $query = "SELECT ar.*, p.title as property_title, u.first_name, u.last_name
                     FROM ar_interactions ar
                     JOIN properties p ON ar.property_id = p.id
                     JOIN users u ON ar.user_id = u.id
                     WHERE 1=1";
            $params = [];

            if ($propertyId) {
                $query .= " AND ar.property_id = ?";
                $params[] = $propertyId;
            }

            if ($userId) {
                $query .= " AND ar.user_id = ?";
                $params[] = $userId;
            }

            if ($type) {
                $query .= " AND ar.interaction_type = ?";
                $params[] = $type;
            }

            if ($startDate) {
                $query .= " AND ar.created_at >= ?";
                $params[] = $startDate;
            }

            if ($endDate) {
                $query .= " AND ar.created_at <= ?";
                $params[] = $endDate;
            }

            // Add sorting
            $query .= " ORDER BY ar.created_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get analytics summary if property ID is provided
            $analytics = null;
            if ($propertyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_interactions,
                        COUNT(DISTINCT user_id) as unique_users,
                        AVG(duration) as avg_duration,
                        COUNT(CASE WHEN interaction_type = 'view' THEN 1 END) as views,
                        COUNT(CASE WHEN interaction_type = 'measure' THEN 1 END) as measurements,
                        COUNT(CASE WHEN interaction_type = 'annotate' THEN 1 END) as annotations,
                        COUNT(CASE WHEN interaction_type = 'walkthrough' THEN 1 END) as walkthroughs
                    FROM ar_interactions
                    WHERE property_id = ?
                ");
                $stmt->execute([$propertyId]);
                $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'interactions' => $interactions,
                    'analytics' => $analytics
                ]
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