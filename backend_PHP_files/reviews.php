<?php
require_once 'config.php';
require_once 'check_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
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
            // Create new review
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['property_id']) || !isset($data['rating'])) {
                throw new Exception('Missing required parameters');
            }

            $propertyId = (int)$data['property_id'];
            $rating = (int)$data['rating'];
            $comment = $data['comment'] ?? null;

            // Validate rating
            if ($rating < 1 || $rating > 5) {
                throw new Exception('Rating must be between 1 and 5');
            }

            // Check if user has already reviewed this property
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE property_id = ? AND user_id = ?");
            $stmt->execute([$propertyId, $user['id']]);
            if ($stmt->fetch()) {
                throw new Exception('You have already reviewed this property');
            }

            // Create review
            $stmt = $pdo->prepare("
                INSERT INTO reviews (property_id, user_id, rating, comment)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$propertyId, $user['id'], $rating, $comment]);

            // Fetch the created review with user details
            $stmt = $pdo->prepare("
                SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.profile_image as reviewer_image
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$pdo->lastInsertId()]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $review
            ]);
            break;

        case 'GET':
            // Get reviews
            $propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

            $query = "
                SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.profile_image as reviewer_image,
                       p.title as property_title
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                JOIN properties p ON r.property_id = p.id
                WHERE 1=1
            ";
            $params = [];

            if ($propertyId) {
                $query .= " AND r.property_id = ?";
                $params[] = $propertyId;
            }

            if ($userId) {
                $query .= " AND r.user_id = ?";
                $params[] = $userId;
            }

            $query .= " ORDER BY r.created_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If property ID is provided, get summary statistics
            $summary = null;
            if ($propertyId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_reviews,
                        AVG(rating) as average_rating,
                        COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
                        COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
                        COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
                        COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
                        COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
                    FROM reviews
                    WHERE property_id = ?
                ");
                $stmt->execute([$propertyId]);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'reviews' => $reviews,
                    'summary' => $summary
                ]
            ]);
            break;

        case 'PUT':
            // Update review
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['review_id']) || !isset($data['rating'])) {
                throw new Exception('Missing required parameters');
            }

            $reviewId = (int)$data['review_id'];
            $rating = (int)$data['rating'];
            $comment = $data['comment'] ?? null;

            // Validate rating
            if ($rating < 1 || $rating > 5) {
                throw new Exception('Rating must be between 1 and 5');
            }

            // Verify review exists and user has permission
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = ? AND user_id = ?");
            $stmt->execute([$reviewId, $user['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Review not found or permission denied');
            }

            // Update review
            $stmt = $pdo->prepare("
                UPDATE reviews 
                SET rating = ?, comment = ?
                WHERE id = ?
            ");
            $stmt->execute([$rating, $comment, $reviewId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Review updated successfully'
            ]);
            break;

        case 'DELETE':
            // Delete review
            $reviewId = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if (!$reviewId) {
                throw new Exception('Missing review ID');
            }

            // Verify review exists and user has permission
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = ? AND user_id = ?");
            $stmt->execute([$reviewId, $user['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Review not found or permission denied');
            }

            // Delete review
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Review deleted successfully'
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