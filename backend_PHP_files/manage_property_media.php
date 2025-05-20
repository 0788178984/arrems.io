<?php
require_once 'config.php';
require_once 'check_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, GET');
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
            // Handle media upload
            if (!isset($_POST['property_id']) || !isset($_FILES['media'])) {
                throw new Exception('Missing required parameters');
            }

            $propertyId = (int)$_POST['property_id'];
            $mediaType = $_POST['media_type'] ?? 'image';
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            $isPrimary = isset($_POST['is_primary']) ? (bool)$_POST['is_primary'] : false;

            // Verify property exists and user has permission
            $stmt = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            $property = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$property || ($property['agent_id'] !== $user['id'] && $user['role'] !== 'admin')) {
                throw new Exception('Permission denied or property not found');
            }

            // Handle file upload
            $uploadDir = '../uploads/property_media/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file = $_FILES['media'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = [
                'image' => ['jpg', 'jpeg', 'png', 'gif'],
                '3d_model' => ['glb', 'gltf', 'obj'],
                'ar_content' => ['usdz', 'glb', 'gltf'],
                'video' => ['mp4', 'webm']
            ];

            if (!isset($allowedTypes[$mediaType]) || !in_array($fileExt, $allowedTypes[$mediaType])) {
                throw new Exception('Invalid file type for selected media type');
            }

            $fileName = uniqid() . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to upload file');
            }

            // If this is set as primary, unset other primary media
            if ($isPrimary) {
                $stmt = $pdo->prepare("UPDATE property_media SET is_primary = 0 WHERE property_id = ?");
                $stmt->execute([$propertyId]);
            }

            // Save media record
            $stmt = $pdo->prepare("
                INSERT INTO property_media (property_id, media_type, file_path, title, description, is_primary)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$propertyId, $mediaType, $fileName, $title, $description, $isPrimary]);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $pdo->lastInsertId(),
                    'file_path' => $fileName,
                    'media_type' => $mediaType,
                    'title' => $title,
                    'is_primary' => $isPrimary
                ]
            ]);
            break;

        case 'DELETE':
            // Handle media deletion
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['media_id'])) {
                throw new Exception('Missing media ID');
            }

            $mediaId = (int)$data['media_id'];

            // Verify ownership and get file path
            $stmt = $pdo->prepare("
                SELECT pm.*, p.agent_id 
                FROM property_media pm
                JOIN properties p ON pm.property_id = p.id
                WHERE pm.id = ?
            ");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$media || ($media['agent_id'] !== $user['id'] && $user['role'] !== 'admin')) {
                throw new Exception('Permission denied or media not found');
            }

            // Delete file
            $filePath = '../uploads/property_media/' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete record
            $stmt = $pdo->prepare("DELETE FROM property_media WHERE id = ?");
            $stmt->execute([$mediaId]);

            echo json_encode(['status' => 'success', 'message' => 'Media deleted successfully']);
            break;

        case 'GET':
            // Handle media retrieval
            if (!isset($_GET['property_id'])) {
                throw new Exception('Missing property ID');
            }

            $propertyId = (int)$_GET['property_id'];
            $mediaType = $_GET['media_type'] ?? null;

            $query = "SELECT * FROM property_media WHERE property_id = ?";
            $params = [$propertyId];

            if ($mediaType) {
                $query .= " AND media_type = ?";
                $params[] = $mediaType;
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => array_map(function($item) {
                    $item['full_path'] = '/uploads/property_media/' . $item['file_path'];
                    return $item;
                }, $media)
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