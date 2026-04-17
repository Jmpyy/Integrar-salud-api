<?php
/**
 * API Entry Point
 * All API requests go through this file
 * This avoids Apache's 405 on directory POST requests
 */

// CORS Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost',
    'http://localhost:3000',
];
$cors_origin = in_array($origin, $allowed_origins) ? $origin : '*';

header("Access-Control-Allow-Origin: $cors_origin");
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Forward to main router (go up one level to backend root)
require_once __DIR__ . '/../index.php';
