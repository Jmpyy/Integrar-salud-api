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
$refreshToken = $body['refreshToken'] ?? $_COOKIE['refresh_token'] ?? null;

if (empty($refreshToken)) {
    json_error(400, 'Refresh token requerido');
}

JWT::init();

try {
    $payload = JWT::decode($refreshToken);
} catch (Exception $e) {
    json_error(401, 'Refresh token inválido o expirado');
}

if (($payload['type'] ?? '') !== 'refresh') {
    json_error(401, 'Token no es un refresh token');
}

// Verify token exists in DB and not expired
$db = Database::connect();
$stmt = $db->prepare('SELECT id, user_id, expires_at FROM refresh_tokens WHERE token = ? AND expires_at > NOW()');
$stmt->execute([$refreshToken]);
$tokenRecord = $stmt->fetch();

if (!$tokenRecord) {
    json_error(401, 'Refresh token inválido');
}

// Get user data
$stmt = $db->prepare('SELECT id, name, profile_picture, email, role, doctor_id, staff_id, must_change_password FROM users WHERE id = ?');
$stmt->execute([$tokenRecord['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    json_error(401, 'Usuario no encontrado');
}

// Generate new access token
$newAccessToken = JWT::encode([
    'sub'   => $user['id'],
    'name'  => $user['name'],
    'profile_picture' => $user['profile_picture'],
    'email' => $user['email'],
    'role'  => $user['role'],
    'doctor_id' => $user['doctor_id'] ? (int)$user['doctor_id'] : null,
    'staff_id'  => $user['staff_id'] ? (int)$user['staff_id'] : null,
    'must_change_password' => (bool)$user['must_change_password']
]);

// Optionally rotate refresh token
$newRefreshToken = JWT::encode([
    'sub'  => $user['id'],
    'type' => 'refresh',
], JWT::getRefreshTtl());

// Update token in DB
$stmt = $db->prepare('UPDATE refresh_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?');
$stmt->execute([$newRefreshToken, $tokenRecord['id']]);

$stmtConfig = $db->query('SELECT config_json FROM system_settings WHERE id = 1');
$rowConfig = $stmtConfig->fetch();
$sysConfig = $rowConfig ? json_decode($rowConfig['config_json'], true) : [];
$sessionTimeout = isset($sysConfig['sessionTimeout']) ? (int)$sysConfig['sessionTimeout'] : 60;

// Aquí no sabemos el estado original de "rememberMe" del frontend,
// pero podemos inferirlo de si el refresh_token expiraba a los 30 días o a los 7 días.
// Por simplicidad, siempre renovamos la cookie como persistente si el refresh_token original era persistente
// En el backend, el refresh token se expira en DATE_ADD(NOW(), INTERVAL 30 DAY)
if ($sessionTimeout === 0 || strtotime($tokenRecord['expires_at']) > time() + (8 * 24 * 3600)) {
    // Si la expiración original del refresh era mayor a 7-8 días (ej. 30 días), significa que era "Remember Me"
    $authExpiry = time() + (7 * 24 * 3600); // 7 days
    $refreshExpiry = time() + (30 * 24 * 3600); // 30 days
} else {
    // Si no, también le damos la expiración original de 7 días, porque los móviles cierran las apps todo el tiempo
    // ¡Los usuarios en celulares odian loguearse a cada rato!
    // Haremos que la cookie dure 7 días por defecto, a menos que el admin cambie la config
    $authExpiry = time() + (7 * 24 * 3600);
    $refreshExpiry = time() + (7 * 24 * 3600); 
}

$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('auth_token', $newAccessToken, [
    'expires'  => $authExpiry,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $isSecure,
]);
setcookie('refresh_token', $newRefreshToken, [
    'expires'  => $refreshExpiry,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $isSecure,
]);

json_success(200, [
    'token'        => $newAccessToken,
    'refreshToken' => $newRefreshToken,
]);
