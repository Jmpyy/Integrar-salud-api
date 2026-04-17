<?php
/**
 * POST /api/patients/{patientId}/history - Add SOAP note
 * GET  /api/patients/{patientId}/history - Get history
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$patientId = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

if (!$patientId) {
    json_error(400, 'ID de paciente requerido');
}

// ─── GET HISTORY ───
if ($method === 'GET') {
    $stmt = $db->prepare('
        SELECT sh.*, d.name as doctor_name
        FROM soap_history sh
        JOIN doctors d ON sh.doctor_id = d.id
        WHERE sh.patient_id = ?
        ORDER BY sh.created_at DESC
    ');
    $stmt->execute([$patientId]);
    $history = $stmt->fetchAll();

    json_success(200, ['history' => $history]);
}

// ─── ADD ENTRY ───
if ($method === 'POST') {
    $errors = validate_required($body, ['doctorId']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $isAclaracion = !empty($body['isAclaracion']);

    if ($isAclaracion) {
        // Aclaración: uses content field
        if (empty($body['content'])) {
            json_error(400, 'Contenido requerido para aclaraciones');
        }
        $stmt = $db->prepare('INSERT INTO soap_history (patient_id, doctor_id, linked_to_id, is_aclaracion, content) VALUES (?, ?, ?, 1, ?)');
        $stmt->execute([
            $patientId,
            $body['doctorId'],
            $body['linkedToId'] ?? null,
            $body['content'],
        ]);
    } else {
        // Regular SOAP note
        $stmt = $db->prepare('INSERT INTO soap_history (patient_id, doctor_id, linked_to_id, is_aclaracion, subjective, objective, analysis, plan) VALUES (?, ?, ?, 0, ?, ?, ?, ?)');
        $stmt->execute([
            $patientId,
            $body['doctorId'],
            null,
            $body['subjective'] ?? null,
            $body['objective'] ?? null,
            $body['analysis'] ?? null,
            $body['plan'] ?? null,
        ]);
    }

    $entryId = (int)$db->lastInsertId();

    $stmt = $db->prepare('SELECT sh.*, d.name as doctor_name FROM soap_history sh JOIN doctors d ON sh.doctor_id = d.id WHERE sh.id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();

    json_success(201, ['entry' => $entry]);
}

json_error(405, 'Method not allowed');
