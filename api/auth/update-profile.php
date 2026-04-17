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

    // Also sync name on affiliated doctor / staff record if exists
    $db->prepare('UPDATE doctors     SET name = ? WHERE user_id = ?')->execute([$name, $user['sub']]);
    $db->prepare('UPDATE admin_staff SET name = ? WHERE user_id = ?')->execute([$name, $user['sub']]);

    json_success(200, ['message' => 'Nombre actualizado correctamente.', 'name' => $name]);
} catch (Exception $e) {
    json_error(500, 'Error al actualizar el perfil: ' . $e->getMessage());
}
