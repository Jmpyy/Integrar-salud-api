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

require_once __DIR__ . '/../../core/RateLimiter.php';
$limiter = new RateLimiter($db);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($limiter->isBlocked($clientIp, 'change_password')) {
    $secsLeft = $limiter->getSecondsRemaining($clientIp, 'change_password');
    $minsLeft = ceil($secsLeft / 60);
    json_error(429, "Demasiados intentos fallidos. Intenta de nuevo en {$minsLeft} minuto(s).", [
        'retry_after_seconds' => $secsLeft,
    ]);
}

$errors = validate_required($body, ['current_password', 'new_password']);
if (!empty($errors)) {
    json_error(400, 'Datos incompletos', $errors);
}

if (strlen($body['new_password']) < 8 || !preg_match('/[A-Za-z]/', $body['new_password']) || !preg_match('/[0-9]/', $body['new_password'])) {
    json_error(400, 'La nueva contraseña debe tener al menos 8 caracteres, incluir letras y números.');
}

// Check current password
$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['sub']]);
$dbUser = $stmt->fetch();

if (!$dbUser || !password_verify($body['current_password'], $dbUser['password_hash'])) {
    $limiter->recordFailure($clientIp, 'change_password');
    json_error(401, 'La contraseña actual es incorrecta.');
}

$limiter->recordSuccess($clientIp, 'change_password');

// Update password
$newHash = password_hash($body['new_password'], PASSWORD_BCRYPT);
$stmt = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?');
$stmt->execute([$newHash, $user['sub']]);

json_success(200, ['message' => 'Contraseña actualizada correctamente. Reingresa con tu nueva clave.']);
