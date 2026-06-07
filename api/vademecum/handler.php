<?php
/**
 * GET    /api/vademecum
 * POST   /api/vademecum
 * PUT    /api/vademecum/{id}
 * DELETE /api/vademecum/{id}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$medId = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

// ─── GET ACTIVE PRESCRIPTIONS ───
if ($method === 'GET' && isset($pathParts[0]) && $pathParts[0] === 'active_prescriptions') {
    $stmt = $db->query('
        SELECT m.*, p.name as patientName, p.id as patientId 
        FROM medications m 
        JOIN patients p ON m.patient_id = p.id 
        WHERE m.active = 1 
        ORDER BY m.start_date DESC
    ');
    $prescriptions = $stmt->fetchAll();
    json_success(200, ['prescriptions' => $prescriptions]);
    exit;
}

// ─── GET ALL / SEARCH ───
if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    if ($search) {
        $stmt = $db->prepare('SELECT * FROM vademecum WHERE name LIKE ? OR doses LIKE ? ORDER BY name ASC');
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $db->query('SELECT * FROM vademecum ORDER BY name ASC');
    }
    $meds = $stmt->fetchAll();
    json_success(200, ['medications' => $meds]);
}

// ─── CREATE ───
if ($method === 'POST') {
    $errors = validate_required($body, ['name']);
    if (!empty($errors)) {
        json_error(400, 'Nombre de medicamento requerido', $errors);
    }

    $stmt = $db->prepare('INSERT INTO vademecum (name, doses, description) VALUES (?, ?, ?)');
    $stmt->execute([
        $body['name'],
        $body['doses'] ?? '',
        $body['description'] ?? '',
    ]);
    
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM vademecum WHERE id = ?');
    $stmt->execute([$id]);
    
    json_success(201, ['medication' => $stmt->fetch()]);
}

// ─── UPDATE ───
if ($method === 'PUT') {
    if (!$medId) json_error(400, 'ID requerido');
    
    $stmt = $db->prepare('UPDATE vademecum SET name = ?, doses = ?, description = ? WHERE id = ?');
    $stmt->execute([
        $body['name'],
        $body['doses'] ?? '',
        $body['description'] ?? '',
        $medId
    ]);
    
    json_success(200, ['message' => 'Actualizado']);
}

// ─── DELETE ───
if ($method === 'DELETE') {
    if (!$medId) json_error(400, 'ID requerido');
    $stmt = $db->prepare('DELETE FROM vademecum WHERE id = ?');
    $stmt->execute([$medId]);
    json_success(200, ['message' => 'Eliminado']);
}

json_error(405, 'Method not allowed');
