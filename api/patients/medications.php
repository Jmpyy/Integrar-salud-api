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

// ─── PATCH (suspend) ───
if ($method === 'PATCH') {
    if (!$medId) {
        json_error(400, 'ID de medicación requerido');
    }

    $active = $body['active'] ?? 0;
    $stmt = $db->prepare('UPDATE medications SET active = ? WHERE id = ? AND patient_id = ?');
    $stmt->execute([(int)$active, $medId, $patientId]);

    if ($stmt->rowCount() === 0) {
        json_error(404, 'Medicación no encontrada');
    }

    $stmt = $db->prepare('SELECT * FROM medications WHERE id = ?');
    $stmt->execute([$medId]);
    $medication = $stmt->fetch();

    json_success(200, ['medication' => $medication]);
}

json_error(405, 'Method not allowed');
