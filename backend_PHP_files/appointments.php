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
            // Create new appointment
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['property_id']) || !isset($data['appointment_date']) || !isset($data['type'])) {
                throw new Exception('Missing required parameters');
            }

            $propertyId = (int)$data['property_id'];
            $appointmentDate = $data['appointment_date'];
            $type = $data['type'];
            $notes = $data['notes'] ?? null;

            // Verify property exists and get agent ID
            $stmt = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            $property = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$property) {
                throw new Exception('Property not found');
            }

            // Create appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (property_id, client_id, agent_id, appointment_date, status, type, notes)
                VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $propertyId,
                $user['id'],
                $property['agent_id'],
                $appointmentDate,
                $type,
                $notes
            ]);

            $appointmentId = $pdo->lastInsertId();

            // Fetch the created appointment
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       p.title as property_title,
                       CONCAT(u1.first_name, ' ', u1.last_name) as client_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as agent_name
                FROM appointments a
                JOIN properties p ON a.property_id = p.id
                JOIN users u1 ON a.client_id = u1.id
                JOIN users u2 ON a.agent_id = u2.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $appointment
            ]);
            break;

        case 'GET':
            // Get appointments
            $userId = $user['id'];
            $status = $_GET['status'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            $query = "
                SELECT a.*, 
                       p.title as property_title,
                       CONCAT(u1.first_name, ' ', u1.last_name) as client_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as agent_name
                FROM appointments a
                JOIN properties p ON a.property_id = p.id
                JOIN users u1 ON a.client_id = u1.id
                JOIN users u2 ON a.agent_id = u2.id
                WHERE (a.client_id = ? OR a.agent_id = ?)
            ";
            $params = [$userId, $userId];

            if ($status) {
                $query .= " AND a.status = ?";
                $params[] = $status;
            }

            if ($startDate) {
                $query .= " AND a.appointment_date >= ?";
                $params[] = $startDate;
            }

            if ($endDate) {
                $query .= " AND a.appointment_date <= ?";
                $params[] = $endDate;
            }

            $query .= " ORDER BY a.appointment_date ASC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $appointments
            ]);
            break;

        case 'PUT':
            // Update appointment status
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['appointment_id']) || !isset($data['status'])) {
                throw new Exception('Missing required parameters');
            }

            $appointmentId = (int)$data['appointment_id'];
            $status = $data['status'];
            $notes = $data['notes'] ?? null;

            // Verify appointment exists and user has permission
            $stmt = $pdo->prepare("
                SELECT * FROM appointments 
                WHERE id = ? AND (client_id = ? OR agent_id = ?)
            ");
            $stmt->execute([$appointmentId, $user['id'], $user['id']]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                throw new Exception('Appointment not found or permission denied');
            }

            // Update appointment
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, notes = COALESCE(?, notes)
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $appointmentId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment updated successfully'
            ]);
            break;

        case 'DELETE':
            // Cancel appointment
            $appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
            
            if (!$appointmentId) {
                throw new Exception('Missing appointment ID');
            }

            // Verify appointment exists and user has permission
            $stmt = $pdo->prepare("
                SELECT * FROM appointments 
                WHERE id = ? AND (client_id = ? OR agent_id = ?)
            ");
            $stmt->execute([$appointmentId, $user['id'], $user['id']]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                throw new Exception('Appointment not found or permission denied');
            }

            // Update appointment status to cancelled
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled'
                WHERE id = ?
            ");
            $stmt->execute([$appointmentId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment cancelled successfully'
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