<?php
// backend/api/push/test.php — Endpoint de diagnóstico para push notifications
// SOLO PARA DEBUG — eliminar después de confirmar que funciona
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

header('Content-Type: application/json');

try {
    $user = require_auth();
    $db = Database::connect();
    
    $diagnostics = [];
    
    // 1. Verificar vendor/autoload.php
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    $diagnostics['vendor_autoload_exists'] = file_exists($autoloadPath);
    
    // 2. Verificar que la clase WebPush existe
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $diagnostics['webpush_class_exists'] = class_exists('Minishlink\WebPush\WebPush');
    } else {
        $diagnostics['webpush_class_exists'] = false;
    }
    
    // 3. Verificar tabla vapid_keys
    try {
        $stmt = $db->query("SELECT id, public_key, LEFT(private_key, 10) as pk_preview FROM vapid_keys ORDER BY id DESC LIMIT 1");
        $keys = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnostics['vapid_keys'] = $keys ? [
            'exists' => true,
            'id' => $keys['id'],
            'public_key_length' => strlen($keys['public_key']),
            'private_key_preview' => $keys['pk_preview'] . '...'
        ] : ['exists' => false];
    } catch (Throwable $e) {
        $diagnostics['vapid_keys'] = ['error' => $e->getMessage()];
    }
    
    // 4. Verificar tabla push_subscriptions
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$user['sub']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnostics['user_subscriptions'] = $count['total'];
        
        // Obtener detalles de las suscripciones
        $stmt2 = $db->prepare("SELECT id, LEFT(endpoint, 60) as endpoint_preview, LEFT(p256dh, 20) as p256dh_preview, LEFT(auth, 10) as auth_preview FROM push_subscriptions WHERE user_id = ?");
        $stmt2->execute([$user['sub']]);
        $diagnostics['subscription_details'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $diagnostics['user_subscriptions'] = ['error' => $e->getMessage()];
    }
    
    // 5. Intentar enviar notificación de prueba
    if ($diagnostics['vendor_autoload_exists'] && $diagnostics['webpush_class_exists']) {
        try {
            require_once __DIR__ . '/../../libs/PushNotificationService.php';
            $pushService = new PushNotificationService($db);
            $result = $pushService->notifyUser(
                $user['sub'], 
                '🔔 Test Push', 
                'Si ves esto, las push notifications están funcionando correctamente!', 
                '/dashboard'
            );
            $diagnostics['test_send_result'] = $result ? 'SUCCESS - Notification sent' : 'FAILED - notifyUser returned false';
        } catch (Throwable $e) {
            $diagnostics['test_send_result'] = 'ERROR: ' . $e->getMessage();
        }
    } else {
        $diagnostics['test_send_result'] = 'SKIPPED - WebPush library not available';
    }
    
    echo json_encode([
        'success' => true,
        'user_id' => $user['sub'],
        'diagnostics' => $diagnostics
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
