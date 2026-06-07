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

$name = trim($body['name'] ?? '');
if ($name === '') {
    json_error(400, 'El nombre no puede estar vacío.');
}

try {
    $stmt = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
    $stmt->execute([$name, $user['sub']]);

    // Sincronizar el nombre en el registro de médico o staff asociado si existe
    $stmtUser = $db->prepare('SELECT doctor_id, staff_id FROM users WHERE id = ?');
    $stmtUser->execute([$user['sub']]);
    $userData = $stmtUser->fetch();

    if ($userData) {
        if ($userData['doctor_id']) {
            $db->prepare('UPDATE doctors SET name = ? WHERE id = ?')->execute([$name, $userData['doctor_id']]);
        }
        if ($userData['staff_id']) {
            $db->prepare('UPDATE admin_staff SET name = ? WHERE id = ?')->execute([$name, $userData['staff_id']]);
        }
    }

    json_success(200, ['message' => 'Nombre actualizado correctamente.', 'name' => $name]);
} catch (Exception $e) {
    json_error(500, 'Error al actualizar el perfil: ' . $e->getMessage());
}
