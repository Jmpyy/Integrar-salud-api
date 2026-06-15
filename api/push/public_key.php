<?php
// backend/api/push/public_key.php
require_once __DIR__ . '/../../core/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::connect();
    $stmt = $db->query("SELECT public_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $keys = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$keys) {
        // Generar keys si no existen
        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            $vapid = \Minishlink\WebPush\VAPID::createVapidKeys();
            $stmt = $db->prepare("INSERT INTO vapid_keys (public_key, private_key) VALUES (?, ?)");
            $stmt->execute([$vapid['publicKey'], $vapid['privateKey']]);
            $keys = ['public_key' => $vapid['publicKey']];
        } else {
            echo json_encode(['error' => 'VAPID keys not generated and Composer vendor/autoload.php not found']);
            exit;
        }
    }

    echo json_encode([
        'publicKey' => $keys['public_key']
    ]);
} catch (Throwable $e) {
    error_log('Error al obtener public key: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
