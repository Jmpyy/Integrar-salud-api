<?php
// backend/api/push/public_key.php
require_once __DIR__ . '/../../core/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::connect();
    $stmt = $db->query("SELECT public_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $keys = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$keys) {
        echo json_encode(['error' => 'VAPID keys not generated yet']);
        exit;
    }

    echo json_encode([
        'publicKey' => $keys['public_key']
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
