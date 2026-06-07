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
$entryId   = isset($pathParts[2]) && is_numeric($pathParts[2]) ? (int)$pathParts[2] : null;

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
    require_roles(['admin', 'medico']);
    $errors = validate_required($body, ['doctorId']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $isAclaracion = !empty($body['isAclaracion']) ? 1 : 0;
    $linkedToId = $body['linkedToId'] ?? null;

    $subjective = $body['subjective'] ?? null;
    $objective  = $body['objective'] ?? null;
    $analysis   = $body['analysis'] ?? null;
    $plan       = $body['plan'] ?? null;
    $content    = $body['content'] ?? null;

    if (!$subjective && !$objective && !$analysis && !$plan && !$content) {
        json_error(400, 'Debes completar al menos un campo del reporte');
    }

    $date = $body['date'] ?? date('Y-m-d H:i:s');

    $stmt = $db->prepare('
        INSERT INTO soap_history 
        (patient_id, doctor_id, linked_to_id, is_aclaracion, subjective, objective, analysis, plan, content, date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $patientId,
        $body['doctorId'],
        $linkedToId,
        $isAclaracion,
        $subjective,
        $objective,
        $analysis,
        $plan,
        $content,
        $date
    ]);

    $entryId = (int)$db->lastInsertId();

    $stmt = $db->prepare('SELECT sh.*, d.name as doctor_name FROM soap_history sh JOIN doctors d ON sh.doctor_id = d.id WHERE sh.id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();

    json_success(201, ['entry' => $entry]);
}

// ─── UPDATE ENTRY ───
if ($method === 'PUT') {
    require_roles(['admin', 'medico']);
    if (!$entryId) {
        json_error(400, 'ID de entrada requerido');
    }

    $stmt = $db->prepare('UPDATE soap_history SET subjective=?, objective=?, analysis=?, plan=?, content=?, doctor_id=?, date=? WHERE id=?');
    $stmt->execute([
        $body['subjective'] ?? null,
        $body['objective'] ?? null,
        $body['analysis'] ?? null,
        $body['plan'] ?? null,
        $body['content'] ?? null,
        $body['doctorId'] ?? null,
        $body['date'] ?? date('Y-m-d H:i:s'),
        $entryId
    ]);

    $stmt = $db->prepare('SELECT sh.*, d.name as doctor_name FROM soap_history sh JOIN doctors d ON sh.doctor_id = d.id WHERE sh.id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();

    json_success(200, ['entry' => $entry]);
}

// ─── DELETE ENTRY ───
if ($method === 'DELETE') {
    require_roles(['admin', 'medico']);
    if (!$entryId) {
        json_error(400, 'ID de entrada requerido');
    }

    $db->prepare('DELETE FROM soap_history WHERE id = ?')->execute([$entryId]);
    json_success(200, ['message' => 'Entrada eliminada']);
}

json_error(405, 'Method not allowed');
