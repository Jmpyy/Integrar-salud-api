<?php
/**
 * GET /api/auth/me
 * Headers: Authorization: Bearer <token>
 */
require_once __DIR__ . '/../../core/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = require_auth();
unset($user['iat'], $user['exp']);

json_success(200, ['user' => $user]);
