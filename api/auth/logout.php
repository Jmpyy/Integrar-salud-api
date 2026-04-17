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

$body = json_body();

if (!empty($body['refreshToken'])) {
    $db = Database::connect();
    $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE token = ?');
    $stmt->execute([$body['refreshToken']]);
}

json_success(200, ['message' => 'Sesión cerrada correctamente']);
