<?php
/**
 * GET    /api/patients/{patientId}/medications
 * POST   /api/patients/{patientId}/medications
 * PATCH  /api/patients/{patientId}/medications/{medId}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$patientId = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;
$medId = isset($pathParts[2]) && is_numeric($pathParts[2]) ? (int)$pathParts[2] : null;

if (!$patientId) {
    json_error(400, 'ID de paciente requerido');
}

// ─── GET ALL ───
if ($method === 'GET') {
    $stmt = $db->prepare('SELECT * FROM medications WHERE patient_id = ? ORDER BY start_date DESC');
    $stmt->execute([$patientId]);
    $medications = $stmt->fetchAll();

    json_success(200, ['medications' => $medications]);
}

// ─── CREATE ───
if ($method === 'POST') {
    require_roles(['admin', 'medico']);
    $errors = validate_required($body, ['drug', 'dose', 'frequency']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $stmt = $db->prepare('INSERT INTO medications (patient_id, drug, dose, frequency, start_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $patientId,
        $body['drug'],
        $body['dose'],
        $body['frequency'],
        $body['startDate'] ?? date('Y-m-d'),
    ]);
    $medId = (int)$db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM medications WHERE id = ?');
    $stmt->execute([$medId]);
    $medication = $stmt->fetch();

    json_success(201, ['medication' => $medication]);
}

// ─── PATCH (suspend/activate) ───
if ($method === 'PATCH') {
    require_roles(['admin', 'medico']);
    debug_log('PATCH /medications', ['medId' => $medId, 'patientId' => $patientId, 'body' => $body]);
    
    if (!$medId) {
        json_error(400, 'ID de medicación requerido en la URL');
    }

    $active = isset($body['active']) ? ($body['active'] ? 1 : 0) : 0;
    
    try {
        // Primero verificamos que exista
        $stmtCheck = $db->prepare('SELECT id FROM medications WHERE id = ?');
        $stmtCheck->execute([$medId]);
        $row = $stmtCheck->fetch();
        
        if (!$row) {
            json_error(404, "La medicación ID $medId no existe en la base de datos");
        }

        $stmt = $db->prepare('UPDATE medications SET active = ? WHERE id = ? AND patient_id = ?');
        $stmt->execute([$active, $medId, $patientId]);
        
        // No usamos rowCount() porque si ya era 0, devuelve 0.
        // Si execute() no lanzó excepción, asumimos éxito o que no hubo cambios.

        $stmt = $db->prepare('SELECT * FROM medications WHERE id = ?');
        $stmt->execute([$medId]);
        $medication = $stmt->fetch();

        json_success(200, ['medication' => $medication]);
    } catch (Exception $e) {
        debug_log('ERROR in PATCH /medications', $e->getMessage());
        json_error(500, 'Error interno del servidor.');
    }
}

json_error(405, 'Method not allowed');
