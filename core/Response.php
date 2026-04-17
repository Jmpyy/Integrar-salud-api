<?php
/**
 * Response helper — sends JSON with proper headers
 */
function debug_log(string $message, $data = null): void {
    // En producción, debug_log no hace nada para no filtrar datos sensibles
    require_once __DIR__ . '/Env.php';
    Env::load();
    if (Env::isProduction()) return;

    $logFile = __DIR__ . '/../debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedData = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    $entry = "[$timestamp] $message: $formattedData" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

function json_response(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(int $status, string $message, array $details = []): void {
    $response = ['error' => true, 'message' => $message];
    if (!empty($details)) {
        $response['details'] = $details;
    }
    json_response($status, $response);
}

function json_success(int $status, array $data): void {
    json_response($status, ['error' => false] + $data);
}

/**
 * Read JSON body from POST/PUT/PATCH requests
 */
function json_body(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/**
 * Validate required fields in body
 */
function validate_required(array $body, array $fields): array {
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($body[$field]) || trim($body[$field]) === '') {
            $errors[$field] = "El campo '$field' es requerido";
        }
    }
    return $errors;
}

/**
 * Extract Bearer token from Authorization header
 */
function get_bearer_token(): ?string {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // Fallback crítico para servidores Apache/XAMPP que en peticiones concurrentes pueden dropear getallheaders
    if (empty($auth) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (empty($auth) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Require authenticated user — decodes token and returns payload
 */
function require_auth(): array {
    require_once __DIR__ . '/JWT.php';
    JWT::init();

    $token = get_bearer_token();
    if (!$token) {
        json_error(401, 'Token de acceso requerido');
    }

    try {
        return JWT::decode($token);
    } catch (Exception $e) {
        json_error(401, $e->getMessage());
    }
}

/**
 * Require admin role
 */
function require_admin(): array {
    $user = require_auth();
    if (($user['role'] ?? '') !== 'admin') {
        json_error(403, 'Acceso restringido a administradores');
    }
    return $user;
}
