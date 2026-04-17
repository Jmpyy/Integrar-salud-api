<?php
/**
 * GET /api/doctors
 * GET /api/doctors/{id}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_auth();
$db = Database::connect();

// Check if specific doctor requested
$path = explode('/', trim($_GET['path'] ?? '', '/'));
$id = $path[0] ?? null;

if ($id && is_numeric($id)) {
    // Single doctor with schedule
    $stmt = $db->prepare('
        SELECT d.*, u.id as user_id 
        FROM doctors d
        LEFT JOIN users u ON u.doctor_id = d.id
        WHERE d.id = ?');
    $stmt->execute([$id]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        json_error(404, 'Doctor no encontrado');
    }

    // Get schedule
    $stmt = $db->prepare('SELECT day_of_week, start_hour, end_hour FROM doctor_schedules WHERE doctor_id = ?');
    $stmt->execute([$id]);
    $scheduleRows = $stmt->fetchAll();

    // Transform schedule to { 1: {start, end}, 2: {start, end}, ... }
    $schedule = [];
    foreach ($scheduleRows as $row) {
        $schedule[$row['day_of_week']] = [
            'start' => (int)$row['start_hour'],
            'end'   => (int)$row['end_hour'],
        ];
    }
    $doctor['schedule'] = $schedule;

    json_success(200, ['doctor' => $doctor]);
}

// All doctors
$stmt = $db->query('
    SELECT d.*, u.id as user_id 
    FROM doctors d
    LEFT JOIN users u ON u.doctor_id = d.id
    ORDER BY d.name');
$doctors = $stmt->fetchAll();

// Append schedule for each
foreach ($doctors as &$doctor) {
    $stmt = $db->prepare('SELECT day_of_week, start_hour, end_hour FROM doctor_schedules WHERE doctor_id = ?');
    $stmt->execute([$doctor['id']]);
    $scheduleRows = $stmt->fetchAll();

    $schedule = [];
    foreach ($scheduleRows as $row) {
        $schedule[$row['day_of_week']] = [
            'start' => (int)$row['start_hour'],
            'end'   => (int)$row['end_hour'],
        ];
    }
    $doctor['schedule'] = $schedule;
}
unset($doctor);

json_success(200, ['doctors' => $doctors]);
