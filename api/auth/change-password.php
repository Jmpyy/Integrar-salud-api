<?php
/**
 * POST /api/auth/change-password
 * Body: { current_password, new_password }
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

$user = require_auth();
$db = Database::connect();
$body = json_body();

$errors = validate_required($body, ['current_password', 'new_password']);
if (!empty($errors)) {
    json_error(400, 'Datos incompletos', $errors);
}

// Check current password
$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['sub']]);
$dbUser = $stmt->fetch();

if (!$dbUser || !password_verify($body['current_password'], $dbUser['password_hash'])) {
    json_error(401, 'La contraseña actual es incorrecta.');
}

// Update password
$newHash = password_hash($body['new_password'], PASSWORD_BCRYPT);
$stmt = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
$stmt->execute([$newHash, $user['sub']]);

json_success(200, ['message' => 'Contraseña actualizada correctamente. Reingresa con tu nueva clave.']);
