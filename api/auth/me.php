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

// Fetch fresh data from DB to get the latest profile picture and name
require_once __DIR__ . '/../../core/Database.php';
$db = Database::connect();
$stmt = $db->prepare('SELECT name, profile_picture FROM users WHERE id = ?');
$stmt->execute([$user['sub']]);
$freshUser = $stmt->fetch();

if ($freshUser) {
    $user['name'] = $freshUser['name'];
    $user['profile_picture'] = $freshUser['profile_picture'];
}

unset($user['iat'], $user['exp']);

json_success(200, ['user' => $user]);
