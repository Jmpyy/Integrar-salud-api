<?php
/**
 * PUT /api/auth/update-profile
 * Allows any authenticated user to update their own display name.
 * Body: { name }
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

$user = require_auth();   // any authenticated role
$db   = Database::connect();
$body = json_body();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    json_error(405, 'Método no permitido');
}

$updates = [];
$params = [];
$response = ['message' => 'Perfil actualizado correctamente.'];

error_log("UPDATE-PROFILE BODY: " . json_encode($body));

if (isset($body['name'])) {
    $name = trim($body['name']);
    if ($name === '') {
        json_error(400, 'El nombre no puede estar vacío.');
    }
    $updates[] = 'name = ?';
    $params[] = $name;
    $response['name'] = $name;
}

if (isset($body['profile_picture'])) {
    $updates[] = 'profile_picture = ?';
    $params[] = trim($body['profile_picture']);
    $response['profile_picture'] = trim($body['profile_picture']);
}

if (empty($updates)) {
    error_log("UPDATE-PROFILE 400: No se enviaron datos. Body: " . json_encode($body));
    json_error(400, 'No se enviaron datos para actualizar.');
}

try {
    $params[] = $user['sub'];
    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Sincronizar el nombre en el registro de médico o staff asociado si existe
    $stmtUser = $db->prepare('SELECT doctor_id, staff_id FROM users WHERE id = ?');
    $stmtUser->execute([$user['sub']]);
    $userData = $stmtUser->fetch();

    if ($userData && isset($name)) {
        if ($userData['doctor_id']) {
            $db->prepare('UPDATE doctors SET name = ? WHERE id = ?')->execute([$name, $userData['doctor_id']]);
        }
        if ($userData['staff_id']) {
            $db->prepare('UPDATE admin_staff SET name = ? WHERE id = ?')->execute([$name, $userData['staff_id']]);
        }
    }

    json_success(200, $response);
} catch (Exception $e) {
    error_log('Error al actualizar perfil: ' . $e->getMessage());
    json_error(500, 'Error interno del servidor al actualizar el perfil.');
}
