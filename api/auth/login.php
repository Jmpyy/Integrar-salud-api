<?php
/**
 * POST /api/auth/login
 * Body: { email, password }
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/JWT.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/RateLimiter.php';

$body = json_body();
$errors = validate_required($body, ['email', 'password']);
if (!empty($errors)) {
    json_error(400, 'Datos incompletos', $errors);
}

$db = Database::connect();

// ── Rate Limiting ──────────────────────────────────────────
$clientIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$clientIp  = trim(explode(',', $clientIp)[0]); // Si hay proxy, tomar solo la primera IP
$limiter   = new RateLimiter($db);

if ($limiter->isBlocked($clientIp)) {
    $secsLeft = $limiter->getSecondsRemaining($clientIp);
    $minsLeft = ceil($secsLeft / 60);
    json_error(429, "Demasiados intentos fallidos. Intenta de nuevo en {$minsLeft} minuto(s).", [
        'retry_after_seconds' => $secsLeft,
    ]);
}
// ──────────────────────────────────────────────────────────

$stmt = $db->prepare('SELECT id, name, email, password_hash, role, must_change_password, doctor_id, staff_id FROM users WHERE email = ?');
$stmt->execute([$body['email']]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    // Registrar intento fallido
    $limiter->recordFailure($clientIp);
    json_error(401, 'Credenciales no válidas');
}

// Login exitoso — resetear contador
$limiter->recordSuccess($clientIp);

// Generate tokens
JWT::init();
$accessToken = JWT::encode([
    'sub'  => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'doctor_id' => $user['doctor_id'] ? (int)$user['doctor_id'] : null,
    'staff_id'  => $user['staff_id'] ? (int)$user['staff_id'] : null,
    'must_change_password' => (bool)$user['must_change_password']
]);

$refreshToken = JWT::encode([
    'sub'  => $user['id'],
    'type' => 'refresh',
], JWT::getRefreshTtl());

// Store refresh token in DB
$stmt = $db->prepare('INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))');
$stmt->execute([$user['id'], $refreshToken]);

// Remove password from response
unset($user['password_hash']);
$user['must_change_password'] = (bool)$user['must_change_password'];

json_success(200, [
    'token'        => $accessToken,
    'refreshToken' => $refreshToken,
    'user'         => $user,
]);

