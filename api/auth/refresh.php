<?php
/**
 * POST /api/auth/refresh
 * Body: { refreshToken }
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/JWT.php';
require_once __DIR__ . '/../../core/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_body();
if (empty($body['refreshToken'])) {
    json_error(400, 'Refresh token requerido');
}

JWT::init();

try {
    $payload = JWT::decode($body['refreshToken']);
} catch (Exception $e) {
    json_error(401, 'Refresh token inválido o expirado');
}

if (($payload['type'] ?? '') !== 'refresh') {
    json_error(401, 'Token no es un refresh token');
}

// Verify token exists in DB and not expired
$db = Database::connect();
$stmt = $db->prepare('SELECT id, user_id, expires_at FROM refresh_tokens WHERE token = ? AND expires_at > NOW()');
$stmt->execute([$body['refreshToken']]);
$tokenRecord = $stmt->fetch();

if (!$tokenRecord) {
    json_error(401, 'Refresh token inválido');
}

// Get user data
$stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
$stmt->execute([$tokenRecord['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    json_error(401, 'Usuario no encontrado');
}

// Generate new access token
$newAccessToken = JWT::encode([
    'sub'   => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
]);

// Optionally rotate refresh token
$newRefreshToken = JWT::encode([
    'sub'  => $user['id'],
    'type' => 'refresh',
], JWT::getRefreshTtl());

// Update token in DB
$stmt = $db->prepare('UPDATE refresh_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?');
$stmt->execute([$newRefreshToken, $tokenRecord['id']]);

json_success(200, [
    'token'        => $newAccessToken,
    'refreshToken' => $newRefreshToken,
]);
