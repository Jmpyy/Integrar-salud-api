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
require_once __DIR__ . '/../../core/Logger.php';

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

$stmt = $db->prepare('SELECT id, name, profile_picture, email, password_hash, role, must_change_password, doctor_id, staff_id, password_changed_at FROM users WHERE email = ?');
$stmt->execute([$body['email']]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    // Registrar intento fallido
    $limiter->recordFailure($clientIp);
    Logger::warn($db, 'LOGIN_FAILED', $user ? $user['id'] : null, ['email' => $body['email']]);
    json_error(401, 'Credenciales no válidas');
}

// Login exitoso — resetear contador
$limiter->recordSuccess($clientIp);
Logger::info($db, 'LOGIN_SUCCESS', $user['id'], ['email' => $user['email'], 'role' => $user['role']]);

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
    'profile_picture' => $user['profile_picture'],
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

$stmtConfig = $db->query('SELECT config_json FROM system_settings WHERE id = 1');
$rowConfig = $stmtConfig->fetch();
$sysConfig = $rowConfig ? json_decode($rowConfig['config_json'], true) : [];
$sessionTimeout = isset($sysConfig['sessionTimeout']) ? (int)$sysConfig['sessionTimeout'] : 60;

$rememberMe = isset($body['rememberMe']) ? (bool)$body['rememberMe'] : false;

// Si sessionTimeout es 0 (Mantener siempre activa) o si marcó "Recordarme", la sesión persiste
// Si sessionTimeout > 0, usamos ese tiempo en minutos, pero si es un número muy grande o "Recordarme", le damos más tiempo.
// Nota: Si es 0, no expirará al cerrar el navegador, le daremos 7 días.
if ($sessionTimeout === 0 || $rememberMe) {
    $authExpiry = time() + (7 * 24 * 3600); // 7 days
    $refreshExpiry = time() + (30 * 24 * 3600); // 30 days
} else {
    // Para evitar que los usuarios de celulares tengan que loguearse cada vez que cierran la app,
    // por defecto damos 7 días a la cookie. Si el frontend usa sessionStorage, lo manejará a su manera.
    $authExpiry = time() + (7 * 24 * 3600);
    $refreshExpiry = time() + (7 * 24 * 3600);
}

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

