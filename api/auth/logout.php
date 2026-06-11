<?php
/**
 * POST /api/auth/logout
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Intentar obtener el userId del token — si falla, igual limpiamos cookies
$userId = null;
require_once __DIR__ . '/../../core/JWT.php';
$token = get_bearer_token();
if ($token) {
    try {
        JWT::init();
        $payload = JWT::decode($token);
        $userId = $payload['sub'] ?? null;
    } catch (Exception $e) {
        // Token inválido/expirado — continuar con limpieza
    }
}

$body = json_body();
$db = Database::connect();

if (!empty($body['refreshToken']) && $userId) {
    // Solo eliminar el refresh token si pertenece al usuario autenticado
    $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE token = ? AND user_id = ?');
    $stmt->execute([$body['refreshToken'], $userId]);
} elseif ($userId) {
    // Si no envía refreshToken, eliminar todos los del usuario
    $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
    $stmt->execute([$userId]);
}

// Limpiar cookies HttpOnly del navegador
$cookieParams = ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict'];
setcookie('auth_token', '', $cookieParams);
setcookie('refresh_token', '', $cookieParams);

json_success(200, ['message' => 'Sesión cerrada correctamente']);
