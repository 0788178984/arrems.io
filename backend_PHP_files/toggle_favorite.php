<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['tour_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        // Check if favorite already exists
        $stmt = $pdo->prepare("
            SELECT id FROM favorites 
            WHERE user_id = ? AND tour_id = ?
        ");
        $stmt->execute([$data['user_id'], $data['tour_id']]);
        $favorite = $stmt->fetch();

        if ($favorite) {
            // Remove favorite
            $stmt = $pdo->prepare("
                DELETE FROM favorites 
                WHERE user_id = ? AND tour_id = ?
            ");
            $stmt->execute([$data['user_id'], $data['tour_id']]);
            $message = 'Removed from favorites';
        } else {
            // Add favorite
            $stmt = $pdo->prepare("
                INSERT INTO favorites (user_id, tour_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$data['user_id'], $data['tour_id']]);
            $message = 'Added to favorites';
        }

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to toggle favorite: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 