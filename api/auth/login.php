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
// SECURITY: No confiar ciegamente en X-Forwarded-For (puede ser falsificado).
// Solo usarlo si la variable de entorno TRUST_PROXY está activa (servidor detrás de un proxy/LB conocido).
$trustProxy = Env::get('TRUST_PROXY', 'false') === 'true';
if ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
} else {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
$limiter   = new RateLimiter($db);

if ($limiter->isBlocked($clientIp)) {
    $secsLeft = $limiter->getSecondsRemaining($clientIp);
    $minsLeft = ceil($secsLeft / 60);
    json_error(429, "Demasiados intentos fallidos. Intenta de nuevo en {$minsLeft} minuto(s).", [
        'retry_after_seconds' => $secsLeft,
    ]);
}
// ──────────────────────────────────────────────────────────

$stmt = $db->prepare('SELECT id, name, email, password_hash, role, must_change_password, doctor_id, staff_id, password_changed_at FROM users WHERE email = ?');
$stmt->execute([$body['email']]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    // Registrar intento fallido
    $limiter->recordFailure($clientIp);
    json_error(401, 'Credenciales no válidas');
}

// Login exitoso — resetear contador
$limiter->recordSuccess($clientIp);

// Limpiar tokens expirados antiguos
$db->exec('DELETE FROM refresh_tokens WHERE expires_at < NOW()');

// ── Password Expiration Policy ──
if (!$user['must_change_password']) {
    $stmtSettings = $db->query('SELECT config_json FROM system_settings WHERE id = 1');
    $settingsRow = $stmtSettings->fetch();
    $config = $settingsRow ? json_decode($settingsRow['config_json'], true) : [];
    
    $requireDays = isset($config['requirePasswordChangeDays']) ? (int)$config['requirePasswordChangeDays'] : 0;
    
    if ($requireDays > 0 && !empty($user['password_changed_at'])) {
        $changedAt = new DateTime($user['password_changed_at']);
        $now = new DateTime();
        $diff = $now->diff($changedAt)->days;
        
        if ($diff >= $requireDays) {
            $user['must_change_password'] = 1;
            $db->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?')->execute([$user['id']]);
        }
    }
}

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

$rememberMe = isset($body['rememberMe']) ? (bool)$body['rememberMe'] : false;
$authExpiry = $rememberMe ? (time() + 3600) : 0;
$refreshExpiry = $rememberMe ? (time() + 604800) : 0;

// Emitir cookies HttpOnly (no accesibles por JavaScript — protección XSS)
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('auth_token', $accessToken, [
    'expires'  => $authExpiry,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $isSecure, // true automáticamente cuando se active HTTPS
]);
setcookie('refresh_token', $refreshToken, [
    'expires'  => $refreshExpiry,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $isSecure,
]);

json_success(200, [
    'token'        => $accessToken,
    'user'         => $user,
]);

