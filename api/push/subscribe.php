<?php
// backend/api/push/subscribe.php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

header('Content-Type: application/json');

try {
    // 1. Validar autenticación
    $user = require_auth();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['endpoint']) || !isset($input['keys']['p256dh']) || !isset($input['keys']['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos de suscripción incompletos']);
        exit;
    }

    $endpoint = $input['endpoint'];
    $p256dh = $input['keys']['p256dh'];
    $auth = $input['keys']['auth'];

    $db = Database::connect();

    // Comprobar si ya existe la suscripción para evitar duplicados exactos
    $checkStmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $checkStmt->execute([$endpoint]);
    
    if (!$checkStmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['sub'], $endpoint, $p256dh, $auth]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Error en push subscribe: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
